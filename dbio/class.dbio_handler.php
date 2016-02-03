<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
  
}

// -----
// These definitions will, eventually, be migrated to language files.
//
define ('DBIO_MESSAGE_IMPORT_MISSING_HEADER', 'Import aborted: Missing header information for input file.');
define ('DBIO_FORMAT_MESSAGE_IMPORT_MISSING_KEY', 'Import aborted: Missing key column (%s).');
define ('DBIO_NO_IMPORT', '--none--');

abstract class dbio_handler extends base {
  function __construct ($log_file_suffix, array $languages) {
    $this->languages = $languages;
    
    $this->debug = (DBIO_DEBUG == 'true');
    $this->debug_log_file = DIR_FS_LOGS . '/dbio-' . $log_file_suffix . '.log';
    
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
  
  function import_initialize ($language = 'all', $operation = 'check') {
    if (!isset ($this->config)) {
      trigger_error ('Import aborted: dbIO helper not configured.', E_USER_ERROR);
      exit ();
      
    }
    $this->message = '';
    if ($language == 'all' && count ($this->languages) == 1) {
      reset ($this->languages);
      $language = key ($this->languages);
      
    }
    $this->import = array ();
    $this->import['delimiter'] = DBIO_CSV_DELIMITER;
    $this->import['enclosure'] = DBIO_CSV_ENCLOSURE;
    $this->import['escape'] = DBIO_CSV_ESCAPE;
    $this->import['operation'] = $operation;
    $this->import['record_count'] = ($this->config['include_header']) ? 1 : 0;
    
    $this->import['headers'] = array ();
    foreach ($this->config['tables'] as $config_table_name => $config_table_info) {
      $is_language_table = $this->tables[$config_table_name]['uses_language'];
      foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
        if ($field_info['include_in_export'] === true) {
          if (!$this->config['include_header'] && $is_language_table) {
            if ($language != 'all') {
              $this->import['headers'][] = 'v_' . $current_field . '_' . $this->languages[$language];
              
            } else {
              foreach ($this->languages as $language_code => $language_id) {
                $this->import['headers'][] = 'v_' . $current_field . '_' . $language_id;
                
              }
            }
          } else {
            $this->import['headers'][] = 'v_' . $current_field;
            
          }
        }
      }
    }
    return $this->import['headers'];
    
  }
  
  function import_get_header ($header) {
    if (!isset ($this->import) || !isset ($this->import['headers'])) {
      trigger_error ("Import aborted, sequencing error. Can't get the header before overall initialization.", E_USER_ERROR);
      exit ();
      
    }
    if (!is_array ($header)) {
      $this->debug_message ('import_get_header: No header included, using generated default.');
      $header = $this->import['headers'];
      
    }
    $this->debug_message ("import_get_header, using headers:\n" . var_export ($header, true));
    $this->import['table_names'] = array ();
    $this->import['language_id'] = array ();
    $this->import['header_field_count'] = 0;
    $this->import['key_index'] = false;
    $key_index = 0;
    foreach ($header as &$current_field) {
      $table_name = DBIO_NO_IMPORT;
      $field_language_id = 0;
      if (strpos ($current_field, 'v_') !== 0) {
        $current_field = DBIO_NO_IMPORT;
        
      } else {
        $current_field = $this->import_field_check (substr ($current_field, 2));
        if ($current_field != DBIO_NO_IMPORT) {
          foreach ($this->tables as $database_table_name => $table_info) {
            if ($table_info['uses_language']) {
              foreach ($this->languages as $language_code => $language_id) {
                $language_suffix = '_' . (int)$language_id;
                $field_name = (strlen ($current_field) > strlen ($language_suffix)) ? substr ($current_field, 0, -strlen ($language_suffix)) : DBIO_NO_IMPORT;
                if (array_key_exists ($field_name, $table_info['fields'])) {
                  $table_name = $database_table_name;
                  $field_language_id = $language_id;
                  $current_field = $field_name;
                  break;
                  
                }
              }
              if ($table_name != DBIO_NO_IMPORT) {
                break;
                
              }
            } elseif (array_key_exists ($current_field, $table_info['fields'])) {
              $table_name = $database_table_name;
              break;
              
            }
          }
        }
        if ($this->config['key']['table'] == $table_name && $this->config['key']['match_field'] == $current_field) {
          $this->import['key_index'] = $key_index;
          
        }
      }
      $this->import['table_names'][] = $table_name;
      $this->import['language_id'][] = $field_language_id;
      if ($current_field != DBIO_NO_IMPORT) {
        $this->import['header_field_count']++;
        
      }  
      $key_index++;
      
    }
    $this->import['headers'] = $header;
    $this->import['header_columns'] = count ($header);
    
    $initialization_complete = true;
    if ($this->import['header_field_count'] == 0) {
      $this->message = DBIO_MESSAGE_IMPORT_MISSING_HEADER;
      $initialization_complete = false;
      
    } elseif ($this->import['key_index'] === false) {
      $this->message = sprintf (DBIO_FORMAT_MESSAGE_IMPORT_MISSING_KEY, $this->config['key']['match_field']);
      $initialization_complete = false;
      
    }
    if (!$initialization_complete) {
      unset ($this->import['table_names']);
      
    }
    return $initialization_complete;
    
  }
  
  function import_csv_record (array $data) {
    global $db;
    if (!isset ($this->import['table_names'])) {
      trigger_error ("Import aborted, sequencing error. Previous import-header initialization error.", E_USER_ERROR);
      exit ();
      
    }
    $this->import['record_count']++;
    $key_index = $this->import['key_index'];
    if (count ($data) < $key_index) {
      $this->log_message ('Data record at line #' . $this->import['record_count'] . ' not imported.  Column count (' . count ($data) . ') missing key column (' . $key_index . ').');
      
    } else {
      $data_key_check = $db->Execute ("SELECT " . $this->config['key']['key_field'] . " as key_value 
                                         FROM " . $this->config['key']['table'] . " 
                                        WHERE " . $this->config['key']['match_field'] . " = '" . $db->prepare_input ($data[$key_index]) . "' LIMIT 1");
      if ($data_key_check->EOF) {
        $this->import['action'] = 'insert';
        $this->import['where_clause'] = '';
        
      } else {
        $this->import['action'] = 'update';
        $this->import['where_clause'] = $db->bindVars ($this->config['key']['key_field'] . ' = :key_value:', ':key_value:', $data_key_check->fields['key_value'], $this->config['key']['key_field_type']);
        
      }
      
      $data_index = 0;
      $this->import_sql_data = array ();
      foreach ($data as $current_element) {
        if ($data_index > $this->import['header_columns']) {
          break;
          
        }
        $field_name = $this->import['headers'][$data_index];
        $table_name = $this->import['table_names'][$data_index];
        $language_id = $this->import['language_id'][$data_index];
        $data_index++;
        
        if ($field_name == DBIO_NO_IMPORT) {
          continue;
          
        }
        $this->process_import_field ($table_name, $field_name, $language_id, $current_element);

      }

      if ($this->import_finalize_fields ($data) !== false) {
        $key_value = false;
        foreach ($this->import_sql_data as $database_table => $sql_data_array) {
          if ($database_table != DBIO_NO_IMPORT) {
            $table_name = $database_table;
            $where_clause = $this->import['where_clause'];
            $capture_key_value = ($this->import['action'] == 'insert' && $this->config['key']['table'] == $table_name);

            if (strpos ($table_name, '^') !== false) {
              $language_tables = explode ('^', $table_name);
              $table_name = $language_tables[0];
              $language_id = $language_tables[1];
              if ($this->import['action'] == 'update') {
                $where_clause .= " AND language_id = $language_id";
                
              }
            }
            
            if ($this->import['operation'] == 'check') {
              $this->debug_message ("SQL for $table_name:\n" . $db->perform ($table_name, $sql_data_array, $this->import['action'], $where_clause, true));
              
            } else {
              $this->debug_message ("Performing database " . $this->import['action'] . " for $table_name with where_clause = '$where_clause':\n" . var_export ($sql_data_array, true));
              $db->perform ($table_name, $sql_data_array, $this->import['action'], $where_clause);
              
            }
          }
        }
      }
    }
  }
  
  function debug_message ($message) {
    if ($this->debug) {
      error_log ($message . "\n", 3, $this->debug_log_file);
      
    }
  }
  
// ----------------------------------------------------------------------------------
//                P R O T E C T E D / I N T E R N A L   F U N C T I O N S 
// ----------------------------------------------------------------------------------

  protected function import_finalize_fields ($data) {
    return true;
  }
  
  protected function add_import_field ($table_name, $field_name, $field_value, $field_type) {
    if (!isset ($this->import_sql_data[$table_name])) {
      $this->import_sql_data[$table_name] = array ();
      
    }
    $this->import_sql_data[$table_name][] = array ( 'fieldName' => $field_name, 'value' => $field_value, 'type' => $field_type );
    
  }
  
  protected function process_import_field ($table_name, $field_name, $language_id, $field_value, $field_type = false) {
    if ($language_id == 0) {
      $import_table_name = $table_name;
      if (!isset ($this->import_sql_data[$table_name])) {
        $this->import_sql_data[$table_name] = array ();
        
      }
    } else {
      $import_table_name = "$table_name^$language_id";
      if (!isset ($this->import_sql_data[$import_table_name])) {
        $this->import_sql_data[$import_table_name] = array ();
        
      }
    }
    if ($field_type === false) {
      if (isset ($this->tables[$table_name]) && isset ($this->tables[$table_name]['fields'][$field_name])) {
        switch ($this->tables[$table_name]['fields'][$field_name]['data_type']) {
          case 'int':
          case 'tinyint': {
            $field_type = 'integer';
            break;
          }
          case 'char':
          case 'varchar':
          case 'mediumtext': {
            $field_type = 'string';
            break;
          }
          case 'float':
          case 'decimal': {
            $field_type = 'float';
            break;
          }
          case 'date':
          case 'datetime': {
            $field_type = 'date';
            break;
          }
          default: {
            $field_type = 'string';
            trigger_error ("Unknown datatype (" . $this->tables[$table_name]['fields'][$field_name]['data_type'] . ") for $table_name::$field_name", E_USER_WARNING);
            break;
          }
        }
      }
    }
    $this->add_import_field ($import_table_name, $field_name, $field_value, $field_type);
    
  }
  
  protected function import_field_check ($field_name) {
    return $field_name;
    
  }
  
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
    
    $this->config['operation'] = NULL;
    
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
/*
      if (strpos ($table_indexes, $field_name . ',') === false || $field_info['extra'] != 'auto_increment') {
        if (!$field_info['nullable'] && $field_info['default'] === NULL) {
          $required_fields .= ($field_name . ',');
          $insert_data_array[$field_name] = NULL;

        }
      }
*/
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