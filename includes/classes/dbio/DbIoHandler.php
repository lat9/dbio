<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
  
}

abstract class DbIoHandler extends base 
{
// ----------------------------------------------------------------------------------
//                                    C O N S T A N T S 
// ----------------------------------------------------------------------------------
    // ----- Interface Constants -----
    const DBIO_HANDLER_VERSION   = '0.0.0';
    // ----- Field-Import Status Values -----
    const DBIO_IMPORT_OK         = '--ok--';
    const DBIO_NO_IMPORT         = '--none--';
    const DBIO_SPECIAL_IMPORT    = '--special--';
    const DBIO_UNKNOWN_VALUE     = '--unknown--';
    // ----- Message Severity ----- ** Note ** This set of constants are bit-flags!
    const DBIO_WARNING           = 1;
    const DBIO_ERROR             = 2;
    const DBIO_ACTIVITY          = 4;
    // ----- Handler configuration bit switches -----
    const DBIO_FLAG_NONE         = 0;       //- No special handling
    const DBIO_FLAG_PER_LANGUAGE = 1;       //- Field is handled once per language
// ----------------------------------------------------------------------------------
//                             P U B L I C   F U N C T I O N S 
// ----------------------------------------------------------------------------------
    public function __construct ($log_file_suffix) 
    {
        $this->debug = (DBIO_DEBUG == 'true');
        $this->debug_log_file = DIR_FS_DBIO_LOGS . '/dbio-' . $log_file_suffix . '.log';
        
        $this->stats = array ( 'report_name' => self::DBIO_UNKNOWN_VALUE, 'errors' => 0, 'warnings' => 0, 'record_count' => 0, 'inserts' => 0, 'updates' => 0, 'date_added' => 'now()' );
        
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
        
        if (!class_exists ('ForceUTF8\Encoding')) {
            require (DIR_FS_DBIO_CLASSES . 'Encoding.php');

        }       
        $this->encoding = new ForceUTF8\Encoding;
        
        $this->setHandlerConfiguration ();
        
        $this->initializeDbIo ();
    }
    
    // -----
    // Returns the current version of the DbIoHandler class.
    //
    public function getHandlerVersion () {
        return self::DBIO_HANDLER_VERSION;
    }

    // -----
    // Set the current time into the process's start time.
    //
    public function startTimer () 
    {
        $this->stats['start_time'] = microtime (true);
    }
  
    // -----
    // Stops a (presumed previously-set) timer, collecting the statistics for the duration.
    //
    public function stopTimer ($record_statistics = true) 
    {
        $stop_time = microtime (true);
        $this->stats['parse_time'] = $stop_time - $this->stats['start_time'];
        unset ($this->stats['start_time']);
        $this->stats['memory_usage'] = memory_get_usage ();
        $this->stats['memory_peak_usage'] = memory_get_peak_usage ();
        
        if ($record_statistics === true) {
            zen_db_perform (TABLE_DBIO_STATS, $this->stats);
        }
    }
  
    // -----
    // Get the script's statistics, returned as an array of statistical information about the previously timed dbIO operation.
    //
    // - errors .... the number of errors that occurred
    // - warnings ... the number of warnings that were issued
    // - record_count ... the total number of file-related records processed
    // - inserts ........ the number of database inserts (new records)
    // - updates ........ the number of database updates
    // - action ......... the operation performed, [import|export]-[language]
    // - parse_time ..... (double) the number of seconds the entire process took
    // - memory_usage ... the memory usage at completion
    // - peak_memory_usage ... the highest memory usage
    //
    public function getStatsArray () 
    {
        return $this->stats;
    }
  
    // -----
    // Return the language-specific description for the handler.
    //
    public function getHandlerDescription () 
    {
        return (isset ($this->config) && isset ($this->config['description'])) ? $this->config['description'] : DBIO_FORMAT_TEXT_NO_DESCRIPTION;
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
    // Return the indication as to whether the dbIO handler is export-only.
    //
    public function isExportOnly ()
    {
        return $this->config['export_only'];
    }
  
    // -----
    // Return the CSV parameters used by the handler.  Note that a handler can override the configuration's default settings.
    //
    public function getCsvParameters () 
    {
        return array ( 'delimiter' => $this->config['delimiter'], 'enclosure' => $this->config['enclosure'], 'escape' => $this->config['escape'] );
    }
    
    // -----
    // Writes the requested message to the current debug-log file, if dbIO debug is enabled.
    //
    public function debugMessage ($message, $severity = 0) 
    {
        if ($this->debug) {
            error_log (date (DBIO_DEBUG_DATE_FORMAT) . ": $message\n", 3, $this->debug_log_file);
      
        }
        if ($severity & self::DBIO_WARNING) {
            $this->stats['warnings']++;
            trigger_error ($message, E_USER_WARNING);
            
        } elseif ($severity & self::DBIO_ERROR) {
            $this->stats['errors']++;
            trigger_error ($message, E_USER_ERROR);
            
        }
        if (($severity & self::DBIO_ACTIVITY) && function_exists ('zen_record_admin_activity')) {
            zen_record_admin_activity ($message, ($severity & self::DBIO_ERROR) ? 'error' : (($severity & self::DBIO_WARNING) ? 'warning' : 'info'));
            
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
        $this->stats['action'] = "export-$language";
        
        // -----
        // For the export to be successfully initialized:
        //
        // 1) The current dbIO handler must have included its 'config' section.
        // 2) The language requested for the export must be present in the current Zen Cart's database
        //        
        if (!isset ($this->config)) {
            $this->message = DBIO_ERROR_NO_HANDLER;
            trigger_error ($this->message, E_USER_ERROR);
            
        } elseif ($language != 'all' && !isset ($this->languages[$language])) {
            $this->message = sprintf (DBIO_ERROR_EXPORT_NO_LANGUAGE, $language);
            
        } else {
            // -----
            // Since those pre-conditions have been met, the majority of the export's initialization
            // requires the breaking-down of the current dbIO handler's configuration into data elements
            // that can be easily parsed during each record's output.
            //
            $initialized = true;
            $this->export_language = $language;
            $this->select_clause = $this->from_clause = $this->where_clause = $this->order_by_clause = '';
            $this->headers = array ();
            
            // -----
            // If the current handler's configuration supports a fixed-list of output fields, retrieve the applicable
            // headers from the handler itself.
            //
            if (isset ($this->config['export_headers']) && is_array ($this->config['export_headers'])) {
                $this->tables = array ();
                $this->exportInitializeFixedHeaders ();
                
            // -----
            // Otherwise, all fields in all configured tables are "fair game"; the handler will indicate which, if any,
            // fields are to be "kept back" from the export.
            //
            } else {
                foreach ($this->config['tables'] as $config_table_name => $config_table_info) {
                    $this->from_clause .= $config_table_name;
                    $table_prefix = (isset ($config_table_info['short_name']) && $config_table_info['short_name'] != '') ? ($config_table_info['short_name'] . '.') : '';
                    if ($table_prefix != '') {
                        $this->from_clause .= ' AS ' . $config_table_info['short_name'] . ', ';
                    
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
                                        $this->select_clause .= "$table_prefix$current_field, ";
                                        $this->export[$config_table_name]['select'] .= "$current_field, ";
                            
                                    }
                                    if ($field_info['include_in_export'] === true) {
                                        $this->headers[] = 'v_' . $current_field . '_' . $language_code;
                            
                                    }
                                }
                            }
                            $first_language = false;
                      
                        }
                        $this->export[$config_table_name]['select'] = mb_substr ($this->export[$config_table_name]['select'], 0, -2);
                    
                    } else {
                        foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
                            if ($field_info['include_in_export'] !== false) {
                                $this->select_clause .= "$table_prefix$current_field, ";
                                if ($field_info['include_in_export'] === true) {
                                    $this->headers[] = 'v_' . $current_field;
                          
                                }
                            }
                        }
                    }
                }
                $this->from_clause = ($this->from_clause == '') ? '' : mb_substr ($this->from_clause, 0, -2);
                $this->select_clause = ($this->select_clause == '') ? '' : mb_substr ($this->select_clause, 0, -2);
                
                if (isset ($this->config['additional_headers']) && is_array ($this->config['additional_headers'])) {
                    foreach ($this->config['additional_headers'] as $header_value => $flags) {
                        if ($flags & self::DBIO_FLAG_PER_LANGUAGE) {
                            foreach ($this->languages as $language_code => $language_id) {
                                $this->headers[] = $header_value . '_' . $language_code;
                            }
                        } else {
                            $this->headers[] = $header_value;
                        }
                    }
                }
            }
        }
        $this->debugMessage ("exportInitialize ($language), " . (($initialized) ? 'Successful' : ('Unsuccessful (' . $this->message . ')')) . 
                             "\nTables:\n" . var_export ($this->tables, true) . 
                             "\nHeaders:\n" . var_export ($this->headers, true));
        return $initialized;
    }
  
    // -----
    // Gets and returns the header-record for the current export.  This is driven by whether the current dbIO handler's configuration
    // indicates that a header-record is to be included in the export.
    //
    // If no header is to be returned, the function returns (bool)false.  Otherwise, the exported record-count is incremented by 1 (to
    // reflect the header's output) and the array of header titles is returned to the caller.
    //
    public function exportGetHeader () 
    {
        $header = false;
        if ($this->config['include_header'] == true) {
            $this->stats['record_count']++;
            $header = $this->headers;
        }
        return $header;
    }
 
    // -----
    // If properly initialized, creates and returns the SQL query associated with the current export.
    //
    public function exportGetSql ($sql_limit = '') 
    {
        if (!isset ($this->export_language) || !isset ($this->select_clause)) {
            trigger_error ('Export aborted: dbIO export sequence error; not previously initialized.', E_USER_ERROR);
            exit ();
      
         }
        $export_sql = 'SELECT ' . $this->select_clause . ' FROM ' . $this->from_clause;
        if ($this->where_clause != '') {
            $export_sql .= ' WHERE ' . $this->where_clause;
      
        }
        if ($this->order_by_clause != '') {
            $export_sql .= ' ORDER BY ' . $this->order_by_clause;
      
        }
        $export_sql .= " $sql_limit";
        $this->debugMessage ("exportGetSql:\n$export_sql");
        return $export_sql;
    }
  
    // -----
    // This function, called just prior to writing each exported record, increments the count of records exported and
    // also makes sure that the encoding for the output is based on the character-set specified.
    //
    public function exportPrepareFields (array $fields) 
    {
        $this->stats['record_count']++;
        $fields = (DBIO_CHARSET === 'utf8') ? $this->encoding->toUTF8 ($fields) : $this->encoding->toWin1252 ($fields, ForceUTF8\Encoding::ICONV_IGNORE_TRANSLIT);
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
        $this->operation = $operation;
        $this->check_values = ($operation == 'check' || $operation == 'run-check');
        $this->stats['record_count'] = ($this->config['include_header']) ? 1 : 0;
        $this->stats['action'] = "import-$language-$operation";
        
        $this->charset_is_utf8 = (mb_strtolower (CHARSET) == 'utf-8');
        $extra_select_clause = '';
        if (!isset ($this->config['extra_keys'])) {
            $short_name = $this->config['tables'][$this->config['key']['table']]['short_name'];
            $from_clause = $this->config['key']['table'] . ' ' . $short_name;
            $where_clause = $short_name . '.' . $this->config['key']['match_field'] . " = :match_value:";
            $match_field_name = $this->config['key']['match_field'];
            $key_field_type = $this->config['key']['match_field_type'];
        } else {
            if (!is_array ($this->config['extra_keys']) || count ($this->config['extra_keys']) !== 1) {
               trigger_error ("Invalid 'extra_keys' configuration: " . var_export ($this->config, true), E_USER_ERROR);
            }
            $tables_used = array ();
            $from_clause = '';
            $where_clause = '';
            foreach ($this->config['extra_keys'] as $record_key => $key_info) {
                $match_field_name = $key_info['match_field'];
                $key_field_type = $key_info['match_field_type'];
                $short_name = $this->config['tables'][$key_info['table']]['short_name'];
                if (!isset ($tables_used[$key_info['table']])) {
                    if ($from_clause != '') {
                        $from_clause .= ', ';
                    }
                    $from_clause .= $key_info['table'] . " $short_name";
                    $table_array[] = $key_info['table'];
                }
                if ($where_clause != '') {
                    $where_clause .= ' AND ';
                }
                if (isset ($key_info['match_field'])) {
                    $where_clause .= "$short_name." . $key_info['match_field'] . ' = :match_value: ';
                } else {
                    trigger_error ('Missing match_field in extra_keys configuration; operation aborted.', E_USER_ERROR);
                }
            }
            if (!isset ($tables_used[$this->config['key']['table']])) {
                $from_clause .= ', ' . $this->config['key']['table'] . ' ' . $this->config['tables'][$this->config['key']['table']]['short_name'];
            }
            $match_info = $this->config['extra_keys'][$this->config['key']['extra_key_name']];
            $match_key_sql_name = $this->config['tables'][$match_info['table']]['short_name'] . '.' . $match_info['key_field'];
            $extra_select_clause .= ', ' . $match_key_sql_name;
            $where_clause .= ' AND ' . $this->config['tables'][$this->config['key']['table']]['short_name'] . '.' . $this->config['key']['match_field'] . " = $match_key_sql_name";
        }
        $this->key_field_name = $match_field_name;
        $this->key_field_type = $key_field_type;
        $this->data_key_sql = "SELECT " . $this->config['tables'][$this->config['key']['table']]['short_name'] . '.' . $this->config['key']['key_field'] . " as key_value$extra_select_clause
                                 FROM " . $from_clause . " 
                                WHERE " . $where_clause . " LIMIT 1";
                          
        $this->headers = array ();
        foreach ($this->config['tables'] as $config_table_name => $config_table_info) {
            if (isset ($config_table_info['import_extra_keys_only'])) {
                continue;
            }
            if ($this->tables[$config_table_name]['uses_language']) {
                foreach ($this->languages as $language_code => $language_id) {
                    if ($language !== 'all' && $language != $language_code) {
                        continue;
                    }
                    foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
                        if ($field_info['include_in_export'] === true) {
                            $this->headers[] = 'v_' . $current_field . '_' . $language_code;
                        }
                    }
                }
            } else {
                foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
                    if ($field_info['include_in_export'] === true) {
                        $this->headers[] = 'v_' . $current_field;
                    }
                }
            }
        }
            
        if (isset ($this->config['additional_headers']) && is_array ($this->config['additional_headers'])) {
            foreach ($this->config['additional_headers'] as $header_value => $flags) {
                if ($flags & self::DBIO_FLAG_PER_LANGUAGE) {
                    foreach ($this->languages as $language_code => $language_id) {
                        $this->headers[] = $header_value . '_' . $language_code;
                    }
                } else {
                    $this->headers[] = $header_value;
                }
            }
        }
        
        $this->debugMessage ("importInitialize completed.\n" . var_export ($this, true));
        return $this->headers;
        
    }
  
    public function importGetHeader ($header) 
    {
        if (!isset ($this->headers)) {
            trigger_error ("Import aborted, sequencing error. Can't get the header before overall initialization.", E_USER_ERROR);
            exit ();
        }
        if (!is_array ($header)) {
            $this->debugMessage ('importGetHeader: No header included, using generated default.');
            $header = $this->headers;
          
        }

        $this->debugMessage ("importGetHeader, using headers:\n" . var_export ($header, true));
        $this->table_names = array ();
        $this->language_id = array ();
        $this->header_field_count = 0;
        $this->key_index = false;
        $key_index = 0;
        foreach ($header as &$current_field) {
            $table_name = self::DBIO_NO_IMPORT;
            $field_language_id = 0;
            if (mb_strpos ($current_field, 'v_') !== 0 || strlen ($current_field) < 3) {
                $current_field = self::DBIO_NO_IMPORT;
            
            } else {
                $current_field = mb_substr ($current_field, 2);  //-Strip off leading 'v_'
                $current_field_status = $this->importFieldCheck ($current_field);
                if ($current_field_status == self::DBIO_NO_IMPORT ) {
                    $current_field = self::DBIO_NO_IMPORT;
                    
                } elseif ($current_field_status == self::DBIO_SPECIAL_IMPORT) {
                    $table_name = self::DBIO_SPECIAL_IMPORT;
                    
                } else {
                    $field_found = false;
                    foreach ($this->tables as $database_table_name => $table_info) {
                        if ($table_info['uses_language']) {
                            $field_language_code = mb_substr ($current_field, -2);
                            $field_name = mb_substr ($current_field, 0, -3);
                            if ($field_name == '') {
                                $current_field = self::DBIO_NO_IMPORT;
                                break;
                            }
                            foreach ($this->languages as $language_code => $language_id) {
                                if ($field_language_code != $language_code) {
                                    continue;
                                }
                                if (array_key_exists ($field_name, $table_info['fields'])) {
                                    $table_name = $database_table_name;
                                    $field_language_id = $language_id;
                                    $current_field = $field_name;
                                    $field_found = true;
                                    break;
                                }
                            }
                        } elseif (array_key_exists ($current_field, $table_info['fields'])) {
                            $table_name = $database_table_name;
                            $field_found = true;
                        }
                        if ($field_found) {
                            break;
                        }
                    }
                }
            }
           if ($this->key_field_name == $current_field) {
                $this->key_index = $key_index;
            }
            $this->table_names[] = $table_name;
            $this->language_id[] = $field_language_id;
            if ($current_field != self::DBIO_NO_IMPORT) {
                $this->header_field_count++;
            }  
            $key_index++;
          
        }
        $this->headers = $header;
        $this->header_columns = count ($header);   
        $initialization_complete = true;
        
        if ($this->header_field_count == 0) {
            $this->message = DBIO_MESSAGE_IMPORT_MISSING_HEADER;
            $initialization_complete = false;
          
        } elseif ($this->key_index === false) {
            $this->message = sprintf (DBIO_FORMAT_MESSAGE_IMPORT_MISSING_KEY, "$key_table_name::$key_match_field");
            $initialization_complete = false;
          
        }
        if (!$initialization_complete) {
            unset ($this->table_names);
        }
        return $initialization_complete;
    
    }
  
    // -----
    // This function is the heart of the dbIO-Import handling, processing the current CSV record into the store's database.
    //
    public function importCsvRecord (array $data) 
    {
        global $db;
        if (!isset ($this->table_names)) {
            trigger_error ("Import aborted, sequencing error. Previous import-header initialization error.", E_USER_ERROR);
            exit ();
        }
        // -----
        // Indicate, initially, that the record is OK to import and increment the count of lines processed.  The last value
        // will be used in any debug-log information to note the location of any processing.
        //
        $this->record_ok = true;
        $this->stats['record_count']++;
        
        // -----
        // Determine the "key" value associated with the record.  If there are fewer columns of data than required to access
        // the key-index, the record is not imported.
        //
        $key_index = $this->key_index;
        if (count ($data) < $key_index) {
            $this->debugMessage ('Data record at line #' . $this->stats['record_count'] . ' not imported.  Column count (' . count ($data) . ') missing key column (' . $key_index . ').', self::DBIO_ERROR);
          
        } else {
            $data_key_sql = $db->bindVars ($this->data_key_sql, ':match_value:', $data[$key_index], $this->key_field_type);
            $data_key_check = $db->Execute ($data_key_sql);
            if ($data_key_check->EOF) {
                $this->import_action = 'insert';
                $this->import_is_insert = true;
                $this->stats['inserts']++;
                $key_value = false;
                $key_value_fields = array ();
                $this->where_clause = '';
            
            } else {
                $this->import_action = 'update';
                $this->import_is_insert = false;
                $this->stats['updates']++;
                $key_value_fields = $data_key_check->fields;
                $key_value = $key_value_fields['key_value'];
                $this->where_clause = $db->bindVars ($this->config['key']['key_field'] . ' = :key_value:', ':key_value:', $key_value, $this->config['key']['key_field_type']);
            
            }
            
            // -----
            // Continue with the import action only if the record is still "OK" to process.
            //
            $this->import_sql_data = array ();
            if ($this->importCheckKeyValue ($data[$key_index], $key_value, $key_value_fields) !== false) {
                // -----
                // Loop, processing each 'column' of data into its respective database field(s).  At the end of this processing,
                // we'll have a couple of sql-data arrays to be used as input to the database 'perform' function; that function will
                // handle any conversions and quote-insertions required.
                //
                $data_index = 0;
                foreach ($data as $current_element) {
                    if ($data_index > $this->header_columns) {
                        break;
                    }
                    $field_name = $this->headers[$data_index];
                    $table_name = $this->table_names[$data_index];
                    $language_id =$this->language_id[$data_index];
                    $data_index++;
                
                    if ($field_name == self::DBIO_NO_IMPORT) {
                        continue;
                    }
                    $this->importProcessField ($table_name, $field_name, $language_id, $current_element);

                }

                // -----
                // If the record didn't have errors preventing its insert/update ...
                //
                if ($this->importFinalizeFields ($data) !== false) {
                    foreach ($this->import_sql_data as $database_table => $sql_data_array) {
                        if ($database_table != self::DBIO_NO_IMPORT) {
                            $table_name = $database_table;
                            $where_clause = $this->where_clause;
                            $capture_key_value = ($this->import_is_insert && $this->config['key']['table'] == $table_name);

                            if (mb_strpos ($table_name, '^') !== false) {
                                $language_tables = explode ('^', $table_name);
                                $table_name = $language_tables[0];
                                $language_id = $language_tables[1];
                                if ($this->import_is_insert) {
                                    $sql_data_array[] = array ( 'fieldName' => $this->config['tables'][$table_name]['language_field'], 'value' => $language_id, 'type' => 'integer' );
                                } else {
                                    $where_clause .= " AND language_id = $language_id";
                                }
                            }
                            $sql_data_array = $this->importUpdateRecordKey ($table_name, $sql_data_array, $key_value, $key_value_fields);
                            if ($sql_data_array !== false) {
                                if ($this->operation == 'check') {
                                    $this->debugMessage ("SQL for $table_name:\n" . $db->perform ($table_name, $sql_data_array, $this->import_action, $where_clause, 'return') . "\n");
                          
                                } else {
                                    $this->debugMessage ("Performing database " . $this->import_action . " for $table_name with where_clause = '$where_clause':\n" . $this->prettify ($sql_data_array) . "\n");
                                    $db->perform ($table_name, $sql_data_array, $this->import_action, $where_clause);
                                    if ($capture_key_value) {
                                        $key_value = $db->insert_ID ();
                                    }
                                }
                            }
                        }
                    }
                    $this->importRecordPostProcess ($key_value);
                }
            }  //-Record is OK to continue processing
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
    
    protected function exportInitializeFixedHeaders ()
    {
        foreach ($this->config['export_headers']['tables'] as $table_name => $table_alias) {
            $this->from_clause .= "$table_name $table_alias, ";
        }
        $this->from_clause = substr ($this->from_clause, 0, -2);
        
        foreach ($this->config['export_headers']['fields'] as $field_name => $field_alias) {
            $this->select_clause .= "$field_alias.$field_name, ";
            $this->headers[] = "v_$field_name";
        }
        $this->select_clause = substr ($this->select_clause, 0, -2);
        $this->where_clause = $this->config['export_headers']['where_clause'];
        $this->order_by_clause = $this->config['export_headers']['order_by_clause'];
        
        $this->debugMessage ("exportInitializeFixedHeaders, from_clause = " . $this->from_clause . ", select_clause = " . $this->select_clause . ", headers:\n" . var_export ($this->headers, true));
    }
  
    // -----
    // Local function (used when logging messages to reduce unnecessary whitespace.
    //
    protected function prettify (array $data) 
    {
        return mb_str_replace (array ("',\n", "=> \n", "array (\n", "0,\n", '  '), array("',", "=> ", "array (", "0,", ' '), var_export ($data, true));
    }

    protected function importFinalizeFields ($data) 
    {
        return $this->record_ok;
    }
 
    protected function importCheckKeyValue ($data_value, $key_value, $key_value_fields) 
    {
        return $this->record_ok;
    }
  
    protected function importUpdateRecordKey ($table_name, $sql_data_array, $key_value, $key_value_fields) 
    {
        return $sql_data_array;
    }
    
    protected function importRecordPostProcess ($key_value) 
    {
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
        $this->debugMessage ("importProcessField ($table_name, $field_name, $language_id, $field_value, $field_type)");
        if ($table_name == self::DBIO_SPECIAL_IMPORT) {
            $this->importAddField ($table_name, $field_name, $field_value, $field_type);
            
        } elseif ($table_name != self::DBIO_NO_IMPORT) {
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
                            if ($this->check_values && !ctype_digit ($field_value)) {
                                $this->debugMessage ("[*] $import_table_name.$field_name: Value ($field_value) is not an integer", self::DBIO_WARNING);
                  
                            }
                            break;
                        case 'float':
                        case 'decimal':
                            $field_type = 'float';
                            if ($this->check_values && !(ctype_digit (mb_str_replace ('.', '', $field_value)) && mb_substr_count ($field_value, '.') <= 1)) {
                                $this->debugMessage ("[*] $import_table_name.$field_name: Value ($field_value) is not a floating-point value.", self::DBIO_WARNING);
                  
                            }
                            break;
                        case 'date':
                        case 'datetime':
                            $field_type = 'date';
                            if ($this->check_values) {
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
                                $this->debugMessage ("[*] importProcessField: $message", self::DBIO_WARNING);
                  
                            }
                            if ($this->charset_is_utf8) {
                                $field_value = $this->encoding->toUTF8 ($field_value);
                            } else {
                                $field_value = $this->encoding->toLatin1 ($field_value);
                            }
                            break;
                    }
                }
            }
            if ($field_type === false) {
                $this->debugMessage ("[*] importProcessField ($import_table_name, $field_name, $language_id, $field_value): Can't resolve field type, ignoring.", self::DBIO_WARNING);
          
            } else {
                $this->importAddField ($import_table_name, $field_name, $field_value, $field_type);
          
            }
        }
    }

    // -----
    // This function, called for each-and-every data-element being imported, can return one of three class constants:
    //
    // - DBIO_IMPORT_OK ........ The field has no special-handling requirements.
    // - DBIO_NO_IMPORT ........ The field's value should not set directly to the database for the import; implying
    //                           that the field is calculated separately by the handler's processing.
    // - DBIO_SPECIAL_IMPORT ... The field requires special-handling by the handler to create the associated database elements.
    //
    protected function importFieldCheck ($field_name) 
    {
        return self::DBIO_IMPORT_OK;
    }
  
// ----------------------------------------------------------------------------------
//                      P R I V A T E   F U N C T I O N S 
// ----------------------------------------------------------------------------------
  
    private function initializeDbIo () 
    {
        $this->message = '';

        if (!isset ($this->config) || !is_array ($this->config) || 
            !( (isset ($this->config['tables']) && is_array ($this->config['tables'])) ||
               (isset ($this->config['export_headers']) && is_array ($this->config['export_headers'])) ) ) {
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
        $this->config['export_only'] = (isset ($this->config['export_only']) && $this->config['export_only'] === true);

        if (isset ($this->config['tables'])) {
            $this->tables = array ();
            foreach ($this->config['tables'] as $table_name => $table_info) {
                $this->initializeTableFields ($table_name, $table_info);
                $this->initializeSqlInputs ($table_name);
            }
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
    private function initializeTableFields ($table_name, $table_config)
    {
        global $db;
        $this->debugMessage ("initializeTableFields for $table_name, table configuration\n" . var_export ($table_config, true));
        $field_overrides = (isset ($table_config['io_field_overrides']) && is_array ($table_config['io_field_overrides'])) ? $table_config['io_field_overrides'] : false;
        $export_key_field_only = (isset ($table_config['export_key_field_only'])) ? $table_config['export_key_field_only'] : false;
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
            if ($export_key_field_only === true) {
                if (isset ($table_config['key_field'])) {
                    $table_info->fields['include_in_export'] = ($column_name == $table_config['key_field']);
                } elseif ($column_name == $this->config['key']['key_field']) {
                    $table_info->fields['include_in_export'] = 'no-header';
                } elseif ($column_name == $this->config['key']['match_field']) {
                    $table_info->fields['include_in_export'] = true;
                } else {
                    $table_info->fields['include_in_export'] = false;
                }
            } else {
                $table_info->fields['include_in_export'] = ($field_overrides !== false && isset ($field_overrides[$column_name])) ? $field_overrides[$column_name] : true;
            }
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