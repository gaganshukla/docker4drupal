<?php

namespace Drupal\activity_tracking\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\activity_tracking\Services\ActivityTrackingService;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Activity tracking form.
 */
class ActivityTracking extends FormBase implements ContainerInjectionInterface {

  /**
   * Drupal\activity_tracking\Services\ActivityTrackingService definition.
   *
   * @var Drupal\activity_tracking\Services\ActivityTrackingService
   */
  protected $activitytracking;

  /**
   * Symfony\Component\HttpFoundation\Request definition.
   *
   * @var Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Drupal\Core\Database\Connection definition.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory object.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $database,
  RequestStack $request,
  ConfigFactoryInterface $config_factory,
  ActivityTrackingService $activitytracking) {
    $this->database          = $database;
    $this->request           = $request;
    $this->configFactory     = $config_factory;
    $this->activity_tracking = $activitytracking;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('activity_tracking.logger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'actvity_tracking_table_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $connection      = $this->database;
    $trackingService = $this->activity_tracking;

    // Fetch the excluded entities to track.
    $activityConfig = $this->configFactory->get('activity_tracking.settings');
    $excludeList    = $activityConfig->get('excluded_entities');
    $excludeBundles = explode(',', $excludeList);

    // Sanitize and fetch filter values.
    $formRequest        = $this->request->getCurrentRequest();
    $filter_section     = $formRequest->query->get('filter_section');
    $filter_actions     = $formRequest->query->get('filter_actions');
    $filter_performedby = $formRequest->query->get('filter_performedby');
    $filter_from_date   = $formRequest->query->get('filter_from_date');
    $filter_to_date     = $formRequest->query->get('filter_to_date');

    $rows = [];

    $query = $connection->select('activity_tracker', 'tracker');
    $query->distinct();
    $query->fields('tracker', [
      'entity_id',
      'action',
      'bundle',
      'uid',
      'user_name',
      'ip',
      'user_agent',
      'created',
    ]);

    if (!empty($filter_section)) {
      $query->condition('tracker.bundle', $filter_section, '=');
    }

    if (!empty($filter_actions)) {
      $query->condition('tracker.action', $filter_actions, '=');
    }

    if (isset($filter_performedby) && $filter_performedby >= 0) {
      $query->condition('tracker.uid', $filter_performedby, '=');
    }

    if (!empty($filter_from_date) && !empty($filter_to_date)) {
      $from_date = $filter_from_date;
      $to_date   = $filter_to_date;

      if ($filter_from_date == $filter_to_date) {
        $from_date = $filter_from_date . ' 00:00:00';
        $to_date   = $filter_to_date . ' 23:59:59';
      }

      $query->condition('tracker.created', strtotime($from_date), '>=');
      $query->condition('tracker.created', strtotime($to_date), '<=');
    }

    $query->condition('tracker.bundle', $excludeBundles, 'NOT IN')
      ->orderBy('tracker.created', 'DESC');

    $header = [
      'Entity ID',
      'Section',
      'Action',
      'Performed By',
      'IP Address',
      'User Agent',
      'Log Time',
    ];

    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(50);
    $results = $pager->execute()->fetchAll();

    foreach ($results as $result) {
      $browserData = $trackingService->getBrowser($result->user_agent);
      if ($browserData['name'] == 'Unknown') {

        $browserName = $result->user_agent;

      }
      else {

        $browserName = $browserData['name'] . '/' . $browserData['version'];

      }

      if ($result->uid == 0) {

        $userName = 'Cron';

      }
      else {

        $userName = ucfirst($result->user_name);

      }

      $rows[] = [
        'Entity ID'    => $result->entity_id,
        'Section'      => str_replace('_', ' ', $result->bundle),
        'Action'       => ucfirst($result->action),
        'Performed By' => $userName,
        'IP Address'   => $result->ip,
        'User Agent'   => $browserName,
        'Log Time'     => date('d/m/Y H:i', ($result->created)),
      ];
    }

    $sectionQuery = $connection->select('activity_tracker', 'tracker');
    $sectionQuery->distinct();
    $sectionQuery->fields('tracker', ['bundle']);
    $sectionQuery->condition('tracker.bundle', $excludeBundles, 'NOT IN');
    $sectionQueryResult = $sectionQuery->execute();
    $sectionList = [];
    $sectionList[] = '-- Select --';
    while ($sectionRes = $sectionQueryResult->fetchObject()) {
      $sectionList[$sectionRes->bundle] = str_replace('_', ' ', $sectionRes->bundle);
    }

    $userQuery = $connection->select('activity_tracker', 'tracker');
    $userQuery->distinct();
    $userQuery->fields('tracker', ['uid', 'user_name']);
    $userQuery->condition('tracker.bundle', $excludeBundles, 'NOT IN');
    $userQueryResult = $userQuery->execute();
    $userList = [];
    $userList[''] = '-- Select --';
    while ($userRes = $userQueryResult->fetchObject()) {
      if ($userRes->uid == 0) {

        $userName = 'Cron';

      }
      else {

        $userName = ucfirst($userRes->user_name);

      }
      $userList[$userRes->uid] = $userName;
    }

    // Filter Form prepared.
    $form['filter_section'] = [
      '#title'         => $this->t('Section'),
      '#type'          => 'select',
      '#options'       => $sectionList,
      '#default_value' => $filter_section,
    ];

    $actionsList = [
      ''       => '--Select--',
      'add'    => 'Add/Insert',
      'update' => 'Update',
      'delete' => 'Delete',
    ];

    $form['filter_actions'] = [
      '#title'         => $this->t('Actions'),
      '#type'          => 'select',
      '#options'       => $actionsList,
      '#default_value' => $filter_actions,
    ];

    $form['filter_performedby'] = [
      '#title'         => $this->t('Users'),
      '#type'          => 'select',
      '#options'       => $userList,
      '#default_value' => $filter_performedby,
    ];

    $format = 'd-m-y';

    $form['filter_from_date'] = [
      '#title'         => $this->t('From Date'),
      '#type'          => 'date',
      '#date_format'   => $format,
      '#default_value' => $filter_from_date,
    ];

    $form['filter_to_date'] = [
      '#title'         => $this->t('To Date'),
      '#type'          => 'date',
      '#date_format'   => $format,
      '#default_value' => $filter_to_date,
    ];

    $form['filter_apply_button'] = [
      '#type'        => 'submit',
      '#value'       => $this->t('Search Log'),
      '#button_type' => 'primary',
    ];

    // Generate the table.
    $form['config_table'] = [
      '#theme'  => 'table',
      '#header' => $header,
      '#rows'   => $rows,
      '#empty'  => $this->t('No items available'),
    ];

    // Finally add the pager.
    $form['pager'] = [
      '#type' => 'pager',
    ];

    $form['#method'] = 'get';
    $form['#cache'] = ['max-age' => 0];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
