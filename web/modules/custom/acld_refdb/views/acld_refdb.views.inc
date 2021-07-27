<?php

/**
 * @file
 */

/**
 * Implementation of hook_views_data().
 */
function acld_refdb_views_data() {
  $data['refdb_access_log']['table']['group'] = t('Reference DB');
  $data['refdb_access_log']['table']['join'] = [
    'node' => [
      'left_field' => 'nid',
      'field' => 'nid',
      'type' => 'INNER',
    ],
  ];
  $data['refdb_access_log']['access_count_internal'] = [
    'title' => t('Access count - internal'),
    'help' => t('The number of times an ACLD Reference Database has been accessed internally.'),
    'field' => [
      'handler' => 'acld_refdb_handler_field_refdbcount_internal',
      'click sortable' => TRUE,
    ],
  ];
  $data['refdb_access_log']['access_count_external'] = [
    'title' => t('Access count - external'),
    'help' => t('The number of times an ACLD Reference Database has been accessed externally.'),
    'field' => [
      'handler' => 'acld_refdb_handler_field_refdbcount_external',
      'click sortable' => TRUE,
    ],
  ];
  $data['refdb_access_log']['access_count_overall'] = [
    'title' => t('Access count - overall'),
    'help' => t('The number of times an ACLD Reference Database has been accessed overall.'),
    'field' => [
      'handler' => 'acld_refdb_handler_field_refdbcount_overall',
      'click sortable' => TRUE,
    ],
  ];
  $data['refdb_access_log']['access_count_pattern_1'] = [
    'title' => t('Access count - card pattern 1'),
    'help' => t('The number of times an ACLD Reference Database has been accessed by library card pattern 1.'),
    'field' => [
      'handler' => 'acld_refdb_handler_field_refdbcount_pattern_1',
      'click sortable' => TRUE,
    ],
  ];
  $data['refdb_access_log']['access_count_pattern_2'] = [
    'title' => t('Access count - card pattern 2'),
    'help' => t('The number of times an ACLD Reference Database has been accessed by library card pattern 2.'),
    'field' => [
      'handler' => 'acld_refdb_handler_field_refdbcount_pattern_2',
      'click sortable' => TRUE,
    ],
  ];
  $data['refdb_access_log']['timestamp'] = [
    'title' => t('Between dates (YYYY-MM-DD)'),
    'help' => t('Timestamp for reference database logs, filtering.'),
    'field' => [
      'handler' => 'views_handler_field_date',
      'click sortable' => TRUE,
    ],
    'filter' => [
      'label' => t('Reference DB Timestamp'),
      'handler' => 'views_handler_filter_date',
      // 'type' => 'open-closed',
    ],
    'sort' => [
      'handler' => 'views_handler_sort',
    ],
  ];
  return $data;
}
