<?php

namespace Drupal\aclib_communico\Plugin\QueueWorker;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\node\NodeInterface;

use Drupal\aclib_communico\AclibCommunicoClient;

/**
 * Unpublish nodes that are not anymore on Communico.
 *
 * @QueueWorker(
 *   id = "aclib_communico_queue_unpublish",
 *   title = @Translation("Aclib Communico Unpublish Queue Worker"),
 *   cron = {"time" = 10}
 * )
 */
class AclibCommunicoQueueUnpublish extends AclibCommunicoQueueWorker {
  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if ($data instanceof NodeInterface) { // Double check in here for node object
      try {

        $data->set('status', 0);
        if ($data->save()) {
          $status = $this->t('Existing node unpublished: @title ', ['@title' => $data->getTitle()]);
          $this->logger->get('aclib_communico')->notice($status);
        }
      }
      catch (\Exception $e) {
        $error = $this->t('Unpublishing Communico event node @title failed on cron run: @message', ['@title' => $data->getTitle(), '@message' => $e->getMessage()]);
        $this->logger->get('aclib_communico')->error($error);
      }
    }
  }
} 