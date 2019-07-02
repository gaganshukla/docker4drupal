<?php

namespace Drupal\activity_tracking\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides settings for Activity tracking module.
 */
class ActivityTrackingSettingsForm extends ConfigFormBase {
  /**
   * The module manager service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs an AutologoutSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler) {
    parent::__construct($config_factory);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['activity_tracking.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'activity_tracking_settings';
  }

  /**
   * Build form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('activity_tracking.settings');
    $form['excluded_entities'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Exclude Entities (comma seperated)'),
      '#description'   => $this->t('Exclude the activty tracking entities for excluding'),
      '#default_value' => $config->get('excluded_entities'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $values = $form_state->getValues();

    $this->config('activity_tracking.settings')
      ->set('excluded_entities', $values['excluded_entities'])
      ->save();
  }

}
