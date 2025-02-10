<?php

namespace Drupal\api_sentinel\Service;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Provides a service for sending API key notifications.
 *
 * This implementation enqueues notification tasks to be processed asynchronously
 * and offers methods to notify users via email and on-site messenger.
 */
class ApiSentinelNotificationService implements ApiSentinelNotificationServiceInterface {

  use StringTranslationTrait;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The notification queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected QueueInterface $notificationQueue;

  /**
   * Constructs a new ApiSentinelNotificationService.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   */
  public function __construct(MailManagerInterface $mailManager, MessengerInterface $messenger, QueueFactory $queueFactory, ConfigFactoryInterface $configFactory, LoggerChannelInterface $logger) {
    $this->mailManager = $mailManager;
    $this->messenger = $messenger;
    $this->queueFactory = $queueFactory;
    $this->configFactory = $configFactory;
    $this->logger = $logger;
    // Get or create the notification queue.
    $this->notificationQueue = $this->queueFactory->get('api_sentinel_notification');
  }

  /**
   * {@inheritdoc}
   */
  public function queueNotification(string $type, AccountInterface $account, array $data = []): void {
    if (!$account->getEmail()) {
      return;
    }
    $notification = [
      'type' => $type,
      'account' => $account,
      'email' => $account->getEmail(),
      'langcode' => $account->getPreferredLangcode(),
      'data' => $data,
      'timestamp' => time(),
    ];
    $this->notificationQueue->createItem($notification);
    $this->processNotification($notification);
    $this->logger->info('Notification queued for user @uid (type: @type)', [
      '@uid' => $account->id(),
      '@type' => $type,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function notifyNewKey(AccountInterface $account): void {
    $data = [
      'link' => Url::fromRoute('api_sentinel.view_api_key', ['uid' => $account->id()], ['absolute' => TRUE])->toString(),
    ];
    $this->queueNotification('new_key', $account, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function notifyBlocked(AccountInterface $account): void {
    $this->queueNotification('block', $account);
  }

  /**
   * {@inheritdoc}
   */
  public function notifyUnblocked(AccountInterface $account): void {
    $this->queueNotification('unblock', $account);
  }

  /**
   * {@inheritdoc}
   */
  public function notifyRevoked(AccountInterface $account): void {
    $this->queueNotification('revoke', $account);
  }

  /**
   * {@inheritdoc}
   */
  public function notifyRateLimit(AccountInterface $account): void {
    $this->queueNotification('rate_limit', $account);
  }

  /**
   * {@inheritdoc}
   */
  public function processNotification(array $notification): void {
    $type = $notification['type'];
    $account = $notification['account'];
    $email = $notification['email'];
    $langcode = $notification['langcode'];
    $data = $notification['data'];

    // Determine subject and message based on the notification type.
    switch ($type) {
      case 'new_key':
        $subject = $this->t('Your new API key');
        $message = $this->t('A new API key has been generated for your account.');
        if (!empty($data['link'])) {
          $message .= "\n" . $this->t('Click this secure link to view your API key: @link', ['@link' => $data['link']]);
        }
        break;

      case 'block':
        $subject = $this->t('Your API key has been blocked');
        $message = $this->t('Your API key has been blocked due to security concerns. Please contact support for further information.');
        break;

      case 'unblock':
        $subject = $this->t('Your API key has been unblocked');
        $message = $this->t('Your API key has been unblocked and is active again.');
        break;

      case 'revoke':
        $subject = $this->t('Your API key has been revoked');
        $message = $this->t('Your API key has been revoked. If this is unexpected, please contact support.');
        break;

      case 'rate_limit':
        $subject = $this->t('API key rate limit reached');
        $message = $this->t('Your API key has reached its rate limit. Please reduce your request frequency.');
        break;

      default:
        $subject = $this->t('API Key Notification');
        $message = $this->t('There is an update regarding your API key.');
        break;
    }

    // Prepare email parameters.
    $module = 'api_sentinel';
    $params = [
      'subject' => $subject,
      'message' => $message,
      'account' => $account,
    ];

    $result = $this->mailManager->mail($module, $type, $email, $langcode, $params, NULL, TRUE);

    if ($result['result'] !== TRUE) {
      $this->logger->error('Failed to send notification email to user @uid (type: @type)', [
        '@uid' => $account->id(),
        '@type' => $type,
      ]);
    }
    else {
      $this->logger->info('Notification email sent to user @uid (type: @type)', [
        '@uid' => $account->id(),
        '@type' => $type,
      ]);
    }
  }

}
