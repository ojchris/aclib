<?php

namespace Drupal\aclib_refdb\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the aclib_refdb_logs entity.
 *
 * @ingroup aclib_refdb
 *
 * @ContentEntityType(
 *   id = "aclib_refdb_logs",
 *   label = @Translation("ACLIB Ref DB logs"),
 *   base_table = "aclib_refdb_logs",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   }
 * )
 */
class AclibRefdbLogs extends ContentEntityBase implements ContentEntityInterface {

  /**
   *
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Id'))
      ->setDescription(t('Primary identifier for reference db logs table.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('Uuid'))
      ->setDescription(t('UUID identifier for reference db logs table.'))
      ->setReadOnly(TRUE);

    // The other fields.
    $fields['nid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Nid'))
      ->setDescription(t('Identifier for a related node.'))
      ->setReadOnly(TRUE);

    $fields['location'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Location'))
      ->setDescription(t('Internal or external visit.'))
      ->setReadOnly(TRUE);

    $fields['datetime'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Date'))
      ->setDescription(t('Ref DB access date and time.'))
      ->setReadOnly(TRUE);

    $fields['remote_addr'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Remote address'))
      ->setDescription(t('IP address of the reference DB visitor.'))
      ->setReadOnly(TRUE);

    $fields['pattern_matched'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Pattern matched'))
      ->setDescription(t('The library card pattern that was matched.'))
      ->setReadOnly(TRUE);

    return $fields;
  }

}
