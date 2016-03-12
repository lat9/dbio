<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
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
    const DBIO_INFORMATIONAL     = 1;
    const DBIO_WARNING           = 2;
    const DBIO_ERROR             = 4;
    const DBIO_STATUS            = 8;
    const DBIO_ACTIVITY          = 16;
    // ----- Handler configuration bit switches -----
    const DBIO_FLAG_NONE         = 0;       //- No special handling
    const DBIO_FLAG_PER_LANGUAGE = 1;       //- Field is handled once per language
    // ----- Handler key-configuration bit-switches -----
    const DBIO_KEY_IS_VARIABLE   = 1;       //- The associated key is mapped to an imported variable
    const DBIO_KEY_IS_FIXED      = 2;       //- The key is mapped to a fixed database field.
    const DBIO_KEY_SELECTED      = 4;       //- The key value should be selected as part of the key-checking SQL (at least one required).
    const DBIO_KEY_IS_MASTER     = 8;       //- The key value should be selected as part of the query, but is not mapped
// ----------------------------------------------------------------------------------
//                             P U B L I C   F U N C T I O N S 
// ----------------------------------------------------------------------------------
    public function __construct ($log_file_suffix) 
    {
        $this->debug = (DBIO_DEBUG != 'false');
        switch (DBIO_DEBUG) {
            case 'true':
                $this->debug_level = self::DBIO_INFORMATIONAL | self::DBIO_WARNING | self::DBIO_ERROR | self::DBIO_STATUS;
                break;
            default:
                $this->debug_level = self::DBIO_WARNING | self::DBIO_ERROR | self::DBIO_STATUS;
                break;
        }
        $this->debug_log_file = DIR_FS_DBIO_LOGS . '/dbio-' . $log_file_suffix . '.log';
        $this->io_errors = array ();
        
        $this->stats = array ( 'report_name' => self::DBIO_UNKNOWN_VALUE, 'errors' => 0, 'warnings' => 0, 'record_count' => 0, 'inserts' => 0, 'updates' => 0, 'date_added' => 'now()' );
        
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
        
        if ($this->stats['report_name'] != self::DBIO_UNKNOWN_VALUE) {
            $this->debug_log_file = DIR_FS_DBIO_LOGS . "/dbio-" . $this->stats['report_name'] . "-$log_file_suffix.log";
        }
        $this->debugMessage ('Configured CHARSET (' . CHARSET . '), DB_CHARSET (' . DB_CHARSET . '), DBIO_CHARSET (' . DBIO_CHARSET . '), PHP multi-byte settings: ' . var_export (mb_get_info (), true));
        
        $this->initializeDbIo ();
    }
    
    public static function getHandlerInformation () {
        trigger_error ("Missing handler information for the active report", E_USER_ERROR);
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
    // Get the script's statistics, returned as an array of statistical information about the previously timed DbIo operation.
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
    // Return the indication as to whether the DbIo action is expected to include a header record.
    //
    public function isHeaderIncluded ()
    {
        return $this->config['include_header'];
    }
    
    // -----
    // Return the indication as to whether the DbIo handler is export-only.
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
    // Return the array of export-filter "instructions"; return false if no filters are provided by the handler.  These values
    // are handled by the DbIo caller, either a cron-job or the admin's Tools->Database I/O Manager.
    //
    public function getExportFilters ()
    {
        return (isset ($this->config) && isset ($this->config['export_filters']) && is_array ($this->config['export_filters'])) ? $this->config['export_filters'] : false;
    }
    
    public function getIOErrors () {
        return (isset ($this->io_errors)) ? $this->io_errors : array ();
    }
    
    // -----
    // Writes the requested message to the current debug-log file, if DbIo debug is enabled.
    //
    public function debugMessage ($message, $severity = self::DBIO_INFORMATIONAL, $log_it = false) 
    {
        if ($this->debug || ($severity & $this->debug_level)) {
            error_log (date (DBIO_DEBUG_DATE_FORMAT) . ": $message\n", 3, $this->debug_log_file);
      
        }
        if ($severity & self::DBIO_WARNING) {
            $this->stats['warnings']++;
            $this->io_errors[] = array ($message, $this->stats['record_count'], self::DBIO_WARNING);
            if ($log_it) {
                trigger_error ($message, E_USER_WARNING);
            }
        } elseif ($severity & self::DBIO_ERROR) {
            $this->stats['errors']++;
            $this->io_errors[] = array ($message, $this->stats['record_count'], self::DBIO_ERROR);
            if ($log_it) {
                trigger_error (DBIO_TEXT_ERROR . $message, E_USER_WARNING);
            }
        }
        if (($severity & self::DBIO_ACTIVITY) && function_exists ('zen_record_admin_activity')) {
            zen_record_admin_activity ($message, ($severity & self::DBIO_ERROR) ? 'error' : (($severity & self::DBIO_WARNING) ? 'warning' : 'info'));
            
        }
    }
    
    public function formatValidateDate ($date_value, $log = false)
    {
        $parsed_date = date_parse ($date_value);
        if ($parsed_date['error_count'] == 0 && checkdate ($parsed_date['month'], $parsed_date['day'], $parsed_date['year'])) {
            $return_date = sprintf ('%u-%02u-%02u', $parsed_date['year'], $parsed_date['month'], $parsed_date['day']);
        } else {
            $return_date = false;
            if ($log) {
                $this->debugMessage ("formatValidateDate: Invalid date ($date_value) supplied.\n" . var_export ($parsed_date, true), self::DBIO_WARNING);
            }
        }
        return $return_date;
    }
    
    // -----
    // Initialize the DbIo export handling. 
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
        // 1) The current DbIo handler must have included its 'config' section.
        // 2) The language requested for the export must be present in the current Zen Cart's database
        //        
        if (!isset ($this->config)) {
            $this->debugMessage (DBIO_ERROR_NO_HANDLER, self::DBIO_ERROR);
            
        } elseif ($language != 'all' && !isset ($this->languages[$language])) {
            $this->message = sprintf (DBIO_ERROR_EXPORT_NO_LANGUAGE, $language);
            
        } else {
            // -----
            // Since those pre-conditions have been met, the majority of the export's initialization
            // requires the breaking-down of the current DbIo handler's configuration into data elements
            // that can be easily parsed during each record's output.
            //
            $initialized = true;
            $this->export_language = $language;
            $this->select_clause = $this->from_clause = $this->where_clause = $this->order_by_clause = '';
            $this->headers = array ();
            $this->saved_data = array ();
            
            foreach ($this->config['tables'] as $config_table_name => $config_table_info) {
                if (!isset ($config_table_info['no_from_clause'])) {
                    $this->from_clause .= $config_table_name;
                    $table_prefix = (isset ($config_table_info['alias']) && $config_table_info['alias'] != '') ? ($config_table_info['alias'] . '.') : '';
                    if ($table_prefix != '') {
                        $this->from_clause .= ' AS ' . $config_table_info['alias'];
                    }
                    if (isset ($config_table_info['join_clause'])) {
                        $this->from_clause .= ' ' . $config_table_info['join_clause'];
                    }
                    $this->from_clause .= ', ';
                }
                
                if (!isset ($this->config['fixed_headers'])) {
                    if ($this->tables[$config_table_name]['uses_language']) {
                        $first_language = true;
                        $this->config['tables'][$config_table_name]['select'] = '';
                        foreach ($this->languages as $language_code => $language_id) {
                            if ($language !== 'all' && $language != $language_code) {
                                continue;
                            }
                            foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
                                if ($field_info['include_in_export'] !== false) {
                                    if ($first_language) {
                                        $this->select_clause .= "$table_prefix$current_field, ";
                                        $this->config['tables'][$config_table_name]['select'] .= "$current_field, ";
                            
                                    }
                                    if ($field_info['include_in_export'] === true) {
                                        $this->headers[] = 'v_' . $current_field . '_' . $language_code;
                            
                                    }
                                }
                            }
                            $first_language = false;
                      
                        }
                        $this->config['tables'][$config_table_name]['select'] = mb_substr ($this->config['tables'][$config_table_name]['select'], 0, -2);
                    
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
            }
            
            if (isset ($this->config['fixed_headers'])) {
                $this->debugMessage ('exportInitialize: Processing fixed headers');
                $no_header_array = (isset ($this->config['fixed_fields_no_header']) && is_array ($this->config['fixed_fields_no_header'])) ? $this->config['fixed_fields_no_header'] : array ();
                $current_language = ($this->export_language == 'all') ? DEFAULT_LANGUAGE : $this->export_language;
                foreach ($this->config['fixed_headers'] as $field_name => $table_name) {
                    $field_alias = (isset ($this->config['tables'][$table_name]['alias'])) ? ($this->config['tables'][$table_name]['alias'] . '.') : '';
                    $this->select_clause .= "$field_alias$field_name, ";
                    if (!in_array ($field_name, $no_header_array)) {
                        $language_suffix = '';
                        if (isset ($this->config['tables'][$table_name]['language_field'])) {
                            $language_suffix = "_$current_language";
                        }
                        $this->headers[] = "v_$field_name$language_suffix";
                    }
                }
                $this->where_clause = $this->config['export_where_clause'];
                $this->order_by_clause = $this->config['export_order_by_clause'];
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
        if ($initialized) {
            $initialized = $this->exportFinalizeInitialization ();
        }
        $this->debugMessage ("exportInitialize ($language), " . (($initialized) ? 'Successful' : ('Unsuccessful (' . $this->message . ')')) . 
                             "\nTables:\n" . var_export ($this->tables, true) . 
                             "\nHeaders:\n" . var_export ($this->headers, true));
        return $initialized;
    }
    
    // -----
    // This function gives the current handler the last opportunity to modify the SQL query clauses used for the current export.  It's
    // usually provided by handlers that use an "export_filter", allowing the handler to inspect any filter-variables provided by
    // the caller.
    //
    // Returns a boolean (true/false) indication of whether the export's initialization was successful.  If unsuccessful, the handler
    // is **assumed** to have set its reason into the class message variable.
    //
    public function exportFinalizeInitialization ()
    {
        $this->debugMessage ("exportFinalizeInitialization\n" . var_export ($this, true));
        return true;
    }
  
    // -----
    // Gets and returns the header-record for the current export.  This is driven by whether the current DbIo handler's configuration
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
            trigger_error ('Export aborted: DbIo export sequence error; not previously initialized.', E_USER_ERROR);
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
            trigger_error ('Import aborted: DbIo helper not configured.', E_USER_ERROR);
            exit ();
        } elseif (!isset ($this->config['keys']) || !is_array ($this->config['keys'])) {
            trigger_error ('Import aborted: DbIo helper\'s "keys" configuration is not set or not an array.', E_USER_ERROR);
            exit ();
        }
        $this->message = '';
        if ($language == 'all' && count ($this->languages) == 1) {
            reset ($this->languages);
            $language = key ($this->languages);
        }
        $this->operation = $operation;
        $this->check_values = ($operation == 'check' || $operation == 'run-check');
        $this->stats['record_count'] = ($this->config['include_header']) ? 1 : 0;
        $this->stats['action'] = "import-$language-$operation";
        $this->unused_fields = array ( ', ' . self::DBIO_SPECIAL_IMPORT, ', ' . self::DBIO_NO_IMPORT );
        
        $this->charset_is_utf8 = (mb_strtolower (CHARSET) == 'utf-8');
        $this->headers = array ();
        
        $import_error = $this->importInitializeKeys ();
        
        $extra_select_clause = '';
        if (isset ($this->config['fixed_headers'])) {
            $no_header_fields = (isset ($this->config['fixed_fields_no_header']) && is_array ($this->config['fixed_fields_no_header'])) ? $this->config['fixed_fields_no_header'] : array ();
            foreach ($this->config['fixed_headers'] as $field_name => $table_name) {
                if (!in_array ($field_name, $no_header_fields)) {
                    if (!isset ($this->config['tables'][$table_name]['language_field'])) {
                        $this->headers[] = "v_$field_name";
                    } else {
                        foreach ($this->languages as $language_code => $language_id) {
                            if ($language != 'all' && $language != $language_code) {
                                continue;
                            }
                            $this->headers[] = 'v_' . $field_name . '_' . $language_code;
                        }
                    }
                }
            }
        } else {
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
        }
        $this->debugMessage ("importInitialize completed.\n" . var_export ($this, true));
        return $import_error;
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
        $this->import_sql_data = array ();
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
                $current_field_status = $this->importHeaderFieldCheck ($current_field);
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
            $this->table_names[] = $table_name;
            $this->language_id[] = $field_language_id;
            
            if ($current_field != self::DBIO_NO_IMPORT) {
                $this->header_field_count++;
                if ($table_name != self::DBIO_SPECIAL_IMPORT && $table_name != self::DBIO_NO_IMPORT) {
                    if ($field_language_id == 0) {
                        $import_table_name = $table_name;
                        if (!isset ($this->import_sql_data[$table_name])) {
                            $this->import_sql_data[$table_name] = array ();
                        }
                    } else {
                        $import_table_name = "$table_name^$field_language_id";
                        if (!isset ($this->import_sql_data[$import_table_name])) {
                            $this->import_sql_data[$import_table_name] = array ();
                        }
                    }

                    $field_type = false;
                    if (isset ($this->tables[$table_name]) && isset ($this->tables[$table_name]['fields'][$current_field])) {
                        $valid_string_field = false;
                        switch ($this->tables[$table_name]['fields'][$current_field]['data_type']) {
                            case 'int':
                            case 'smallint':
                            case 'mediumint':
                            case 'bigint':
                            case 'tinyint':
                                $field_type = 'integer';
                                break;
                            case 'float':
                            case 'decimal':
                                $field_type = 'float';
                                break;
                            case 'date':
                            case 'datetime':
                                $field_type = ($this->tables[$table_name]['fields'][$current_field]['default'] === NULL) ? 'null_date' : 'date';
                                break;
                            case 'char':
                            case 'text':
                            case 'varchar':
                            case 'mediumtext':
                                $valid_string_field = true;  //-Indicate that a value string-type field was found and fall through to common processing
                            default:
                                $field_type = 'string';
                                if (!$valid_string_field) {
                                    $message = "Unknown datatype (" . $this->tables[$table_name]['fields'][$current_field]['data_type'] . ") for $table_name::$current_field on line #" . $this->stats['record_count'];
                                    $this->debugMessage ("[*] importGetHeader: $message", self::DBIO_WARNING);
                                }
                                break;
                        }
                    }
                    if ($field_type === false) {
                        $this->debugMessage ("[*] importGetHeader ($import_table_name, $current_field, $language_id): Can't resolve field type, ignoring.", self::DBIO_WARNING);
                    } else {
                        $this->import_sql_data[$import_table_name][$current_field] = array ( 'value' => '', 'type' => $field_type );
                    }
                    
                    if (isset ($this->variable_keys[$current_field])) {
                        $this->key_index = $key_index;
                        $this->variable_keys[$current_field]['index'] = $key_index;
                        $this->variable_keys[$current_field]['type'] = $field_type;
                    }
                }
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
            $this->message = sprintf (DBIO_FORMAT_MESSAGE_IMPORT_MISSING_KEY, $this->key_field_names);
            $initialization_complete = false;
          
        }
        if (!$initialization_complete) {
            $this->table_names = array ();
        }
        $this->debugMessage ("importGetHeader: finished ($initialization_complete).\n" . $this->prettify (array (
            $this->import_sql_data, 
            $this->table_names, 
            $this->language_id, 
            $this->header_field_count, 
            $this->key_index,
            $this->variable_keys,
            $this->headers)));
        return $initialization_complete;
    
    }
  
    // -----
    // This function is the heart of the DbIo-Import handling, processing the current CSV record into the store's database.
    //
    public function importCsvRecord (array $data) 
    {
        global $db;
        if (!isset ($this->table_names)) {
            trigger_error ("Import aborted, sequencing error. Previous import-header initialization error.", E_USER_ERROR);
            exit ();
        }
        $this->debugMessage ("importCsvRecord: starting ...");
        
        // -----
        // Indicate, initially, that the record is OK to import and increment the count of lines processed.  The last value
        // will be used in any debug-log information to note the location of any processing.
        //
        $this->record_status = true;
        $this->stats['record_count']++;
        
        // -----
        // Determine the "key" value associated with the record.  If there are fewer columns of data than required to access
        // the key-index, the record is not imported.
        //
        $key_index = $this->key_index;
        if (count ($data) < $key_index) {
            $this->debugMessage ('Data record at line #' . $this->stats['record_count'] . ' not imported.  Column count (' . count ($data) . ') missing key column (' . $key_index . ').', self::DBIO_ERROR);
          
        } else {
            $data_key_check = $db->Execute ($this->importBindKeyValues ($data, $this->data_key_sql), false, false, 0, true);
            if ($data_key_check->EOF) {
                $this->import_action = 'insert';
                $this->import_is_insert = true;
                $this->where_clause = '';
                $this->key_fields = array ();
            
            } else {
                $this->import_action = 'update';
                $this->import_is_insert = false;
                $this->where_clause = $this->importBindKeyValues ($data, $this->key_where_clause);
                $this->key_fields = $data_key_check->fields;
            
            }
            
            // -----
            // Continue with the import action only if the record is still "OK" to process.
            //
            if ($this->importCheckKeyValue ($data) !== false) {
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
                    if ($this->headers[$data_index] != self::DBIO_NO_IMPORT) {                   
                        $this->importProcessField ($this->table_names[$data_index], $this->headers[$data_index], $this->language_id[$data_index], $current_element);
                    }
                    $data_index++;
                }

                // -----
                // If the record didn't have errors preventing its insert/update ...
                //
                if ($this->record_status === true) {
                    if ($this->import_is_insert) {
                        $this->stats['inserts']++;
                    } else {
                        $this->stats['updates']++;
                    }
                    $record_key_value = false;
                    foreach ($this->import_sql_data as $database_table => $table_fields) {
                        if ($database_table != self::DBIO_NO_IMPORT) {
                            $table_name = $database_table;
                            $extra_where_clause = '';
                            $capture_key_value = ($this->import_is_insert && isset ($this->config['keys'][$database_table]['capture_key_value']));

                            if (mb_strpos ($table_name, '^') !== false) {
                                $language_tables = explode ('^', $table_name);
                                $table_name = $language_tables[0];
                                $language_id = $language_tables[1];
                                if ($this->import_is_insert) {
                                    $table_fields[$this->config['tables'][$table_name]['language_field']] = array ( 'value' => $language_id, 'type' => 'integer' );
                                } else {
                                    $extra_where_clause = " AND " . $this->config['tables'][$table_name]['alias'] . '.' . $this->config['tables'][$table_name]['language_field'] . " = $language_id";
                                }
                            }
                            $table_fields = $this->importUpdateRecordKey ($table_name, $table_fields, $record_key_value);
                            if ($table_fields !== false) {
                                $sql_query = $this->importBuildSqlQuery ($table_name, $table_fields, $extra_where_clause);
                                if ($sql_query !== false && $this->operation != 'check') {
                                    $db->Execute ($sql_query);
                                    if ($capture_key_value) {
                                        $record_key_value = $db->insert_ID ();
                                    }
                                }
                            }
                        }
                    }
                    $this->importRecordPostProcess ($record_key_value);
                    
                } elseif ($this->record_status === 'processed') {
                    $this->importFinishProcessing ();
                    
                }
            }  //-Record's key value is OK to continue processing
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
        return mb_str_replace (array ("',\n", "=> \n", "array (\n", "0,\n", '  '), array("',", "=> ", "array (", "0,", ' '), var_export ($data, true));
    }
 
    protected function importCheckKeyValue ($data) 
    {
        return $this->record_status;
    }
  
    protected function importUpdateRecordKey ($table_name, $table_fields, $key_value) 
    {
        return $table_fields;
    }
    
    protected function importInitializeKeys ()
    {
        $keys_ok = true;
        $this->key_from_clause = $this->key_select_clause = $this->key_where_clause = '';
        $key_number = 0;
        $this->variable_keys = array ();
        $this->key_field_names = '';
        foreach ($this->config['keys'] as $table_name => $table_key_fields) {
            $table_alias = $table_key_fields['alias'];
            $this->key_from_clause .= "$table_name AS $table_alias, ";
            foreach ($table_key_fields as $key_field_name => $key_field_attributes) {
                if ($key_field_name == 'alias' || $key_field_name == 'capture_key_value') {
                    continue;
                }
                $this->key_field_names .= "$key_field_name, ";
                $key_type = $key_field_attributes['type'];
                if ($key_type & self::DBIO_KEY_IS_VARIABLE) {
                    $key_match_variable = ":key_value$key_number:";
                    $key_number++;
                    if ($this->key_where_clause != '') {
                        $this->key_where_clause .= ' AND ';
                    }
                    $this->key_where_clause .= "$table_alias.$key_field_name = $key_match_variable";
                    $this->variable_keys[$key_field_name] = array ( 'table_name' => $table_name, 'match_variable_name' => $key_match_variable );
                } elseif ($key_type & self::DBIO_KEY_IS_FIXED) {
                    if ($this->key_where_clause != '') {
                        $this->key_where_clause .= ' AND ';
                    }
                    $this->key_where_clause .= "$table_alias.$key_field_name = " . $key_field_attributes['match_fixed_key'];
                } elseif (!($key_type & self::DBIO_KEY_IS_MASTER)) {
                    $keys_ok = false;
                    $this->message = sprintf ('Unknown key field type (%1$u) for %2$s::%3$s.', $field_type, $table_name, $key_field_name);
                }
                if ($key_type & (self::DBIO_KEY_SELECTED | self::DBIO_KEY_IS_MASTER)) {
                    $this->key_select_clause .= "$table_alias.$key_field_name, ";
                }
            }
        }
        if (!$keys_ok || $this->key_from_clause == '' || $this->key_select_clause == '' || $this->key_where_clause == '') {
            $keys_ok = false;
            $this->message = ($this->message == '') ? DBIO_MESSAGE_KEY_CONFIGURATION_ERROR : $this->message;
        } else {
            $this->key_from_clause = substr ($this->key_from_clause, 0, -2);  //-Strip trailing ', '
            $this->key_select_clause = substr ($this->key_select_clause, 0, -2);
            $this->data_key_sql = "SELECT " . $this->key_select_clause . "
                                     FROM " . $this->key_from_clause . " 
                                    WHERE " . $this->key_where_clause . " LIMIT 1";
        }
        return $keys_ok;
    }
    
    protected function importBindKeyValues ($data, $sql_template)
    {
        global $db;
        foreach ($this->variable_keys as $field_name => $field_info) {
            $sql_template = $db->bindVars ($sql_template, $field_info['match_variable_name'], $data[$field_info['index']], $field_info['type']);
        }
        return $sql_template;
    }
    
    protected function importBuildSqlQuery ($table_name, $table_fields, $extra_where_clause = '', $is_override = false, $is_insert = true)
    {
        global $db;
        $record_is_insert = ($is_override) ? $is_insert : $this->import_is_insert;
        if ($record_is_insert) {
            $sql_query = "INSERT INTO $table_name (" . implode (', ', array_keys ($table_fields)) . ")\nVALUES (";
            $sql_query = str_replace ($this->unused_fields, '', $sql_query);
            foreach ($table_fields as $field_name => $field_info) {
                switch ($field_info['type']) {
                    case 'integer':
                        $field_value = (int)$field_info['value'];
                        break;
                     case 'float':
                        $field_value = (float)$field_info['value'];
                        break;
                     case 'date':           //-Fall-through ...
                     case 'null_date':
                        $field_value = $field_info['value'];
                        if ($field_value != 'null' && $field_value != 'NULL' && $field_value != 'now()') {
                            $field_value = "'" . $db->prepare_input ($field_value) . "'";
                        }
                        break;
                    default:
                        $field_value = $field_value['value'];
                        if ($field_value != 'null' && $field_value != 'NULL') {
                            $field_value = "'" . $db->prepare_input ($field_value) . "'";
                        }
                        break;
                }
                $sql_query .= $field_value . ', ';
            }
            $sql_query = substr ($sql_query, 0, -2) . ")";
        } else {
            $sql_query = "UPDATE $table_name SET ";
            $where_clause = $this->where_clause;
            foreach ($table_fields as $field_name =>$field_info) {
                if ($field_name != self::DBIO_NO_IMPORT && !isset ($this->variable_keys[$field_name])) {
                    switch ($field_info['type']) {
                        case 'integer':
                            $field_value = (int)$field_info['value'];
                            break;
                         case 'float':
                            $field_value = (float)$field_info['value'];
                            break;
                         case 'date':
                         case 'null_date':
                            $field_value = $field_info['value'];
                            if ($field_value != 'null' && $field_value != 'NULL' && $field_value != 'now()') {
                                $field_value = "'" . $db->prepare_input ($field_value) . "'";
                            }
                            break;
                        default:
                            $field_value = $field_info['value'];
                            if ($field_value != 'null' && $field_value != 'NULL') {
                                $field_value = "'" . $db->prepare_input ($field_value) . "'";
                            }
                            break;
                    }
                    $sql_query .= "$field_name = $field_value, ";
                }
            }
            $sql_query = substr ($sql_query, 0, -2) . ' WHERE ' . $where_clause . $extra_where_clause;
        }
        $this->debugMessage ("importBuildSqlQuery ($table_name, " . var_export ($table_fields, true));
        $this->debugMessage ("importBuildSqlQuery for $table_name:\n$sql_query", self::DBIO_STATUS);  //- Forces the generated SQL to be logged!!
        return $sql_query;
    }
    
    protected function importRecordPostProcess ($key_value) 
    {
    }        
    
    protected function importFinishProcessing () 
    {
    } 
    
    protected function importProcessField ($table_name, $field_name, $language_id, $field_value) 
    {
        $this->debugMessage ("importProcessField ($table_name, $field_name, $language_id, $field_value)");
        if ($table_name == self::DBIO_SPECIAL_IMPORT) {
            $this->importAddField ($table_name, $field_name, $field_value);
            
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
            $field_type = $this->import_sql_data[$import_table_name][$field_name]['type'];
            $field_error = false;
            if ($this->check_values) {
                switch ($field_type) {
                    case 'integer':
                        if (!ctype_digit ($field_value)) {
                            $field_error = true;
                            $this->debugMessage ("[*] $import_table_name.$field_name, line #" . $this->stats['record_count'] . ": Value ($field_value) is not an integer", self::DBIO_ERROR);
                        }
                        break;
                    case 'float':
                        if (!(ctype_digit (mb_str_replace ('.', '', $field_value)) && mb_substr_count ($field_value, '.') <= 1)) {
                            $field_error = true;
                            $this->debugMessage ("[*] $import_table_name.$field_name, line #" . $this->stats['record_count'] . ": Value ($field_value) is not a floating-point value.", self::DBIO_ERROR);
                        }
                        break;
                    case 'null_date':
                        if ($field_value == 'NULL' || $field_value == 'null' || $field_value == NULL) {
                            $field_value = 'NULL';
                            break;
                        }                   //-Fall through for date checking if not null
                    case 'date':
                        if (!$this->formatValidateDate ($field_value)) {
                            $field_error = true;
                            $this->debugMessage ("[*] $import_table_name.$field_name, line #" . $this->stats['record_count'] . ": Value ($field_value) is not a recognized date value.", self::DBIO_ERROR);
                        }
                        break;
                    case 'string':
                        break;
                    default:
                        $field_error = true;
                        $message = "Unknown datatype (" . $field_type . ") for $table_name::$field_name on line #" . $this->stats['record_count'];
                        $this->debugMessage ("[*] importProcessField: $message", self::DBIO_ERROR);
                        break;
                }
            }
            if ($field_error) {
                $this->record_status = false;
            } else {
                $this->importAddField ($import_table_name, $field_name, $field_value);
            }
        }
    }
  
    protected function importAddField ($table_name, $field_name, $field_value) 
    {
        $this->debugMessage ("importAddField ($table_name, $field_name, $field_value)");
        $this->import_sql_data[$table_name][$field_name]['value'] = $field_value;
    }
  
    // -----
    // This function, called for each-and-every data-element being imported, can return one of three class constants:
    //
    // - DBIO_IMPORT_OK ........ The field has no special-handling requirements.
    // - DBIO_NO_IMPORT ........ The field's value should not set directly to the database for the import; implying
    //                           that the field is calculated separately by the handler's processing.
    // - DBIO_SPECIAL_IMPORT ... The field requires special-handling by the handler to create the associated database elements.
    //
    protected function importHeaderFieldCheck ($field_name) 
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
               (isset ($this->config['fixed_headers']) && is_array ($this->config['fixed_headers'])) ) ) {
            trigger_error ('DbIo configuration not set prior to initialize.  Current class: ' . var_export ($this, true), E_USER_ERROR);
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
    // as being an auto-increment field, which has "special" interpretation by the DbIo processing.  If that field is found
    // within the processing, simply mark it as non-auto-increment; ZC1.5.5 and later already do this.
    //
    private function initializeTableFields ($table_name, $table_config)
    {
        global $db;
        $this->debugMessage ("initializeTableFields for $table_name, table configuration\n" . var_export ($table_config, true));
        $field_overrides = (isset ($table_config['io_field_overrides']) && is_array ($table_config['io_field_overrides'])) ? $table_config['io_field_overrides'] : false;
        $key_fields_only = (isset ($table_config['key_fields_only'])) ? $table_config['key_fields_only'] : false;
        $table_keys = (isset ($this->config['keys'][$table_name])) ? $this->config['keys'][$table_name] : array ();
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
            if ($key_fields_only === true) {
                $table_info->fields['include_in_export'] = isset ($table_keys[$column_name]);
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
        $language_override = (isset ($this->config['tables'][$table_name]['language_override']) && $this->config['tables'][$table_name]['language_override'] === true);
        foreach ($this->tables[$table_name]['fields'] as $field_name => $field_info) {
            if (!$language_override && ($field_name == 'language_id' || $field_name == 'languages_id')) {
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