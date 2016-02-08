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

abstract class DbIoHandler extends base 
{
// ----------------------------------------------------------------------------------
//                                    C O N S T A N T S 
// ----------------------------------------------------------------------------------
    // ----- Interface Constants -----
    const DBIO_HANDLER_VERSION  = '0.0.0';
    // ----- Message Severity -----
    const DBIO_WARNING           = 1;
    const DBIO_ERROR             = 2;
    // ----- Handler configuration bit switches -----
    const DBIO_FLAG_NONE         = 0;       //- No special handling
    const DBIO_FLAG_PER_LANGUAGE = 1;       //- Field is handled once per language
// ----------------------------------------------------------------------------------
//                             P U B L I C   F U N C T I O N S 
// ----------------------------------------------------------------------------------
    public function __construct ($log_file_suffix) 
    {
        $this->debug = (DBIO_DEBUG == 'true');
        $this->debug_log_file = DIR_FS_LOGS . '/dbio-' . $log_file_suffix . '.log';
        
        $this->stats = array ( 'errors' => 0, 'warnings' => 0, 'record_count' => 0, 'inserts' => 0, 'updates' => 0, 'start_time' => 0, 'stop_time' => 0 );
        
        $this->debugMessage ('Configured CHARSET (' . CHARSET . '), DB_CHARSET (' . DB_CHARSET . '), DBIO_CHARSET (' . DBIO_CHARSET . '), PHP multi-byte settings: ' . var_export (mb_get_info (), true));
        
        $this->languages = array ();
        if (!class_exists ('language')) {
            require (DIR_WS_CLASSES . 'language.php');
          
        }
        $languages = new language;
        foreach ($languages->catalog_languages as $iso_code_2 => $language_info) {
            $this->languages[$iso_code_2] = $language_info['id'];
          
        }
        unset ($languages);
        
        $this->setHandlerConfiguration ();
        
        $this->initialize ();
    }

    // -----
    // Set the current time into the process's start time.
    //
    public function startTimer () 
    {
        $this->stats['start_time'] = microtime (true);
    }
  
    // -----
    // Set the current time into the process's stop time.
    //
    public function stopTimer () 
    {
        $this->stats['stop_time'] = microtime (true);
    }
  
    // -----
    // Get the script's parse time, returned as a floating-point value identifying the number of seconds (a floating-point value).  The value is
    // returned as 0 if the timer was either not started or not stopped.
    //
    public function getParseTime () 
    {
        return ($this->stats['start_time'] === 0 || $this->stats['stop_time'] === 0) ? 0 : ($this->stats['stop_time'] - $this->stats['start_time']);
    }
  
    // -----
    // Return the language-specific description for the handler.
    //
    public function getHandlerDescription ($language = 'en') 
    {
        return (isset ($this->config) && isset ($this->config['description']) && isset ($this->config['description'][$language])) ? $this->config['description'][$language] : sprintf (DBIO_FORMAT_TEXT_NO_DESCRIPTION, $language);
    }
  
    // -----
    // Return any message (usually an error or warning) from the last action performed by the handler.
    //
    public function getHandlerMessage () 
    {
        return $this->message;
    }
    
    // -----
    // Return the indication as to whether the dbIO action is expected to include a header record.
    //
    public function isHeaderIncluded ()
    {
        return $this->config['include_header'];
    }
  
  
    // -----
    // Return the current CSV parameters.
    //
    public function getCsvParameters () 
    {
        return array ( 'delimiter' => $this->config['delimiter'], 'enclosure' => $this->config['enclosure'], 'escape' => $this->config['escape'] );
    }
    
    // -----
    // Writes the requested message to the current debug-log file, if debug is enabled.
    //
    public function debugMessage ($message, $severity = 0) 
    {
        if ($this->debug) {
            error_log (date (DBIO_DEBUG_DATE_FORMAT) . ": $message\n", 3, $this->debug_log_file);
      
        }
        switch ($severity) {
            case self::DBIO_WARNING:
                $this->stats['warnings']++;
                trigger_error ($message, E_USER_WARNING);
                break;
            case self::DBIO_ERROR:
                $this->stats['errors']++;
                trigger_error ($message, E_USER_ERROR);
                break;
            default:
                break;
        }
    }
    
    // -----
    // Initialize the dbIO export handling.
    //
    public function exportInitialize ($language = 'all') 
    {
        $initialized = false;
        if ($language == 'all' && count ($this->languages) == 1) {
            reset ($this->languages);
            $language = key ($this->languages);
        }
        $this->message = '';
        
        if (!isset ($this->config)) {
            $this->message = DBIO_ERROR_NO_HANDLER;
            trigger_error ($this->message, E_USER_ERROR);
            
        } elseif ($language != 'all' && !isset ($this->languages[$language])) {
            $this->message = sprintf (DBIO_ERROR_EXPORT_NO_LANGUAGE, $language);
            
        } else {
            $initialized = true;
            $this->export_language = $language;
            $this->export = array ();
            $this->export['select'] = $this->export['from'] = $this->export['where'] = $this->export['order_by'] = '';
            $this->export['headers'] = array ();
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
                        if ($language !== 'all' && $language != $language_code) {
                            continue;
                        }
                        foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
                            if ($field_info['include_in_export'] !== false) {
                                if ($first_language) {
                                    $this->export['select'] .= "$table_prefix$current_field, ";
                                    $this->export[$config_table_name]['select'] .= "$current_field, ";
                        
                                }
                                if ($field_info['include_in_export'] === true) {
                                    $this->export['headers'][] = 'v_' . $current_field . '_' . $language_code;
                        
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
            
            if (isset ($this->config['additional_headers']) && is_array ($this->config['additional_headers'])) {
                foreach ($this->config['additional_headers'] as $header_value => $flags) {
                    if ($flags & self::DBIO_FLAG_PER_LANGUAGE) {
                        foreach ($this->languages as $language_code => $language_id) {
                            $this->export['headers'][] = $header_value . '_' . $language_code;
                        }
                    } else {
                        $this->export['headers'][] = $header_value;
                    }
                }
            }
        }
        $this->debugMessage ("exportInitialize ($language), " . (($initialized) ? 'Successful' : ('Unsuccessful (' . $this->message . ')')));
        return $initialized;
    }
  
    public function exportGetHeader () 
    {
        $header = false;
        if ($this->config['include_header'] == true) {
            $this->stats['record_count']++;
            $header = $this->export['headers'];
      
        }
        return $header;
    
    }
 
    public function exportGetSql ($sql_limit = '') 
    {
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
  
    public function exportPrepareFields (array $fields) 
    {
        $this->stats['record_count']++;
        return $fields;
    
    }
  
    public function importInitialize ($language = 'all', $operation = 'check') 
    {
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
        $this->import['operation'] = $operation;
        $this->import['check_values'] = ($operation == 'check' || $operation == 'run-check');
        $this->stats['record_count'] = ($this->config['include_header']) ? 1 : 0;
        
        $this->import['headers'] = array ();
        foreach ($this->config['tables'] as $config_table_name => $config_table_info) {
            if ($this->tables[$config_table_name]['uses_language']) {
                foreach ($this->languages as $language_code => $language_id) {
                    if ($language !== 'all' && $language != $language_code) {
                        continue;
                    }
                    foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
                        if ($field_info['include_in_export'] === true) {
                            $this->import['headers'][] = 'v_' . $current_field . '_' . $language_code;
                        }
                    }
                }
            } else {
                foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
                    if ($field_info['include_in_export'] === true) {
                        $this->import['headers'][] = 'v_' . $current_field;
                    }
                }
            }
        }
            
        if (isset ($this->config['additional_headers']) && is_array ($this->config['additional_headers'])) {
            foreach ($this->config['additional_headers'] as $header_value => $flags) {
                if ($flags & self::DBIO_FLAG_PER_LANGUAGE) {
                    foreach ($this->languages as $language_code => $language_id) {
                        $this->import['headers'][] = $header_value . '_' . $language_code;
                    }
                } else {
                    $this->import['headers'][] = $header_value;
                }
            }
        }
        return $this->import['headers'];
        
    }
  
    public function importGetHeader ($header) 
    {
        if (!isset ($this->import) || !isset ($this->import['headers'])) {
            trigger_error ("Import aborted, sequencing error. Can't get the header before overall initialization.", E_USER_ERROR);
            exit ();
        }
        if (!is_array ($header)) {
            $this->debugMessage ('import_get_header: No header included, using generated default.');
            $header = $this->import['headers'];
          
        }
        $this->debugMessage ("import_get_header, using headers:\n" . var_export ($header, true));
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
                $current_field = $this->importFieldCheck (substr ($current_field, 2));
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
  
    // -----
    // This function is the heart of the dbIO-Import handling, processing the current CSV record into the store's database.
    //
    public function importCsvRecord (array $data) 
    {
        global $db;
        if (!isset ($this->import['table_names'])) {
            trigger_error ("Import aborted, sequencing error. Previous import-header initialization error.", E_USER_ERROR);
            exit ();
        }
        // -----
        // Indicate, initially, that the record is OK to import and increment the count of lines processed.  The last value
        // will be used in any debug-log information to note the location of any processing.
        //
        $this->import['record_ok'] = true;
        $this->stats['record_count']++;
        
        // -----
        // Determine the "key" value associated with the record.  If there are fewer columns of data than required to access
        // the key-index, the record is not imported.
        //
        $key_index = $this->import['key_index'];
        if (count ($data) < $key_index) {
            $this->debugMessage ('Data record at line #' . $this->import['record_count'] . ' not imported.  Column count (' . count ($data) . ') missing key column (' . $key_index . ').', self::DBIO_ERROR);
          
        } else {
            // -----
            // See if a record matching the field-to-match exists.  If there is one, we'll be updating the associated record;
            // otherwise, we'll be inserting.
            //      
            $data_key_check = $db->Execute ("SELECT " . $this->config['key']['key_field'] . " as key_value 
                                               FROM " . $this->config['key']['table'] . " 
                                               WHERE " . $this->config['key']['match_field'] . " = '" . $db->prepare_input ($data[$key_index]) . "' LIMIT 1");
            if ($data_key_check->EOF) {
                $this->import['action'] = 'insert';
                $this->import['where_clause'] = '';
                $this->stats['inserts']++;
                $key_value = false;
            
            } else {
                $this->import['action'] = 'update';
                $this->stats['updates']++;
                $key_value = $data_key_check->fields['key_value'];
                $this->import['where_clause'] = $db->bindVars ($this->config['key']['key_field'] . ' = :key_value:', ':key_value:', $key_value, $this->config['key']['key_field_type']);
            
            }
          
            // -----
            // Loop, processing each 'column' of data into its respective database field(s).  At the end of this processing,
            // we'll have a couple of sql-data arrays to be used as input to the database 'perform' function; that function will
            // handle any conversions and quoting required.
            //
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
                $this->importProcessField ($table_name, $field_name, $language_id, $current_element);

            }

            // -----
            // If the record didn't have errors preventing its insert ...
            //
            if ($this->importFinalizeFields ($data) !== false) {
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
                    
                            } else {
                                $sql_data_array[] = array ( 'fieldName' => $this->config['tables'][$table_name]['language_field'], 'value' => $language_id, 'type' => 'integer' );
                    
                            }
                        }
                
                        if ($this->import['operation'] == 'check') {
                            $this->debugMessage ("SQL for $table_name:\n" . $db->perform ($table_name, $sql_data_array, $this->import['action'], $where_clause, 'return') . "\n");
                  
                        } else {
                            $sql_data_array = $this->importUpdateRecordKey ($table_name, $sql_data_array, $key_value);
                            if ($sql_data_array !== false) {
                                $this->debugMessage ("Performing database " . $this->import['action'] . " for $table_name with where_clause = '$where_clause':\n" . $this->prettify ($sql_data_array) . "\n");
                                $db->perform ($table_name, $sql_data_array, $this->import['action'], $where_clause);
                                if ($capture_key_value) {
                                    $key_value = $db->insert_ID ();
                      
                                }
                            }
                        }
                    }
                }
            }
        }
    }
  
// ----------------------------------------------------------------------------------
//                      P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------
    
    // -----
    // This abstract function, required to be supplied for the actual handler, is called during the class
    // construction to have the handler set its database-related configuration.
    //
    abstract protected function setHandlerConfiguration ();
  
    // -----
    // Local function (used when logging messages to reduce unnecessary whitespace.
    //
    protected function prettify (array $data) 
    {
        return str_replace (array ("',\n", "=> \n", "array (\n", "0,\n", '  '), array("',", "=> ", "array (", "0,", ' '), var_export ($data, true));
    }

    protected function importFinalizeFields ($data) 
    {
        return $this->import['record_ok'];
    }
  
    protected function importUpdateRecordKey ($table_name, $sql_data_array, $key_value) 
    {
        return $sql_data_array;
    }
  
    protected function importAddField ($table_name, $field_name, $field_value, $field_type) 
    {
        if (!isset ($this->import_sql_data[$table_name])) {
            $this->import_sql_data[$table_name] = array ();
        }
        $this->import_sql_data[$table_name][] = array ( 'fieldName' => $field_name, 'value' => $field_value, 'type' => $field_type );
    }
  
    protected function importProcessField ($table_name, $field_name, $language_id, $field_value, $field_type = false) 
    {
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
                $valid_string_field = false;
                switch ($this->tables[$table_name]['fields'][$field_name]['data_type']) {
                    case 'int':
                    case 'smallint':
                    case 'mediumint':
                    case 'bigint':
                    case 'tinyint':
                        $field_type = 'integer';
                        if ($this->import['check_values'] && !ctype_digit ($field_value)) {
                            $this->debugMessage ("[*] $import_table_name.$field_name: Value ($field_value) is not an integer", self::DBIO_WARNING);
              
                        }
                        break;
                    case 'float':
                    case 'decimal':
                        $field_type = 'float';
                        if ($this->import['check_values'] && !(ctype_digit (str_replace ('.', '', $field_value) && substr_count ($field_value, '.') <= 1))) {
                            $this->debugMessage ("[*] $import_table_name.$field_name: Value ($field_value) is not a floating-point value.", self::DBIO_WARNING);
              
                        }
                        break;
                    case 'date':
                    case 'datetime':
                        $field_type = 'date';
                        if ($this->import['check_values']) {
                        }
                        break;
                    case 'char':
                    case 'text':
                    case 'varchar':
                    case 'mediumtext':
                        $valid_string_field = true;  //-Indicate that a value string-type field was found and fall through to common processing
                    default:
                        $field_type = 'string';
                        if (!$valid_string_field) {
                            $message = "Unknown datatype (" . $this->tables[$table_name]['fields'][$field_name]['data_type'] . ") for $table_name::$field_name on line #" . $this->stats['record_count'];
                            $this->debugMessage ("[*] process_input_field: $message", self::DBIO_WARNING);
              
                        }
                        if ($this->import['check_values']) {
                            $field_value = htmlentities ($field_value, ENT_COMPAT, DBIO_CHARSET, false);
                            if (trim ($field_value) == '') {
                                $encoded_field_value = mb_convert_encoding ($field_value, DBIO_CHARSET, CHARSET);
                                if (mb_substr_count ($encoded_field_value, DBIO_CHARSET) != 0) {
                                    $message = "Invalid character(s) detected in field $table_name::$field_name: $encoded_field_value on line #" . $this->stats['record_count'];
                                    $this->debugMessage ("[*] process_input_field: $message" , self::DBIO_WARNING);
                  
                                }
                                $field_value = $encoded_field_value;
                
                            }
                            $field_value = html_entity_decode ($field_value, ENT_COMPAT, CHARSET);
              
                        }
                        break;
                }
            }
        }
        if ($field_type === false) {
            $this->debugMessage ("[*] process_input_field ($import_table_name, $field_name, $language_id, $field_value): Can't resolve field type, ignoring.", self::DBIO_WARNING);
      
        } else {
            $this->importAddField ($import_table_name, $field_name, $field_value, $field_type);
      
        }
    }
  
    protected function importFieldCheck ($field_name) 
    {
        return $field_name;
    }
  
// ----------------------------------------------------------------------------------
//                      P R I V A T E   F U N C T I O N S 
// ----------------------------------------------------------------------------------
  
    private function initialize () 
    {
        $this->message = '';

        if (!isset ($this->config) || !is_array ($this->config) || !isset ($this->config['tables']) || !is_array ($this->config['tables'])) {
            trigger_error ('dbIO configuration not set prior to initialize.  Current class: ' . var_export ($this, true), E_USER_ERROR);
            exit();
        }
    
        $this->config['operation'] = null;
    
        if (!isset ($this->config['include_header'])) {
            $this->config['include_header'] = true;
        }
        if (!isset ($this->config['delimiter'])) {
            $this->config['delimiter'] = DBIO_CSV_DELIMITER;
        }
        if (!isset ($this->config['enclosure'])) {
            $this->config['enclosure'] = DBIO_CSV_ENCLOSURE;
        }
        if (!isset ($this->config['escape'])) {
            $this->config['escape'] = DBIO_CSV_ESCAPE;
        }

        $this->tables = array ();
        foreach ($this->config['tables'] as $table_name => $table_info) {
            $this->initializeTableFields ($table_name, (isset ($table_info['io_field_overrides']) && is_array ($table_info['io_field_overrides'])) ? $table_info['io_field_overrides'] : false);
            $this->initializeSqlInputs ($table_name);
      
        }
    }  //-END function initialize
  
    // -----
    // Function to gather the pertinent bits of information about the specified table, taking into account any handler-
    // specific field overrides (i.e. whether/not to include in header and/or data).
    //
    // NOTE: There's an override here for the products_description.products_id field.  It's marked (up to Zen Cart v1.5.5)
    // as being an auto-increment field, which has "special" interpretation by the dbIO processing.  If that field is found
    // within the processing, simply mark it as non-auto-increment; ZC1.5.5 and later already do this.
    //
    private function initializeTableFields ($table_name, $field_overrides) 
    {
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
            $table_info->fields['include_in_export'] = (is_array ($field_overrides) && isset ($field_overrides[$column_name])) ? $field_overrides[$column_name] : true;
            $table_info->fields['nullable'] = ($table_info->fields['nullable'] === 'TRUE');
            $table_info->fields['sort_order'] = 0;
            $this->tables[$table_name]['fields'][$column_name] = $table_info->fields;
          
            $table_info->MoveNext ();
        }   
    }  //-END function initializeTableFields
  
    private function initializeSqlInputs ($table_name) 
    {
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
    
    }  //-END function initializeSqlInputs
}