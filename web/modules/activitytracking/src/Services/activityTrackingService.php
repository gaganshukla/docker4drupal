<?php

namespace Drupal\activity_tracking\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxy;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Service handler for activity tracking.
 */
class ActivityTrackingService {

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

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
   * Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountProxy $current_user,
  Connection $database,
  RequestStack $request,
  ConfigFactoryInterface $config_factory,
  LoggerChannelFactory $loggerFactory) {
    $this->currentUser   = $current_user;
    $this->database      = $database;
    $this->request       = $request;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $loggerFactory->get('activity_tracking');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('database'),
      $container->get('request_stack'),
      $container->get('config.factory')
    );
  }

  /**
   * Fetch the browser name.
   */
  public function getBrowser($userAgent) {

    $u_agent  = $userAgent;
    $bname    = 'Unknown';
    $platform = 'Unknown';
    $version  = "";
    $ub       = "";

    // First get the platform.
    if (preg_match('/linux/i', $u_agent)) {

      $platform = 'linux';

    }
    elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {

      $platform = 'mac';

    }
    elseif (preg_match('/windows|win32/i', $u_agent)) {

      $platform = 'windows';

    }

    // Next get the name of the useragent yes seperately and for good reason.
    if (preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent)) {

      $bname = 'Internet Explorer';
      $ub    = "MSIE";

    }
    elseif (preg_match('/Firefox/i', $u_agent)) {

      $bname = 'Mozilla Firefox';
      $ub    = "Firefox";

    }
    elseif (preg_match('/Chrome/i', $u_agent)) {

      $bname = 'Google Chrome';
      $ub    = "Chrome";

    }
    elseif (preg_match('/Safari/i', $u_agent)) {

      $bname = 'Apple Safari';
      $ub    = "Safari";

    }
    elseif (preg_match('/Opera/i', $u_agent)) {

      $bname = 'Opera';
      $ub    = "Opera";

    }
    elseif (preg_match('/Netscape/i', $u_agent)) {

      $bname = 'Netscape';
      $ub    = "Netscape";

    }

    // Finally get the correct version number.
    $known   = ['Version', $ub, 'other'];
    $pattern = '#(?<browser>' . implode('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';

    if (!preg_match_all($pattern, $u_agent, $matches)) {
      // We have no matching number just continue.
    }

    // See how many we have.
    $i = count($matches['browser']);

    if ($i != 1) {

      // We will have two since we are not using 'other' argument yet.
      // See if version is before or after the name.
      if (strripos($u_agent, "Version") < strripos($u_agent, $ub)) {

        $version = $matches['version'][0];

      }
      else {

        $version = $matches['version'][1];

      }

    }
    else {

      $version = $matches['version'][0];

    }

    // Check if we have a number.
    if ($version == NULL || $version == "") {

      $version = "?";

    }

    return [
      'userAgent' => $u_agent,
      'name'      => $bname,
      'version'   => $version,
      'platform'  => $platform,
      'pattern'   => $pattern,
    ];
  }

  /**
   * Stores the data into tracker table.
   */
  public function logActivity($entity, $userAction) {

    // Get logged user session.
    $currentUser = $this->currentUser;

    // Fetch the excluded entities to track.
    $activityConfig = $this->configFactory->get('activity_tracking.settings');
    $excludeList    = $activityConfig->get('excluded_entities');
    $excludeBundles = explode(',', $excludeList);

    // Valildate for bundle exclusion.
    if (!in_array($entity->bundle(), $excludeBundles)) {

      // Fetching user's IP address.
      $ip = $this->request->getCurrentRequest()->getClientIp();

      if (empty($ip)) {
        $ip = '';
      }

      $httpagent = $_SERVER['HTTP_USER_AGENT'];

      // Override for exposed REST API calls.
      if (!$httpagent) {
        $httpagent = 'Rest API/Lambda';
      }

      $keys = [
        'id' => NULL,
      ];

      $fields = [
        'entity_id'  => $entity->id(),
        'action'     => $userAction,
        'bundle'     => $entity->bundle(),
        'uid'        => $currentUser->id(),
        'user_name'  => $currentUser->getDisplayName(),
        'ip'         => $ip,
        'user_agent' => $httpagent,
        'created'    => REQUEST_TIME,
      ];

      try {
        $this->database->merge('activity_tracker')
          ->key($keys)
          ->fields($fields)
          ->execute();
      }
      catch (Exception $e) {
        // Exception handling if something else gets thrown.
        $this->loggerFactory->error($e->getMessage());
      }

    }

  }

}
