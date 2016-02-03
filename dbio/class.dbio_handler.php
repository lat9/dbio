<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
  
}

abstract class dbio_handler extends base {
  function __construct (array $languages) {
    $this->languages = $languages;
    $this->_initialize ();
    
  }
  
  // -----
  // Return the language-specific description for the handler.
  //
  function get_handler_description ($language = 'en') {
    return (isset ($this->config) && isset ($this->config['description']) && isset ($this->config['description'][$language])) ? $this->config['description'][$language] : DBIO_TEXT_NO_DESCRIPTION;
    
  }
  
  // -----
  // Return any message (usually an error or warning) from the last action performed by the handler.
  //
  function get_handler_message () {
    return $this->message;
    
  }
  
  // -----
  // Initialize the dbIO export handling.
  //
  function export_initialize ($language = 'all') {
    if (!isset ($this->config)) {
      trigger_error ('Export aborted: dbIO helper not configured.', E_USER_ERROR);
      exit ();
      
    }
    $this->message = '';
    if ($language == 'all' && count ($this->languages) == 1) {
      reset ($this->languages);
      $language = key ($this->languages);
      
    }
    $this->export_language = $language;
    $this->export = array ();
    $this->export['select'] = $this->export['from'] = $this->export['where'] = $this->export['order_by'] = '';
    $this->export['headers'] = array ();
    $this->export['delimiter'] = DBIO_CSV_DELIMITER;
    $this->export['enclosure'] = DBIO_CSV_ENCLOSURE;
    $this->export['escape'] = DBIO_CSV_ESCAPE;
    foreach ($this->config['tables'] as $config_table_name => $config_table_info) {
      $this->export['from'] .= $config_table_name;
      $table_prefix = (isset ($config_table_info['short_name']) && $config_table_info['short_name'] != '') ? ($config_table_info['short_name'] . '.') : '';
      if ($table_prefix != '') {
        $this->export['from'] .= ' AS ' . $config_table_info['short_name'] . ', ';
        
      }
      if ($this->tables[$config_table_name]['uses_language']) {
        $first_language = true;
        $this->export[$config_table_name]['select'] = '';
        foreach ($this->languages as $language_code => $language_id) {
          foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
            if ($field_info['include_in_export'] !== false) {
              if ($first_language) {
                $this->export['select'] .= "$table_prefix$current_field, ";
                $this->export[$config_table_name]['select'] .= "$current_field, ";
                
              }
              if ($field_info['include_in_export'] === true) {
                $this->export['headers'][] = 'v_' . $current_field . '_' . $language_id;
                
              }
            }
          }
          $first_language = false;
        }
        $this->export[$config_table_name]['select'] = substr ($this->export[$config_table_name]['select'], 0, -2);
        
      } else {
        foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
          if ($field_info['include_in_export'] !== false) {
            $this->export['select'] .= "$table_prefix$current_field, ";
            if ($field_info['include_in_export'] === true) {
              $this->export['headers'][] = 'v_' . $current_field;
              
            }
          }
        }
      }
    }
    $this->export['from'] = ($this->export['from'] == '') ? '' : substr ($this->export['from'], 0, -2);
    $this->export['select'] = ($this->export['select'] == '') ? '' : substr ($this->export['select'], 0, -2);
    
  }
  
  function export_get_header () {
    $header = false;
    if ($this->config['include_header'] == true) {
      $header = $this->export['headers'];
      
    }
    return $header;
    
  }
  
  function export_prepare_fields (array $fields) {
    return $fields;
    
  }
  
  function import_initialize ($language = 'all') {
    if (!isset ($this->config)) {
      trigger_error ('Import aborted: dbIO helper not configured.', E_USER_ERROR);
      exit ();
      
    }
    $this->message = '';
    if ($language == 'all' && count ($this->languages) == 1) {
      reset ($this->languages);
      $language = key ($this->languages);
      
    }
    $this->import['delimiter'] = DBIO_CSV_DELIMITER;
    $this->import['enclosure'] = DBIO_CSV_ENCLOSURE;
    $this->import['escape'] = DBIO_CSV_ESCAPE;
    
    $this->import['headers'] = array ();
    foreach ($this->config['tables'] as $config_table_name => $config_table_info) {
      $is_language_table = $this->tables[$config_table_name]['uses_language'];
      foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
        if ($field_info['include_in_export']) {
          if (DBIO_USE_LANGUAGE_SUFFIX == 'true' && !$this->config['include_header'] && $is_language_table) {
            if ($language != 'all') {
              $this->import['headers'][] = $current_field . '_' . $language;
              
            } else {
              foreach ($this->languages as $language_code => $language_id) {
                $this->import['headers'][] = $current_field . '_' . $language_code;
                
              }
            }
          } else {
            $this->import['headers'][] = $current_field;
            
          }
        }
      }
    }
    return $this->import['headers'];
    
  }
  
  function import_csv_record (array $data, $is_header = false) {
    return $data;
    
  }
  
// ----------------------------------------------------------------------------------
//                         P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------
  
  protected function export_get_sql ($sql_limit = '') {
    if (!isset ($this->export_language) || !isset ($this->export['select'])) {
      trigger_error ('Export aborted: dbIO export sequence error; not previously initialized.', E_USER_ERROR);
      exit ();
      
    }
    $export_sql = 'SELECT ' . $this->export['select'] . ' FROM ' . $this->export['from'];
    if ($this->export['where'] != '') {
      $export_sql .= ' WHERE ' . $this->export['where'];
      
    }
    if ($this->export['order_by'] != '') {
      $export_sql .= ' ORDER BY ' . $this->export['order_by'];
      
    }
    return $export_sql . " $sql_limit";
    
  }
  
  protected function _initialize () {
    $this->message = '';
    
    if (!isset ($this->config) || !isset ($this->config['tables']) || !is_array ($this->config['tables'])) {
      trigger_error ('dbIO configuration not set prior to _initialize.  Current class: ' . var_export ($this, true), E_USER_ERROR);
      exit();
      
    }
    
    if (!isset ($this->config['include_header'])) {
      $this->config['include_header'] = true;
      
    }

    $this->tables = array ();
    
    foreach ($this->config['tables'] as $table_name => $table_info) {
      $this->_initialize_table_fields ($table_name);
      $this->_initialize_sql_inputs ($table_name);
      
    }
  }
  
  // -----
  // Function, available for use by all helpers, to gather the pertinent bits of information about the specified table.
  //
  // NOTE: There's an override here for the products_description.products_id field.  It's marked (up to Zen Cart v1.5.5)
  // as being an auto-increment field, which has "special" interpretation by the dbIO processing.  If that field is found
  // within the processing, simply mark it as non-auto-increment; ZC1.5.5 and later already do this.
  //
  protected function _initialize_table_fields ($table_name) {
    global $db;
    $this->tables[$table_name] = array ();
    $this->tables[$table_name]['fields'] = array ();
    
    $sql_query = "
         SELECT COLUMN_NAME as `column_name`, COLUMN_DEFAULT as `default`, IS_NULLABLE as `nullable`, DATA_TYPE as `data_type`, 
                CHARACTER_MAXIMUM_LENGTH as `max_length`, NUMERIC_PRECISION as `precision`, COLUMN_KEY as `key`, EXTRA as `extra`
           FROM INFORMATION_SCHEMA.COLUMNS 
          WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' 
            AND TABLE_NAME = '$table_name'
       ORDER BY ORDINAL_POSITION";
    $table_info = $db->Execute ($sql_query);
    while (!$table_info->EOF) {
      $column_name = $table_info->fields['column_name'];
      if ("$table_name.$column_name" == TABLE_PRODUCTS_DESCRIPTION . '.products_id') {
        $table_info->fields['extra'] = '';
        
      }
      unset ($table_info->fields['column_name']);
      $table_info->fields['include_in_export'] = true;
      $table_info->fields['nullable'] = ($table_info->fields['nullable'] === 'TRUE');
      $table_info->fields['sort_order'] = 0;
      $this->tables[$table_name]['fields'][$column_name] = $table_info->fields;
      
      $table_info->MoveNext ();
      
    }   
  }
  
  protected function _initialize_sql_inputs ($table_name) {
    global $db;
    $this->tables[$table_name]['sql_update'] = array ();
    $this->tables[$table_name]['sql_insert'] = array ();
    
    $indexes_info = $db->Execute ("SHOW indexes IN $table_name WHERE Key_name = 'PRIMARY'");
    $table_indexes = '';
    while (!$indexes_info->EOF) {
      $table_indexes .= ($indexes_info->fields['Column_name'] . ',');
      $indexes_info->MoveNext ();
      
    }
    unset ($indexes_info);

    $required_fields = '';
    $uses_language = false;
    $insert_data_array = array ();
    $update_data_array = array ();
    foreach ($this->tables[$table_name]['fields'] as $field_name => $field_info) {
      if ($field_name == 'language_id' || $field_name == 'languages_id') {
        $uses_language = true;
        
      }
      if (strpos ($table_indexes, $field_name . ',') === false || $field_info['extra'] != 'auto_increment') {
        if (!$field_info['nullable'] && $field_info['default'] === NULL) {
          $required_fields .= ($field_name . ',');
          $insert_data_array[$field_name] = NULL;

        }
      }
      if (isset ($this->config['tables'][$table_name]['insert_now']) && $this->config['tables'][$table_name]['insert_now'] == $field_name) {
        $insert_data_array[$field_name] = 'now()';
        
      }
      if (isset ($this->config['tables'][$table_name]['update_now']) && $this->config['tables'][$table_name]['update_now'] == $field_name) {
        $update_data_array[$field_name] = 'now()';
        
      }
    }
    $this->tables[$table_name]['uses_language'] = $uses_language;
    
    $this->tables[$table_name]['sql_insert']['table_indexes'] = $table_indexes;
    $this->tables[$table_name]['sql_insert']['required_fields'] = $required_fields;
    $this->tables[$table_name]['sql_insert']['data'] = $insert_data_array;
    
    $this->tables[$table_name]['sql_update']['table_indexes'] = $table_indexes;
    $this->tables[$table_name]['sql_update']['required_fields'] = $required_fields;
    $this->tables[$table_name]['sql_update']['data'] = $update_data_array;   
    
  }

}