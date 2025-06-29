<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2025, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.1.0
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

abstract class DbIoHandler extends base
{
// ----------------------------------------------------------------------------------
//                                    C O N S T A N T S
// ----------------------------------------------------------------------------------
    // ----- Interface Constants -----
    const DBIO_HANDLER_VERSION   = '1.6.7';
    // ----- Field-Import Status Values -----
    const DBIO_IMPORT_OK         = '--ok--';
    const DBIO_NO_IMPORT         = '--none--';
    const DBIO_SPECIAL_IMPORT    = '--special--';
    const DBIO_UNKNOWN_VALUE     = '--unknown--';
    // ----- DbIo commands -----
    const DBIO_COMMAND_REMOVE    = 'REMOVE';
    // ----- Message Severity ----- ** Note ** This set of constants are bit-flags!
    const DBIO_INFORMATIONAL     = 1;
    const DBIO_WARNING           = 2;
    const DBIO_ERROR             = 4;
    const DBIO_STATUS            = 8;
    const DBIO_ACTIVITY          = 16;
    // ----- Handler configuration bit switches -----
    const DBIO_FLAG_NONE         = 0;       //- No special handling
    const DBIO_FLAG_PER_LANGUAGE = 1;       //- Field is handled once per language
    const DBIO_FLAG_NO_EXPORT    = 2;       //- Possibly set during export-customization to indicate that the field should not be exported
    const DBIO_FLAG_FIELD_SELECT = 4;       //- Indicates that an additional-header field has a companion select-field
    // ----- Handler key-configuration bit-switches -----
    const DBIO_KEY_IS_VARIABLE   = 1;       //- The associated key is mapped to an imported variable
    const DBIO_KEY_IS_FIXED      = 2;       //- The key is mapped to a fixed database field.
    const DBIO_KEY_SELECTED      = 4;       //- The key value is selected as part of the key-checking SQL (at least one required).
    const DBIO_KEY_IS_MASTER     = 8;       //- The key value is selected as part of the query, but is not mapped
    const DBIO_KEY_IS_ALTERNATE  = 16;      //- The key is used in addition to the "master" key setting to locate a unique matching record
    // ----- Handler language_override values -----
    const DBIO_OVERRIDE_ALL      = 1;       //- ALL fields within the language-based table use only the DEFAULT_LANGUAGE
    const DBIO_OVERRIDE_STRING   = 2;       //- String fields are I/O with language; others aren't.

    public
        $stats;

    protected
        $queryCache = null,
        $queryCacheOlder = null,
        $debug,
        $debug_level,
        $debug_log_file,
        $version_mismatch,

        $operation,

        $languages,
        $first_language_code,
        $export_language,
        $import_language_id,
        $language_id,

        $encoding,
        $charset_is_utf8,
        $config,
        $message,
        $io_errors,

        $tables,
        $customized_fields,
        $unused_fields,

        $select_clause,
        $from_clause,
        $where_clause,
        $order_by_clause,
        $headers,
        $header_columns,
        $header_field_count,

        $saved_data,
        $handler_does_import,
        $handler_overrides_import,
        $import_sql_data,
        $export_where_clause,
        $export_order_by_clause,

        $table_names,

        $key_index,
        $key_fields,
        $key_field_names,
        $key_from_clause,
        $key_select_clause,
        $key_where_clause,
        $alternate_key_included,
        $variable_keys,

        $dbio_command_index,

        $record_status,
        $import_action,
        $import_is_insert,
        $data_key_sql,
        $data_key_check;

// ----------------------------------------------------------------------------------
//                             P U B L I C   F U N C T I O N S
// ----------------------------------------------------------------------------------
    public function __construct($log_file_suffix)
    {
        global $queryCache;

        if (isset($queryCache) && is_object($queryCache)) {
            if (method_exists($queryCache, 'reset')) {
                $this->queryCache = $queryCache;
            } else {
                $this->queryCacheOlder = $queryCache;
            }
        }
        $this->debug = (DBIO_DEBUG !== 'false');
        switch (DBIO_DEBUG) {
            case 'true':
                $this->debug_level = self::DBIO_INFORMATIONAL | self::DBIO_WARNING | self::DBIO_ERROR | self::DBIO_STATUS;
                break;
            default:
                $this->debug_level = self::DBIO_WARNING | self::DBIO_ERROR | self::DBIO_STATUS;
                break;
        }
        $this->debug_log_file = DIR_FS_DBIO_LOGS . '/dbio-' . $log_file_suffix . '.log';

        $this->operation = '';
        $this->io_errors = [];

        $this->stats = [
            'report_name' => self::DBIO_UNKNOWN_VALUE, 
            'errors' => 0, 
            'warnings' => 0, 
            'record_count' => 0, 
            'inserts' => 0, 
            'updates' => 0, 
            'date_added' => 'now()' 
        ];

        $this->languages = [];
        if (!class_exists('language')) {
            require DIR_FS_CATALOG . DIR_WS_CLASSES . 'language.php';
        }
        $languages = new language;
        foreach ($languages->catalog_languages as $iso_code_2 => $language_info) {
            $this->languages[$iso_code_2] = $language_info['id'];
        }
        $this->first_language_code = current(array_keys($this->languages));
        unset($languages);

        if (!class_exists('ForceUTF8\Encoding')) {
            require DIR_FS_DBIO_CLASSES . 'Encoding.php';
        }
        $this->encoding = new ForceUTF8\Encoding;
        $this->charset_is_utf8 = (dbio_strtoupper(CHARSET) === 'UTF-8');

        $this->setHandlerConfiguration();

        if ($this->stats['report_name'] !== self::DBIO_UNKNOWN_VALUE) {
            $this->debug_log_file = DIR_FS_DBIO_LOGS . "/dbio-" . $this->stats['report_name'] . "-$log_file_suffix.log";
        }

        $this->initializeDbIo();
    }

    public static function getHandlerInformation()
    {
        dbioLogError("Missing handler information for the active report");
    }

    public static function getHandlerExportFilters()
    {
        return false;
    }

    // -----
    // Returns the current version of the DbIoHandler class.
    //
    public function getHandlerVersion()
    {
        return self::DBIO_HANDLER_VERSION;
    }

    // -----
    // Set the current time into the process's start time.
    //
    public function startTimer()
    {
        $this->stats['start_time'] = microtime(true);
    }

    // -----
    // Stops a (presumed previously-set) timer, collecting the statistics for the duration.
    //
    public function stopTimer($record_statistics = true)
    {
        $stop_time = microtime(true);
        $this->stats['parse_time'] = $stop_time - $this->stats['start_time'];
        unset($this->stats['start_time']);
        $this->stats['memory_usage'] = memory_get_usage();
        $this->stats['memory_peak_usage'] = memory_get_peak_usage();

        if ($record_statistics === true) {
            zen_db_perform(TABLE_DBIO_STATS, $this->stats);
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
    public function getStatsArray()
    {
        return $this->stats;
    }

    // -----
    // Return the language-specific description for the handler.
    //
    public function getHandlerDescription()
    {
        return (isset($this->config) && isset($this->config['description'])) ? $this->config['description'] : DBIO_FORMAT_TEXT_NO_DESCRIPTION;
    }

    // -----
    // Return any message (usually an error or warning) from the last action performed by the handler.
    //
    public function getHandlerMessage()
    {
        return $this->message;
    }

    // -----
    // Return the indication as to whether the DbIo action is expected to include a header record.
    //
    public function isHeaderIncluded()
    {
        return $this->config['include_header'];
    }

    // -----
    // Return the indication as to whether the DbIo handler is export-only.
    //
    public function isExportOnly()
    {
        return $this->config['export_only'];
    }

    // -----
    // Return the CSV parameters used by the handler.  Note that a handler can override the configuration's default settings.
    //
    public function getCsvParameters()
    {
        return [
            'delimiter' => $this->config['delimiter'], 
            'enclosure' => $this->config['enclosure'], 
            'escape' => $this->config['escape'] 
        ];
    }

    // -----
    // Return the array of export-filter "instructions"; return false if no filters are provided by the handler.  These values
    // are handled by the DbIo caller, either a cron-job or the admin's Tools->Database I/O Manager.
    //
    public function getExportFilters()
    {
        return (isset($this->config) && isset($this->config['export_filters']) && is_array($this->config['export_filters'])) ? $this->config['export_filters'] : false;
    }

    public function getIOErrors()
    {
        return (isset($this->io_errors)) ? $this->io_errors : [];
    }

    // -----
    // Writes the requested message to the current debug-log file, if DbIo debug is enabled.
    //
    public function debugMessage($message, $severity = self::DBIO_INFORMATIONAL, $log_it = false)
    {
        if ($this->debug === true || ($severity & $this->debug_level)) {
            error_log(date(DBIO_DEBUG_DATE_FORMAT) . ": $message\n", 3, $this->debug_log_file);
        }
        if ($severity & self::DBIO_WARNING) {
            $this->stats['warnings']++;
            $this->io_errors[] = [
                $message,
                $this->stats['record_count'],
                self::DBIO_WARNING
            ];
            if ($log_it === true) {
                trigger_error($message, E_USER_WARNING);
            }
        } elseif ($severity & self::DBIO_ERROR) {
            $this->stats['errors']++;
            $this->io_errors[] = [
                $message,
                $this->stats['record_count'],
                self::DBIO_ERROR
            ];
            if ($log_it === true) {
                trigger_error(DBIO_TEXT_ERROR . $message, E_USER_WARNING);
            }
        }
        if (!($this->operation === 'check') && ($severity & self::DBIO_ACTIVITY)) {
            zen_record_admin_activity($message, ($severity & self::DBIO_ERROR) ? 'error' : (($severity & self::DBIO_WARNING) ? 'warning' : 'info'));
        }
    }

    public function formatValidateDate($date_value_in, $field_type = 'date', $is_nullable = false)
    {
        if (empty($date_value_in)) {
            $return_date = ($is_nullable) ? 'null' : false;
            $parsed_date = '';
        } else {
            $date_value = $date_value_in;
            if (DBIO_IMPORT_DATE_FORMAT !== 'y-m-d') {
                $date_time_split = explode(' ', $date_value);
                $needle = (dbio_strpos($date_time_split[0], '/') !== false) ? '/' : '-';
                $date_split = explode($needle, $date_time_split[0]);
                if (count($date_split) === 3 && dbio_strlen($date_split[0]) !== 4) {
                    if (DBIO_IMPORT_DATE_FORMAT === 'd-m-y') {
                        $date_value = sprintf('%u-%02u-%02u', $date_split[2], $date_split[1], $date_split[0]);
                    } else {
                        $date_value = sprintf('%u-%02u-%02u', $date_split[2], $date_split[0], $date_split[1]);
                    }
                    if ($field_type === 'datetime' && isset($date_time_split[1])) {
                        $date_value .= ' ' . $date_time_split[1];
                    }
                }
            }

            $parsed_date = date_parse($date_value);
            if ($parsed_date['error_count'] === 0 && checkdate($parsed_date['month'], $parsed_date['day'], $parsed_date['year']) === true) {
                $return_date = sprintf('%u-%02u-%02u', $parsed_date['year'], $parsed_date['month'], $parsed_date['day']);
                if ($field_type === 'datetime') {
                    $return_date .= sprintf(' %02u:%02u:%02u', $parsed_date['hour'], $parsed_date['minute'], $parsed_date['second']);
                }
            } else {
                $return_date = false;
            }
        }
        $this->debugMessage("formatValidateDate: ($date_value_in, $field_type), DBIO_IMPORT_DATE_FORMAT = '" . DBIO_IMPORT_DATE_FORMAT . "', returning ($return_date). Parsed date: " . print_r($parsed_date, true)); 
        return $return_date;
    }

    // -----
    // This function returns an associative array containing the 'keys' (i.e. required) and 'fields' (i.e. optional) available
    // for export using the current handler.
    //
    final public function getCustomizableFields()
    {
        $customizable_fields = [];
        if (isset($this->config['allow_export_customizations']) && $this->config['allow_export_customizations'] === true) {
            $customizable_fields['keys'] = [];
            if (isset($this->config['keys'])) {
                foreach ($this->config['keys'] as $table_name => $table_config) {
                    foreach ($table_config as $field_name => $field_config) {
                        if ($field_name === 'alias' || $field_name === 'capture_key_value') {
                            continue;
                        }
                        $customizable_fields['keys'][] = $field_name;
                    }
                }
            }

            $customizable_fields['fields'] = [];
            foreach ($this->tables as $table_name => $table_config) {
                foreach ($table_config['fields'] as $field_name => $field_config) {
                    if ($field_config['include_in_export'] === true && !in_array($field_name, $customizable_fields['keys'])) {
                        $customizable_fields['fields'][] = $field_name;
                    }
                }
            }

            // -----
            // If the handler has set "additional_headers", i.e. fields that require special handling, add them at the end.
            //
            if (isset($this->config['additional_headers'])) {
                foreach ($this->config['additional_headers'] as $field_name => $field_config) {
                    $customizable_fields['fields'][] = str_replace('v_', '', $field_name);
                }
            }
        }
        return $customizable_fields;
    }

    // -----
    // This function, used by the export-customization within the admin's Tools->Database I/O Manager, allows customization of
    // the fields exported by any DbIo handler.
    //
    // $customized_fields:  A regular array containing the fields that should be included in the subsequent export.
    //
    // Notes:
    // 1) This function CANNOT be overridden!
    // 2) Calling this function with an empty array essentially disables the table's export!
    // 3) For proper operation, this function needs to be called prior to any call to the class' exportInitialize function!
    //
    final public function exportCustomizeFields($customized_fields)
    {
        $customized = false;
        if (is_array($customized_fields) && isset($this->config['allow_export_customizations']) && $this->config['allow_export_customizations'] === true) {
            $this->customized_fields = $customized_fields;
            $customized = true;
        }
        $this->debugMessage("exportCustomizeFields, returning ($customized):\n" . print_r($customized_fields, true));

        return $customized;
    }

    // -----
    // Initialize the DbIo export handling. 
    // 
    public function exportInitialize($language = 'all')
    {
        $this->logCharacterSetConfig();

        $initialized = false;
        if ($language === 'all' && count($this->languages) === 1) {
            reset($this->languages);
            $language = key($this->languages);
        }
        $this->message = '';
        $this->stats['action'] = "export-$language";

        // -----
        // For the export to be successfully initialized:
        //
        // 1) The current DbIo handler must have included its 'config' section.
        // 2) The current version of this class must "support" the features required by the handler itself.
        // 3) The language requested for the export must be present in the current Zen Cart's database
        //        
        if (!isset($this->config)) {
            $this->debugMessage(DBIO_ERROR_NO_HANDLER, self::DBIO_ERROR);
        } elseif ($this->version_mismatch === true) {
            $this->message = sprintf(DBIO_ERROR_HANDLER_VERSION_MISMATCH, $this->stats['report_name']);
             trigger_error('DbIoHandler version mismatch for handler "' . $this->stats['report_name'] . '".  Required: ' . $this->config['handler_version'] . ', Current: ' . self::DBIO_HANDLER_VERSION, E_USER_WARNING);
        } elseif ($language !== 'all' && !isset($this->languages[$language])) {
            $this->message = sprintf(DBIO_ERROR_EXPORT_NO_LANGUAGE, $language);
        } else {
            // -----
            // Since those pre-conditions have been met, the majority of the export's initialization
            // requires the breaking-down of the current DbIo handler's configuration into data elements
            // that can be easily parsed during each record's output.
            //
            $this->export_language = $language;
            $this->select_clause = $this->from_clause = $this->where_clause = $this->order_by_clause = '';
            $this->headers = [];
            $this->saved_data = [];
            
            if (isset($this->customized_fields) && is_array($this->customized_fields)) {
                $initialized = $this->exportInitializeCustomized();
            } else {
                $initialized = $this->exportInitializeAll();
            }

            if (isset($this->config['supports_dbio_commands']) && $this->config['supports_dbio_commands'] === true) {
                $this->headers[] = 'v_dbio_command';
            }
        }
        if ($initialized === true) {
            $initialized = $this->exportFinalizeInitialization();
        }
        $additional_headers = (isset($this->config['additional_headers'])) ? ("\nAdditional Headers\n" . print_r($this->config['additional_headers'], true)) : '';
        $this->debugMessage(
            "exportInitialize ($language), " . (($initialized) ? 'Successful' : ('Unsuccessful (' . $this->message . ')')) . 
            "\nTables:\n" . print_r($this->tables, true) . 
            $additional_headers .
            "\nHeaders:\n" . print_r($this->headers, true)
        );
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
    public function exportFinalizeInitialization()
    {
        $this->debugMessage("exportFinalizeInitialization\n" . print_r($this, true));
        return true;
    }

    // -----
    // Gets and returns the header-record for the current export.  This is driven by whether the current DbIo handler's configuration
    // indicates that a header-record is to be included in the export.
    //
    // If no header is to be returned, the function returns (bool)false.  Otherwise, the exported record-count is incremented by 1 (to
    // reflect the header's output) and the array of header titles is returned to the caller.
    //
    public function exportGetHeader()
    {
        $header = false;
        if ($this->config['include_header'] === true) {
            $this->stats['record_count']++;
            $header = $this->headers;
        }
        return $header;
    }

    // -----
    // If properly initialized, creates and returns the SQL query associated with the current export.
    //
    public function exportGetSql($sql_limit = '')
    {
        if (!isset($this->export_language) || !isset($this->select_clause)) {
            dbioLogError('Export aborted: DbIo export sequence error; not previously initialized.');
        }

        $export_sql = 'SELECT ' . $this->select_clause . ' FROM ' . $this->from_clause;
        if ($this->where_clause !== '') {
            $export_sql .= ' WHERE ' . $this->where_clause;
      
        }
        if ($this->order_by_clause !== '') {
            $export_sql .= ' ORDER BY ' . $this->order_by_clause;

        }
        $export_sql .= " $sql_limit";
        $this->debugMessage("exportGetSql:\n$export_sql");
        return $export_sql;
    }

    // -----
    // This function, called just prior to writing each exported record, increments the count of records exported and
    // also makes sure that the encoding for the output is based on the character-set specified.
    //
    public function exportPrepareFields(array $fields)
    {
        $this->stats['record_count']++;
        if (isset($this->config['supports_dbio_commands']) && $this->config['supports_dbio_commands'] === true) {
            $fields[] = '';
        }
        return $this->exportEncodeData($fields);
    }

    public function importInitialize($language = 'all', $operation = 'check')
    {
        $this->logCharacterSetConfig();

        if (!isset($this->config)) {
            dbioLogError('Import aborted: DbIo helper not configured.');
        } elseif (!isset($this->config['keys']) || !is_array($this->config['keys'])) {
            dbioLogError('Import aborted: DbIo helper\'s "keys" configuration is not set or not an array.');
        }

        if ($this->version_mismatch === true) {
            $import_ok = false;
            $this->message = sprintf(DBIO_ERROR_HANDLER_VERSION_MISMATCH, $this->stats['report_name']);
            trigger_error('DbIoHandler version mismatch for handler "' . $this->stats['report_name'] . '".  Required: ' . $this->config['handler_version'] . ', Current: ' . self::DBIO_HANDLER_VERSION, E_USER_WARNING);
        } else {
            $this->message = '';
            if ($language === 'all' && count($this->languages) === 1) {
                reset($this->languages);
                $language = key($this->languages);
            }
            $this->operation = $operation;
            $this->stats['record_count'] = ($this->config['include_header']) ? 1 : 0;
            $this->stats['action'] = "import-$language-$operation";
            $this->unused_fields = [
                ', ' . self::DBIO_SPECIAL_IMPORT,
                ', ' . self::DBIO_NO_IMPORT
            ];

            $this->charset_is_utf8 = (dbio_strtolower(CHARSET) === 'utf-8');
            $this->headers = [];

            $this->handler_does_import = (isset($this->config['handler_does_import']) && $this->config['handler_does_import'] === true);
            $this->handler_overrides_import = (isset($this->config['handler_overrides_import']) && $this->config['handler_overrides_import'] === true);
            $import_ok = $this->importInitializeKeys();

            $extra_select_clause = '';
            if (isset($this->config['fixed_headers'])) {
                $no_header_fields = (isset($this->config['fixed_fields_no_header']) && is_array($this->config['fixed_fields_no_header'])) ? $this->config['fixed_fields_no_header'] : [];
                foreach ($this->config['fixed_headers'] as $field_name => $table_name) {
                    if (!in_array($field_name, $no_header_fields)) {
                        if ($table_name == self::DBIO_NO_IMPORT) {
                            $this->headers[] = $field_name;
                        } elseif ($table_name == self::DBIO_SPECIAL_IMPORT || !isset($this->config['tables'][$table_name]['language_field'])) {
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
            } elseif (!$this->handler_overrides_import) {
                foreach ($this->config['tables'] as $config_table_name => $config_table_info) {
                    if (isset($config_table_info['import_extra_keys_only'])) {
                        continue;
                    }
                    if ($this->tables[$config_table_name]['uses_language']) {
                        foreach ($this->languages as $language_code => $language_id) {
                            if ($language !== 'all' && $language !== $language_code) {
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

                if (isset($this->config['additional_headers']) && is_array($this->config['additional_headers'])) {
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
        $this->debugMessage("importInitialize completed" . ((!$import_ok) ? ' with errors' : '') . "\n" . print_r($this, true));
        return $import_ok;
    }

    public function importGetHeader($header)
    {
        if (!isset($this->headers)) {
            dbioLogError("Import aborted, sequencing error. Can't get the header before overall initialization.");
        }
        if (!is_array($header)) {
            $this->debugMessage('importGetHeader: No header included, using generated default.');
            $header = $this->headers;
        }

        $this->debugMessage("importGetHeader, using headers:\n" . print_r($header, true));
        $initialization_complete = true;

        if ($this->handler_overrides_import) {
            return $initialization_complete;
        }

        $this->import_sql_data = [];
        $this->table_names = [];
        $this->language_id = [];
        $this->header_field_count = 0;
        $this->key_index = false;
        $key_index = 0;
        foreach ($header as &$current_field) {
            $table_name = self::DBIO_NO_IMPORT;
            $field_language_id = 0;
            if (dbio_strpos($current_field, 'v_') !== 0 || dbio_strlen($current_field) < 3) {
                $current_field = self::DBIO_NO_IMPORT;
            } elseif ($current_field === 'v_dbio_command') {
                if (!isset($this->config['supports_dbio_commands']) || $this->config['supports_dbio_commands'] !== true) {
                    $this->debugMessage(DBIO_ERROR_HANDLER_NO_COMMANDS, self::DBIO_ERROR);
                    $this->message = DBIO_ERROR_HANDLER_NO_COMMANDS;
                    $initialization_complete = false;
                } else {
                    $current_field = self::DBIO_NO_IMPORT;
                    if (isset($this->dbio_command_index)) {
                        $this->debugMessage("importGetHeader, multiple v_dbio_command columns found; import cancelled.", self::DBIO_ERROR);
                        $this->message = DBIO_ERROR_MULTIPLE_COMMAND_COLUMNS;
                        $initialization_complete = false;
                    } else {
                        $this->dbio_command_index = $key_index;
                    }
                }
            } else {
                $current_field = dbio_substr($current_field, 2);  //-Strip off leading 'v_'
                $current_field_status = $this->importHeaderFieldCheck($current_field);
                if ($current_field_status === self::DBIO_NO_IMPORT ) {
                    $current_field = self::DBIO_NO_IMPORT;
                } elseif ($current_field_status === self::DBIO_SPECIAL_IMPORT) {
                    $table_name = self::DBIO_SPECIAL_IMPORT;
                } else {
                    $field_found = false;
                    foreach ($this->tables as $database_table_name => $table_info) {
                        if ($table_info['uses_language']) {
                            $field_language_code = dbio_substr($current_field, -2);
                            $field_name = dbio_substr($current_field, 0, -3);
                            if ($field_name === '') {
                                $current_field = self::DBIO_NO_IMPORT;
                                break;
                            }
                            foreach ($this->languages as $language_code => $language_id) {
                                if ($field_language_code !== $language_code) {
                                    continue;
                                }
                                if (array_key_exists($field_name, $table_info['fields'])) {
                                    $table_name = $database_table_name;
                                    $field_language_id = $language_id;
                                    $current_field = $field_name;
                                    $field_found = true;
                                    break;
                                }
                            }
                        } elseif (array_key_exists($current_field, $table_info['fields'])) {
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
                if ($table_name != self::DBIO_SPECIAL_IMPORT && $table_name !== self::DBIO_NO_IMPORT) {
                    if ((int)$field_language_id === 0) {
                        $import_table_name = $table_name;
                        if (!isset($this->import_sql_data[$table_name])) {
                            $this->import_sql_data[$table_name] = [];
                        }
                    } else {
                        $import_table_name = "$table_name^$field_language_id";
                        if (!isset($this->import_sql_data[$import_table_name])) {
                            $this->import_sql_data[$import_table_name] = [];
                        }
                    }

                    $field_type = false;
                    if (isset($this->tables[$table_name]) && isset($this->tables[$table_name]['fields'][$current_field])) {
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
                            case 'double':
                                $field_type = 'float';
                                break;
                            case 'date':
                            case 'datetime':
                                $field_type = $this->tables[$table_name]['fields'][$current_field]['data_type'];
                                break;
                            case 'enum':
                                $field_type = 'enum';
                                break;
                            case 'char':
                            case 'text':
                            case 'varchar':
                            case 'mediumtext':
                            case 'longtext':
                                $valid_string_field = true;  //-Indicate that a value string-type field was found and fall through to common processing
                            default:
                                $field_type = 'string';
                                if (!$valid_string_field) {
                                    $message = "Unknown datatype (" . $this->tables[$table_name]['fields'][$current_field]['data_type'] . ") for $table_name::$current_field on line #" . $this->stats['record_count'];
                                    $this->debugMessage("[*] importGetHeader: $message", self::DBIO_WARNING);
                                }
                                break;
                        }
                    }
                    if ($field_type === false) {
                        $this->debugMessage("[*] importGetHeader ($import_table_name, $current_field, $language_id): Can't resolve field type, ignoring.", self::DBIO_WARNING);
                    } else {
                        $this->import_sql_data[$import_table_name][$current_field] = [
                            'value' => '',
                            'type' => $field_type
                        ];
                    }

                    if (isset($this->variable_keys[$current_field])) {
                        $this->key_index = $key_index;
                        $this->variable_keys[$current_field]['index'] = $key_index;
                        $this->variable_keys[$current_field]['type'] = $field_type;
                    }
                }
            }  
            $key_index++;
        }
        $this->headers = $header;
        $this->header_columns = count($header);

        $missing_keys = '';
        foreach ($this->variable_keys as $field_name => $field_info) {
            if (!isset($field_info['index'])) {
                $missing_keys .= ', v_' . $field_name;
            }
        }
        if ($missing_keys !== '') {
            $this->message = sprintf(DBIO_ERROR_HEADER_MISSING_KEYS, dbio_substr($missing_keys, 2));
            $initialization_complete = false;
        } elseif ($this->header_field_count == 0) {
            $this->message = DBIO_MESSAGE_IMPORT_MISSING_HEADER;
            $initialization_complete = false;
        } elseif ($this->key_index === false) {
            $this->message = sprintf(DBIO_FORMAT_MESSAGE_IMPORT_MISSING_KEY, $this->key_field_names);
            $initialization_complete = false;
        }
        if ($initialization_complete === false) {
            $this->table_names = [];
        } else {
            $initialization_complete = $this->importFinalizeHeader();
        }

        $this->debugMessage(
            "importGetHeader: finished ($initialization_complete).\n" .
            print_r(
                [
                    'import_sql_data' => $this->import_sql_data, 
                    'table_names' => $this->table_names, 
                    'language_id' => $this->language_id, 
                    'header_field_count' => $this->header_field_count, 
                    'key_index' => $this->key_index,
                    'variable_keys' => $this->variable_keys,
                    'headers' => $this->headers
                ],
                true
            )
        );
        return $initialization_complete;
    }

    // -----
    // This function is the heart of the DbIo-Import handling, processing the current CSV record into the store's database.
    //
    public function importCsvRecord(array $data)
    {
        global $db;

        if (!isset($this->table_names)) {
            dbioLogError("Import aborted, sequencing error. Previous import-header initialization error.");
        }
        $this->debugMessage("importCsvRecord: starting ...");

        $data = ($this->charset_is_utf8 === true) ? $this->encoding->toUTF8($data) : $this->encoding->toWin1252($data, ForceUTF8\Encoding::ICONV_IGNORE_TRANSLIT);

        // -----
        // If the queryCache handler is loaded, reset the cache at the start of each record's import.  This helps to reduce
        // the amount of memory required for an import script's execution.
        //
        // Note: For older versions of the queryCache object, we need to reset the class's cache-array directly.
        //
        if ($this->queryCache !== null) {
            $this->queryCache->reset('ALL');
        } elseif ($this->queryCacheOlder !== null) {
            $this->queryCacheOlder->queries = [];
        }

        // -----
        // Indicate, initially, that the record is OK to import and increment the count of lines processed.  The last value
        // will be used in any debug-log information to note the location of any processing.
        //
        $this->record_status = true;
        $this->stats['record_count']++;
        $this->saved_data = [];

        // -----
        // Determine the "key" value associated with the record.  If there are fewer columns of data than are present in the
        // header, the record is not imported.
        //
        $key_index = $this->key_index;
        if ($this->handler_overrides_import === false && count($data) < count($this->headers)) {
            if (!empty(trim(implode('', $data)))) {
                $this->debugMessage(
                    'Data record at line #' .
                    $this->stats['record_count'] .
                    ' not imported.  Column count (' .
                    count($data) .
                    ') less than header column count (' .
                    count($this->headers) . ').',
                    self::DBIO_ERROR
                );
            }
        } else {
            // -----
            // Otherwise, determine the action to be performed for the current record.
            //
            // Normally, a handler specifies a single, unique key value to be used to "match" the record to be processed, but some
            // handlers also provide an "alternate" key that can be used to find a unique record (e.g. a products_model
            // field as an alternate for a products_id).
            //
            // When a matching database record is located, one of three conditions are possible:
            //
            // 1) No record matches.  The associated DbIo input record is processed as an INSERT into the database.
            // 2) A single record matches.  The associated DbIo input record is processed as an UPDATE to the database.
            // 3) Multiple records match.  This base class doesn't "know" enough to process the UPDATE to the database.
            //    The default handling (by this class) marks the import as an update, but also notes that the record can't
            //    be processed, due to the non-unique "key".  The record "might" be updated, depending on the currently-
            //    active handler's additional processing via the importCheckKeyValue function.  In this case, the class
            //    variable 'data_key_check' is set to contain the multiple-record SQL query result.
            //
            if ($this->handler_overrides_import === false) {
                unset($this->data_key_check);
                $data_key_check = $db->ExecuteNoCache($this->importBindKeyValues($data, $this->data_key_sql));
                switch ($data_key_check->RecordCount()) {
                    case 0:
                        $this->import_action = 'insert';
                        $this->import_is_insert = true;
                        $this->where_clause = '';
                        $this->key_fields = [];
                        break;
                    case 1:
                        $this->import_action = 'update';
                        $this->import_is_insert = false;
                        $this->where_clause = $this->importBindKeyValues($data, $this->key_where_clause);
                        $this->key_fields = $data_key_check->fields;
                        break;
                    default:
                        $this->debugMessage("Multiple records match the keys at line #" . $this->stats['record_count'] . "; update capability to be checked by the specific handler.");
                        $this->import_action = 'update';
                        $this->import_is_insert = false;
                        $this->where_clause = '';
                        $this->key_fields = [];
                        $this->record_status = false;
                        $this->data_key_check = $data_key_check;
                        break;
                }
            }

            // -----
            // First, give the current handler the opportunity to update the "key" value(s) associated with this record's DbIo operation, continuing
            // only if all is OK.
            //
            if ($this->importCheckKeyValue($data) !== false) {
                // -----
                // Check to see if DbIo commands are included in this import and, if so, check to see if the current to-be-imported
                // record includes a non-blank command; if so, pass that command off the the active handler.
                //
                // The handler might return nothing (null) or false, causing the import action for the current row to be
                // considered "finished" or boolean true to indicate that the current row's processing should continue.
                //
                $continue_import = true;
                if (isset($this->dbio_command_index) && $data[$this->dbio_command_index] !== '') {
                    $continue_import = $this->importHandleDbIoCommand($data[$this->dbio_command_index], $data);
                }
                if ($continue_import === true) {
                    // -----
                    // Otherwise ... loop, processing each 'column' of data into its respective database field(s).  At the end of this processing,
                    // we'll have a couple of sql-data arrays to be used as input to the database 'perform' function; that function will
                    // handle any conversions and quote-insertions required.
                    //
                    $data_index = 0;
                    foreach ($data as $current_element) {
                        if ($data_index > $this->header_columns) {
                            break;
                        }
                        if ($this->headers[$data_index] != self::DBIO_NO_IMPORT) {
                            $this->importProcessField($this->table_names[$data_index], $this->headers[$data_index], $this->language_id[$data_index], $current_element);
                        }
                        $data_index++;
                    }

                    // -----
                    // If the record didn't have errors preventing its insert/update ...
                    //
                    if ($this->record_status === true) {
                        if ($this->import_is_insert === true) {
                            $this->stats['inserts']++;
                        } else {
                            $this->stats['updates']++;
                        }

                        if ($this->handler_does_import === true || $this->handler_overrides_import === true) {
                            $this->importFinishProcessing();
                        } else {
                            $record_key_value = false;
                            foreach ($this->import_sql_data as $database_table => $table_fields) {
                                if ($database_table !== self::DBIO_NO_IMPORT && $database_table !== self::DBIO_SPECIAL_IMPORT) {
                                    $table_name = $database_table;
                                    $extra_where_clause = '';
                                    $capture_key_value = ($this->import_is_insert === true && isset($this->config['keys'][$database_table]['capture_key_value']));

                                    $language_id = false;
                                    if (dbio_strpos($table_name, '^') !== false) {
                                        $language_tables = explode('^', $table_name);
                                        $table_name = $language_tables[0];
                                        $language_id = $language_tables[1];
                                        if ($this->import_is_insert === true) {
                                            $table_fields[$this->config['tables'][$table_name]['language_field']] = [
                                                'value' => $language_id,
                                                'type' => 'integer'
                                            ];
                                        } else {
                                            $extra_where_clause = " AND " . $this->config['tables'][$table_name]['alias'] . '.' . $this->config['tables'][$table_name]['language_field'] . " = $language_id";
                                        }
                                    }

                                    // -----
                                    // Added in v1.6.0, as an 'aid' to the Products handler.  This should "really" be a parameter
                                    // supplied to the two class methods in the following section, but that would break upward
                                    // compatibility for any handlers that currently extend these methods.
                                    //
                                    $this->import_language_id = $language_id;

                                    $table_alias = $this->config['tables'][$table_name]['alias'];
                                    $table_fields = $this->importUpdateRecordKey($table_name, $table_fields, $record_key_value);
                                    if ($table_fields !== false) {
                                        $sql_query = $this->importBuildSqlQuery($table_name, $table_alias, $table_fields, $extra_where_clause);
                                        if ($sql_query !== false && $this->operation !== 'check') {
                                            $db->Execute($sql_query);
                                            if ($capture_key_value === true) {
                                                $record_key_value = $db->insert_ID();
                                            }
                                        }
                                    }
                                }
                            }
                            $this->importRecordPostProcess($record_key_value);
                        }
                    }
                }
            }  //-Record's key value is OK to continue processing
        }
    }

    // -----
    // This function, called as the final step in a CSV-import action, gives the current handler
    // the opportunity to perform some post-processing of the imported records.
    //
    public function importPostProcess()
    {
    }
  
// ----------------------------------------------------------------------------------
//                      P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------

    protected function exportEncodeData($fields)
    {
       return (DBIO_CHARSET === 'utf8') ? $this->encoding->toUTF8($fields) : $this->encoding->toWin1252($fields, ForceUTF8\Encoding::ICONV_IGNORE_TRANSLIT);
    }

    protected final static function loadHandlerMessageFile($handler_name)
    {
        include_once DIR_FS_DBIO_LANGUAGES . $_SESSION['language'] . '/dbio/DbIo' . $handler_name . 'Handler.php';
    }

    protected final function logCharacterSetConfig()
    {
        $this->debugMessage('Configured CHARSET (' . CHARSET . '), DB_CHARSET (' . DB_CHARSET . '), DBIO_CHARSET (' . DBIO_CHARSET . '), DEFAULT_LANGUAGE (' . DEFAULT_LANGUAGE . '), ' . dbio_get_string_info());
    }

    // -----
    // This abstract function, required to be supplied for the actual handler, is called during the class
    // construction to have the handler set its database-related configuration.
    //
    abstract protected function setHandlerConfiguration();

    protected function exportInitializeAll()
    {
        foreach ($this->config['tables'] as $config_table_name => $config_table_info) {
            $table_prefix = $this->exportInitializeFromClause($config_table_name, $config_table_info);

            if (!isset($this->config['fixed_headers'])) {
                if ($this->tables[$config_table_name]['uses_language']) {
                    $first_language = true;
                    foreach ($this->languages as $language_code => $language_id) {
                        if ($this->export_language !== 'all' && $this->export_language !== $language_code) {
                            continue;
                        }
                        foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
                            if ($field_info['include_in_export'] !== false) {
                                if ($first_language === true) {
                                    $this->select_clause .= "$table_prefix$current_field, ";
                                }
                                if ($field_info['include_in_export'] === true) {
                                    $this->headers[] = 'v_' . $current_field . '_' . $language_code;
                                }
                            }
                        }
                        $first_language = false;
                    }
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

        if (isset($this->config['fixed_headers'])) {
            $no_header_array = (isset($this->config['fixed_fields_no_header']) && is_array($this->config['fixed_fields_no_header'])) ? $this->config['fixed_fields_no_header'] : [];
            $current_language_code = ($this->export_language === 'all') ? $this->first_language_code : $this->export_language;
            foreach ($this->config['fixed_headers'] as $field_name => $table_name) {
                if ($table_name !== self::DBIO_SPECIAL_IMPORT) {
                    $field_alias = (isset($this->config['tables'][$table_name]['alias'])) ? ($this->config['tables'][$table_name]['alias'] . '.') : '';
                    $this->select_clause .= "$field_alias$field_name, ";
                }
                if (!in_array($field_name, $no_header_array)) {
                    $language_suffix = '';
                    if (isset($this->config['tables'][$table_name]['language_field'])) {
                        $language_suffix = "_$current_language_code";
                    }
                    $this->headers[] = "v_$field_name$language_suffix";
                }
            }
            $this->where_clause = $this->config['export_where_clause'];
            $this->order_by_clause = $this->config['export_order_by_clause'];
        }

        $this->from_clause = ($this->from_clause == '') ? '' : dbio_substr($this->from_clause, 0, -2);
        $this->select_clause = ($this->select_clause == '') ? '' : dbio_substr($this->select_clause, 0, -2);

        if (isset($this->config['additional_headers']) && is_array($this->config['additional_headers'])) {
            foreach ($this->config['additional_headers'] as $header_value => $flags) {
                if (!($flags & self::DBIO_FLAG_NO_EXPORT)) {
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
        return true;  //-Indicate that the export has been successfully initialized
    }

    protected function exportInitializeCustomized()
    {
        if (isset($this->config['fixed_headers'])) {
            $initialized = false;
            trigger_error("Handler configuration error; fixed_headers is incompatible with customized exports.", E_USER_WARNING);
        } else {
            $initialized = true;
            $available_fields = [];
            foreach ($this->config['tables'] as $config_table_name => $config_table_info) {
                $table_prefix = $this->exportInitializeFromClause($config_table_name, $config_table_info);

                $uses_language = $this->tables[$config_table_name]['uses_language'];

                foreach ($this->tables[$config_table_name]['fields'] as $current_field => $field_info) {
                    if ($field_info['include_in_export'] !== false) {
                        $available_fields[$current_field] = [
                            'uses_language' => $uses_language,
                            'select' => $table_prefix . $current_field,
                            'include_header' => ($field_info['include_in_export'] === true),
                            'is_additional_header' => false,
                        ];
                    }
                }
            }
 
            if (isset($this->config['additional_headers']) && is_array($this->config['additional_headers'])) {
                foreach ($this->config['additional_headers'] as $header_value => $flags) {
                    if (!($flags & self::DBIO_FLAG_NO_EXPORT)) {
                        $field_name = str_replace('v_', '', $header_value);
                        $this->config['additional_headers'][$header_value] |= self::DBIO_FLAG_NO_EXPORT;
                        $available_fields[$field_name] = [
                            'uses_language' => (boolean)($flags & self::DBIO_FLAG_PER_LANGUAGE),
                            'select' => ($flags & self::DBIO_FLAG_FIELD_SELECT) ? $this->config['additional_header_select'][$header_value] : false,
                            'include_header' => true,
                            'is_additional_header' => true,
                        ];
                    }
                }
            }

            foreach ($this->customized_fields as $current_field) {
                if (isset($available_fields[$current_field])) {
                    $field_info = $available_fields[$current_field];
                    if ($field_info['uses_language']) {
                        $first_language = true;
                        foreach ($this->languages as $language_code => $language_id) {
                            if ($this->export_language !== 'all' && $this->export_language !== $language_code) {
                                continue;
                            }
                            if ($first_language) {
                                if ($field_info['select'] !== false) {
                                    $this->select_clause .= ($field_info['select'] . ', ');
                                }
                                if ($field_info['is_additional_header']) {
                                    $this->config['additional_headers']['v_' . $current_field] &= ~self::DBIO_FLAG_NO_EXPORT;
                                }
                            }
                            if ($field_info['include_header'] === true) {
                                $this->headers[] = 'v_' . $current_field . '_' . $language_code;
                            }
                            $first_language = false;
                        }
                    } else {
                        if ($field_info['select'] !== false) {
                            $this->select_clause .= ($field_info['select'] . ', ');
                        }
                        if ($field_info['include_header'] === true) {
                            $this->headers[] = 'v_' . $current_field;
                        }
                        if ($field_info['is_additional_header'] === true) {
                            $this->config['additional_headers']['v_' . $current_field] &= ~self::DBIO_FLAG_NO_EXPORT;
                        }
                    }
                }
            }
            $this->from_clause = ($this->from_clause === '') ? '' : dbio_substr($this->from_clause, 0, -2);
            $this->select_clause = ($this->select_clause === '') ? '' : dbio_substr($this->select_clause, 0, -2);
        }
        $this->debugMessage(
            "exportInitializeCustomized" .
            "\nSelect clause: " . $this->select_clause .
            "\nCustomized Fields:\n" . print_r($this->customized_fields, true) .
            "\nHeaders:\n" . print_r($this->headers, true) .
            "\nAdditional headers:\n" . print_r($this->config['additional_headers'], true) .
            "\nAvailable Fields\n" . print_r($available_fields, true)
        );
        return $initialized;
    }

    protected function exportInitializeFromClause($config_table_name, $config_table_info)
    {
        $table_prefix = '';
        if (!isset($config_table_info['no_from_clause'])) {
            $this->from_clause .= $config_table_name;
            $table_prefix = (isset($config_table_info['alias']) && $config_table_info['alias'] != '') ? ($config_table_info['alias'] . '.') : '';
            if ($table_prefix !== '') {
                $this->from_clause .= ' AS ' . $config_table_info['alias'];
            }
            if (isset($config_table_info['join_clause'])) {
                $this->from_clause .= ' ' . $config_table_info['join_clause'];
            }
            $this->from_clause .= ', ';
        }
        return $table_prefix;
    }

    protected function insertBeforeKey($array, $key, $data)
    {
        $offset = array_search($key, array_keys($array));
        if ($offset === false) {
            $offset = count($array);
        }
        return array_merge(
            array_slice($array, 0, $offset),
            $data,
            array_slice($array, $offset)
        );
    }

    // -----
    // This function, provided by the detailed handler when it's set 'supports_dbio_commands', enables a handler to support
    // "special" functions (like "REMOVE") during its import processing.
    //
    // Starting with DbIoHandler v1.4.0, this function returns a boolean indication as to whether (true) or not (false)
    // the import should proceed normally.  Note that previous handlers implementing this function might not return anything,
    // in which case the return value is null.
    //
    protected function importHandleDbIoCommand($command, $data)
    {
        return false;
    }
 
    protected function importCheckKeyValue($data)
    {
        return $this->record_status;
    }

    protected function importUpdateRecordKey($table_name, $table_fields, $key_value) 
    {
        return $table_fields;
    }

    // -----
    // Retrieve the value associated with the specified field name in the current data record.
    //
    protected function importGetFieldValue($field_name, $data)
    {
        $this->debugMessage("importGetFieldValue for '$field_name' from " . print_r($data, true) . print_r($this->headers, true));
        $field_index = array_search($field_name, $this->headers);
        return ($field_index === false) ? false : $data[$field_index];
    }

    // -----
    // Retrieve the value associated with the specified language-specific field name in the current data record.
    //
    protected function importGetLanguageFieldValue($field_name, $language_id, $data)
    {
        $this->debugMessage("importGetLanguageFieldValue for '$field_name' ($language_id) from " . print_r($data, true) . print_r($this->headers, true) . print_r($this->language_ids, true));
        for ($i = 0, $field_index = false; $i < $this->header_field_count; $i++) {
            if ($this->headers[$i] === $field_name && $this->language_ids[$i] === $language_id) {
                $field_index = $i;
                break;
            }
        }
        return ($field_index === false) ? false : $data[$field_index];
    }

    protected function importInitializeKeys()
    {
        $keys_ok = true;
        $this->key_from_clause = $this->key_select_clause = $this->key_where_clause = '';
        $key_number = 0;
        $this->alternate_key_included = false;
        $this->variable_keys = [];
        $this->key_field_names = '';
        if (!$this->handler_overrides_import) {
            foreach ($this->config['keys'] as $table_name => $table_key_fields) {
                $table_alias = $table_key_fields['alias'];
                $this->key_from_clause .= "$table_name AS $table_alias, ";
                foreach ($table_key_fields as $key_field_name => $key_field_attributes) {
                    // -----
                    // Bypass "special" entries within the configuration's keys.
                    //
                    if ($key_field_name === 'alias' || $key_field_name === 'capture_key_value') {
                        continue;
                    }
                    $this->key_field_names .= "$key_field_name, ";
                    $key_type = $key_field_attributes['type'];
                    $this->alternate_key_included = $this->alternate_key_included || ($key_type & self::DBIO_KEY_IS_ALTERNATE) == self::DBIO_KEY_IS_ALTERNATE;
                    if ($key_type & self::DBIO_KEY_IS_VARIABLE) {
                        $key_match_variable = ":key_value$key_number:";
                        $key_number++;
                        if ($this->key_where_clause !== '') {
                            $this->key_where_clause .= (($key_type & self::DBIO_KEY_IS_ALTERNATE) ? ' OR ' : ' AND ');
                        }
                        $this->key_where_clause .= "$table_alias.$key_field_name = $key_match_variable";
                        $this->variable_keys[$key_field_name] = [
                            'table_name' => $table_name, 
                            'match_variable_name' => $key_match_variable, 
                            'key_is_alternate' => (bool)($key_type & self::DBIO_KEY_IS_ALTERNATE) 
                        ];
                    } elseif ($key_type & self::DBIO_KEY_IS_FIXED) {
                        if ($this->key_where_clause !== '') {
                            $this->key_where_clause .= ' AND ';
                        }
                        $this->key_where_clause .= "$table_alias.$key_field_name = " . $key_field_attributes['match_fixed_key'];
                    } elseif (!($key_type & self::DBIO_KEY_IS_MASTER)) {
                        $keys_ok = false;
                        $this->message = sprintf('Unknown key field type (%1$u) for %2$s::%3$s.', $field_type, $table_name, $key_field_name);
                    }
                    if ($key_type & (self::DBIO_KEY_SELECTED | self::DBIO_KEY_IS_MASTER)) {
                        $this->key_select_clause .= "$table_alias.$key_field_name, ";
                    }
                }
            }
            if ($keys_ok === false || $this->key_from_clause === '' || $this->key_select_clause === '' || $this->key_where_clause === '') {
                $keys_ok = false;
                $this->message = ($this->message === '') ? DBIO_MESSAGE_KEY_CONFIGURATION_ERROR : $this->message;
            } else {
                $this->key_from_clause = dbio_substr($this->key_from_clause, 0, -2);  //-Strip trailing ', '
                $this->key_select_clause = dbio_substr($this->key_select_clause, 0, -2);
                $this->key_field_names = dbio_substr($this->key_field_names, 0, -2);

                $this->data_key_sql = 
                    "SELECT " . $this->key_select_clause . "
                       FROM " . $this->key_from_clause . "
                      WHERE " . $this->key_where_clause . (($this->alternate_key_included === true) ? '' : ' LIMIT 1');
            }
        }
        return $keys_ok;
    }

    // -----
    // Called as the very last step of an import's header processing (so long as no previous error), giving a handler
    // one last time to verify that the header is acceptable.  Return true if the initialization is successful; false, otherwise.
    //
    protected function importFinalizeHeader()
    {
        return true;
    }

    protected function importBindKeyValues($data, $sql_template)
    {
        global $db;

        foreach ($this->variable_keys as $field_name => $field_info) {
            $sql_template = $db->bindVars($sql_template, $field_info['match_variable_name'], $data[$field_info['index']], $field_info['type']);
        }
        return $sql_template;
    }

    protected function importBuildSqlQuery($table_name, $table_alias, $table_fields, $extra_where_clause = '', $is_override = false, $is_insert = true)
    {
        global $db;

        $record_is_insert = ($is_override === true) ? $is_insert : $this->import_is_insert;
        if ($record_is_insert === true) {
            $sql_query = "INSERT INTO $table_name (`" . implode('`, `', array_keys($table_fields)) . "`)\nVALUES (";
            $sql_query = str_replace($this->unused_fields, '', $sql_query);
            foreach ($table_fields as $field_name => $field_info) {
                switch ($field_info['type']) {
                    case 'integer':
                        $field_value = (int)$field_info['value'];
                        break;
                     case 'float':
                        $field_value = (float)$field_info['value'];
                        break;
                     case 'date':           //-Fall-through ...
                     case 'datetime':
                        $field_value = $field_info['value'];
                        if ($field_value != 'null' && $field_value != 'NULL' && $field_value != 'now()') {
                            $field_value = "'" . $db->prepare_input($field_value) . "'";
                        }
                        break;
                    default:
                        $field_value = $field_info['value'];
                        if ($field_value != 'null' && $field_value != 'NULL') {
                            $field_value = "'" . $db->prepare_input($field_value) . "'";
                        }
                        break;
                }
                $sql_query .= "$field_value, ";
            }
            $sql_query = dbio_substr($sql_query, 0, -2) . ")";
        } else {
            $sql_query = "UPDATE $table_name $table_alias SET ";
            $where_clause = $this->where_clause;
            foreach ($table_fields as $field_name => $field_info) {
                if ($field_name !== self::DBIO_NO_IMPORT && (!isset($this->variable_keys[$field_name]) || $this->variable_keys[$field_name]['key_is_alternate'] === true)) {
                    switch ($field_info['type']) {
                        case 'integer':
                            $field_value = (int)$field_info['value'];
                            break;
                         case 'float':
                            $field_value = (float)$field_info['value'];
                            break;
                         case 'date':
                         case 'datetime':
                            $field_value = $field_info['value'];
                            if ($field_value !== 'null' && $field_value !== 'NULL' && $field_value !== 'now()') {
                                $field_value = "'" . $db->prepare_input($field_value) . "'";
                            }
                            break;
                        default:
                            $field_value = $field_info['value'];
                            if ($field_value != 'null' && $field_value != 'NULL') {
                                $field_value = "'" . $db->prepare_input($field_value) . "'";
                            }
                            break;
                    }
                    $sql_query .= "`$field_name` = $field_value, ";
                }
            }
            $sql_query = dbio_substr($sql_query, 0, -2) . ' WHERE ' . $where_clause . $extra_where_clause;
        }
        $this->debugMessage("importBuildSqlQuery ($table_name, " . print_r($table_fields, true));

        // -----
        // Force the generated SQL to be logged, but only when the import is being checked.  For
        // full-import processing, this log could be a performance issue for large imports.
        //
        $log_status = ($this->operation === 'check') ? self::DBIO_STATUS : self::DBIO_INFORMATIONAL;
        $this->debugMessage("importBuildSqlQuery for $table_name:\n$sql_query", $log_status);
        return $sql_query;
    }

    protected function importRecordPostProcess ($key_value)
    {
    }

    // -----
    // This function, required/used **only** if the current handler sets its config['handler_does_import'] to true, is called to process the
    // current record's import.
    //
    // Note: If a handler sets config['handler_does_import'] = true and does NOT supply this function, an error log will be generated.
    //
    protected function importFinishProcessing()
    {
        $this->debugMessage(sprintf(DBIO_ERROR_HANDLER_MISSING_FUNCTION, $this->stats['report_name'], 'importFinishProcessing'), self::DBIO_ERROR);
    }

    protected function importProcessField($table_name, $field_name, $language_id, $field_value)
    {
        $this->debugMessage("importProcessField ($table_name, $field_name, $language_id, $field_value)");
        if ($table_name === self::DBIO_SPECIAL_IMPORT) {
            $this->importAddField($table_name, $field_name, $field_value);
        } elseif ($table_name !== self::DBIO_NO_IMPORT) {
            if ($language_id == 0) {
                $import_table_name = $table_name;
                if (!isset($this->import_sql_data[$table_name])) {
                    $this->import_sql_data[$table_name] = [];
                }
            } else {
                $import_table_name = "$table_name^$language_id";
                if (!isset($this->import_sql_data[$import_table_name])) {
                    $this->import_sql_data[$import_table_name] = [];
                }
            }
            $field_type = $this->import_sql_data[$import_table_name][$field_name]['type'];
            $field_error = false;

            $this->debugMessage("importProcessField, current field type: $field_type");

            switch ($field_type) {
                case 'integer':
                    if ($this->tables[$table_name]['fields'][$field_name]['nullable'] && $field_value === '') {
                        $field_value = 'null';
                        break;
                    }
                    if (!preg_match('/^-?\d+$/', $field_value)) {
                        $field_error = true;
                        $this->debugMessage("[*] $import_table_name.$field_name, line #" . $this->stats['record_count'] . ": Value ($field_value) is not an integer", self::DBIO_ERROR);
                    }
                    break;
                case 'float':
                    if ($this->tables[$table_name]['fields'][$field_name]['nullable'] && $field_value === '') {
                        $field_value = 'null';
                        break;
                    }
                    if (!preg_match('/^-?(?:\d+|\d*\.\d+)$/', $field_value)) {
                        $field_error = true;
                        $this->debugMessage("[*] $import_table_name.$field_name, line #" . $this->stats['record_count'] . ": Value ($field_value) is not a floating-point value.", self::DBIO_ERROR);
                    }
                    break;
                case 'date':                //-Fall-through for date-related processing
                case 'datetime':
                    $formatted_field_value = $this->formatValidateDate($field_value, $field_type, $this->tables[$table_name]['fields'][$field_name]['nullable']);
                    if ($formatted_field_value === false) {
                        $this->debugMessage("[*] $import_table_name.$field_name, line #" . $this->stats['record_count'] . ": Value ($field_value) is not a recognized date value; the default value for the field will be used.", self::DBIO_WARNING);
                        $formatted_field_value = $this->tables[$table_name]['fields'][$field_name]['default'];
                        if ($formatted_field_value === '') {
                            $formatted_field_value = 'null';
                        }
                    }
                    $field_value = $formatted_field_value;
                    break;
                case 'string':
                    if ($this->tables[$table_name]['fields'][$field_name]['nullable'] && ($field_value == 'null' || $field_value == 'NULL')) {
                        break;
                    }
                    $max_field_length = $this->tables[$table_name]['fields'][$field_name]['max_length'];
                    if (dbio_strlen($field_value) > $max_field_length) {
                        $this->debugMessage("[*] $import_table_name.$field_name, line#" . $this->stats['record_count'] . ": The value ($field_value) exceeds the field's maximum length ($max_field_length); the value will be truncated.", self::DBIO_WARNING);
                        $field_value = dbio_substr($field_value, 0, $max_field_length);
                    }
                    break;
                case 'enum':
                    if ($this->tables[$table_name]['fields'][$field_name]['nullable'] && $field_value === '') {
                        $field_value = 'null';
                        break;
                    }
                    if (!in_array($field_value, $this->tables[$table_name]['fields'][$field_name]['enum_values'])) {
                        $field_error = true;
                        $this->debugMessage("[*] $import_table_name.$field_name, line#" . $this->stats['record_count'] . ": Value ($field_value) is not one of the field's enumerated values (" . implode(',', $this->tables[$table_name]['fields'][$field_name]['enum_values']) . ").", self::DBIO_ERROR);
                    }
                    break;
                default:
                    $field_error = true;
                    $message = "Unknown datatype (" . $field_type . ") for $table_name::$field_name on line #" . $this->stats['record_count'];
                    $this->debugMessage("[*] importProcessField: $message", self::DBIO_ERROR);
                    break;
            }

            if ($field_error === true) {
                $this->record_status = false;
            } else {
                $this->importAddField($import_table_name, $field_name, $field_value);
            }
        }
    }

    protected function importAddField($table_name, $field_name, $field_value)
    {
        $this->debugMessage("importAddField ($table_name, $field_name, $field_value)");
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
    protected function importHeaderFieldCheck($field_name)
    {
        return self::DBIO_IMPORT_OK;
    }

// ----------------------------------------------------------------------------------
//                      P R I V A T E   F U N C T I O N S
// ----------------------------------------------------------------------------------

    private function initializeDbIo()
    {
        $this->message = '';
        $this->version_mismatch = false;

        if (!isset($this->config) || !is_array($this->config) || 
            !( (isset($this->config['tables']) && is_array($this->config['tables'])) ||
               (isset($this->config['fixed_headers']) && is_array($this->config['fixed_headers'])) ) ) {
            dbioLogError('DbIo configuration not set prior to initialize.  Current class: ' . print_r($this, true));
        }

        $this->config['operation'] = null;

        if (!isset($this->config['include_header'])) {
            $this->config['include_header'] = true;
        }
        if (!isset($this->config['delimiter'])) {
            $this->config['delimiter'] = (DBIO_CSV_DELIMITER === 'TAB') ? "\t" : DBIO_CSV_DELIMITER;
        }
        if (!isset($this->config['enclosure'])) {
            $this->config['enclosure'] = DBIO_CSV_ENCLOSURE;
        }
        if (!isset($this->config['escape'])) {
            $this->config['escape'] = DBIO_CSV_ESCAPE;
        }
        $this->config['export_only'] = (isset($this->config['export_only']) && $this->config['export_only'] === true);

        if (isset($this->config['tables'])) {
            $this->tables = [];
            foreach ($this->config['tables'] as $table_name => $table_info) {
                $this->initializeTableFields($table_name, $table_info);
            }
        }

        if (!isset($this->config['handler_version']) || $this->config['handler_version'] > self::DBIO_HANDLER_VERSION) {
            $this->version_mismatch = true;
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
    private function initializeTableFields($table_name, $table_config)
    {
        global $db;

        $field_overrides = (isset($table_config['io_field_overrides']) && is_array($table_config['io_field_overrides'])) ? $table_config['io_field_overrides'] : false;
        $key_fields_only = (isset($table_config['key_fields_only'])) ? $table_config['key_fields_only'] : false;
        $table_keys = (isset($this->config['keys'][$table_name])) ? $this->config['keys'][$table_name] : [];
        $this->tables[$table_name] = [];
        $this->tables[$table_name]['fields'] = [];
        $uses_language = false;
        
        $sql_query = "
             SELECT COLUMN_NAME as `column_name`, COLUMN_DEFAULT as `default`, IS_NULLABLE as `nullable`, DATA_TYPE as `data_type`, 
                    CHARACTER_MAXIMUM_LENGTH as `max_length`, NUMERIC_PRECISION as `precision`, COLUMN_KEY as `key`, EXTRA as `extra`,
                    COLUMN_TYPE as `column_type`
               FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_SCHEMA = '" . DB_DATABASE . "' 
                AND TABLE_NAME = '$table_name'
           ORDER BY ORDINAL_POSITION";
        $table_info = $db->Execute($sql_query);
        foreach ($table_info as $next_table) {
            $column_name = $next_table['column_name'];
            if ($column_name == 'language_id' || $column_name == 'languages_id') {
                $uses_language = true;
            }
            if ("$table_name.$column_name" == TABLE_PRODUCTS_DESCRIPTION . '.products_id') {
                $next_table['extra'] = '';
            }
            unset($table_info->fields['column_name']);
            if ($key_fields_only === true) {
                $next_table['include_in_export'] = isset($table_keys[$column_name]);
            } else {
                $next_table['include_in_export'] = ($field_overrides !== false && isset($field_overrides[$column_name])) ? $field_overrides[$column_name] : true;
            }

            // -----
            // If the field is an 'enum' type, the possible 'enum' (character) values are present in the 'column_type'
            // element returned.  That value is formatted in a manner similar to:
            //
            // enum('lbs','kgs')
            //
            // ... and the allowed values will be one of lbs or kgs (without the quotes).
            //
            if ($next_table['data_type'] == 'enum') {
                $enum_values = str_replace(['enum(', "'"], '', $next_table['column_type']);
                $next_table['enum_values'] = explode(',', rtrim($enum_values, ')'));
            }
            unset($next_table['column_type']);

            $next_table['nullable'] = ($next_table['nullable'] === 'YES' || $next_table['nullable'] === 'TRUE');
            $next_table['sort_order'] = 0;
            $this->tables[$table_name]['fields'][$column_name] = $next_table;
        }
        $language_override = (isset($this->config['tables'][$table_name]['language_override']) && $this->config['tables'][$table_name]['language_override'] === self::DBIO_OVERRIDE_ALL);
        $this->tables[$table_name]['uses_language'] = ($language_override === false && $uses_language === true);
    }  //-END function initializeTableFields

    // -----
    // Function to insert an array after a specified key in another array.
    //
    // $input ....... The base array, into which the additional array is to be inserted
    // $after_key ... The key in the input array after which the insert input is inserted
    // $insert ...... Either an associative array or a string value, depending on the type of the $input array
    //
    // Returns:
    // false ........... If either input is not an array or the key is not found in the input
    // array ........... The input array, with the insert element inserted after the specified key
    //
    protected function arrayInsertAfter($input, $after_key, $insert)
    {
       $return_value = false;
        if (is_array($input)) {
            if (is_array($insert)) {
                $key_indices = array_flip(array_keys($input));
                if (isset($key_indices[$after_key])) {
                    $offset = $key_indices[$after_key];
                    $return_value = array_slice($input, 0, $offset+1, true) + $insert + array_slice($input, $offset+1, null, true);
                }
            } else {
                $offset = array_search($after_key, $input);
                if ($offset !== false) {
                    $return_value = array_slice($input, 0, $offset+1);
                    $return_value[] = $insert;
                    $return_value = array_merge($return_value, array_slice($input, $offset+1));
                }
            }
        }
        return $return_value;
    }

    protected function headerInsertColumns($after_key, $insert_array)
    {
        if (array_search($after_key, $this->headers) === false) {
            dbioLogError("Unknown key ($after_key) requested");
        } else {
            foreach ($insert_array as $current_value) {
                $this->headers = $this->arrayInsertAfter($this->headers, $after_key, $current_value);
                $after_key = $current_value;
            }
        }
    }

    // -----
    // Function to insert an associative-array element into the specified array at the location
    // identified in the current non-language customized field-list.
    //
    // Notes: 
    // 1) The $fields array is an associative key/value pair, keyed on the field's name.  The
    //    class' customized_fields array is numerically indexed, based on the to-be-exported report's
    //    previously-determined header-order.
    // 2) The calling handler has ensured that the $fields array contains all to-be-exported fields
    //    prior to (i.e. to the left of) the to-be-added field, if customized fields are in effect.
    //
    protected function insertAtCustomizedPosition($fields, $field_name, $field_value)
    {
        // -----
        // If this isn't a customized-template export, simply record the field's value for its name.
        //
        if (!(isset($this->customized_fields) && is_array($this->customized_fields))) {
            $fields[$field_name] = $field_value;
        // -----
        // Otherwise, processing a customized export ...
        //
        } else {
            // -----
            // Determine the location within the data for this customized field, continuing only if
            // the field is actually specified in the CSV's header.
            //
            $field_position = array_search('v_' . $field_name, $this->headers);
            if ($field_position !== false) {
                // -----
                // If the current field is the first 'column' data, then it's the first element of the
                // to-be-output CSV record, nothing to copy before it.
                //
                $field_keys = array_keys($fields);
                $field_count = count($fields);
                $updated_fields = [];
                $next_field = 0;
                if ($field_position !== 0) {
                     for ($next_field = 0; $next_field < $field_position; $next_field++) {
                        $next_field_name = $field_keys[$next_field];
                        $updated_fields[$next_field_name] = $fields[$next_field_name];
                    }
                }

                // -----
                // Next, insert the requested key/value pair into the updated fields' array.
                //
                $updated_fields[$field_name] = $field_value;
 
                // -----
                // Finally, copy the remaining elements of the input fields' array after that insertion
                // and set the reconstructed array as the method's return value.
                //
                for (; $next_field < $field_count; $next_field++) {
                    $next_field_name = $field_keys[$next_field];
                    $updated_fields[$next_field_name] = $fields[$next_field_name];
                }
                $fields = $updated_fields;
            }
        }
        return $fields;
    }
}
