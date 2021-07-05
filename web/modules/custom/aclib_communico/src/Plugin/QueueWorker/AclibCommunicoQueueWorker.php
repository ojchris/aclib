<?php

namespace Drupal\aclib_communico\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

use Drupal\aclib_communico\AclibCommunicoException;

/**
 * Fetches data from Communico and creates appropriate nodes.
 *
 * @QueueWorker(
 *   id = "aclib_communico_queue",
 *   title = @Translation("Aclib Communico Queue Worker"),
 *   cron = {"time" = 10}
 * )
 */
class AclibCommunicoQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  
  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerChannelFactory;
  
  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerChannelFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerChannelFactory = $loggerChannelFactory;
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    
    try {
      if ($this->entityTypeManager->getStorage('node')->create($data)->save()) {
        $this->loggerChannelFactory->get('aclib_communico')->notice('A new node imported via Communico API; @title', ['@title' => $data['title']]);
      }
      else {
        throw new AclibCommunicoException('Failed creating event: @title', ['@title' => $data['title']]);
      }
    }
    catch (AclibCommunicoException $e) {
      $message = 'Failed creating event: ' . $e->getMessage();
      throw new AclibCommunicoException($message, $e->getCode(), $e);
    }
  }
}