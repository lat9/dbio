<?php

declare(strict_types=1);
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2026, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.2.0
//
if (!defined('IS_ADMIN_FLAG')) {
    exit('Illegal access');
}

class DbIo
{
    public object $handler;

    protected array $csv_parms;
    protected string $dbio_type;
    protected string $export_filename;

    protected $export_fp;   //- A file-pointer `resource`, but that type isn't available for type-hinting.
    protected string $export_sql;
    protected string $file_suffix;

    protected $import_fp;   //- A file-pointer `resource`, but that type isn't available for type-hinting.
    protected bool $initialized = false;
    protected string $message = '';
    protected bool $using_mbstring;

    public function __construct(string $dbio_type = '', string $file_suffix = '')
    {
        $this->file_suffix = (($file_suffix === '') ? '' : ($file_suffix . '.')) . date('Ymd-His-') . mt_rand(1000,999999);
 
        global $languageLoader;
        $languageLoader->loadExtraLanguageFiles(DIR_FS_DBIO_LANGUAGES, $_SESSION['language'], FILENAME_DBIO_MESSAGES, '/dbio');

        spl_autoload_register([$this, 'autoloadDbIoClasses']);

        if (!function_exists('mb_internal_encoding')) {
            require_once DIR_WS_FUNCTIONS . 'dbio_string_functions.php';
            $this->using_mbstring = false;
        } else {
            require_once DIR_WS_FUNCTIONS . 'dbio_mb_string_functions.php';
            dbio_string_initialize();
            $this->using_mbstring = true;
        }
        $this->initializeConfig($dbio_type);
    }

    protected function autoloadDbIoClasses($class_name): void
    {
        if (!class_exists($class_name) && file_exists(DIR_FS_DBIO_CLASSES . $class_name . '.php')) {
            require_once DIR_FS_DBIO_CLASSES . $class_name . '.php';
        }
    }

    // -----
    // Returns the last message issued by the DbIo processing.
    //
    public function getMessage(): string
    {
        return ($this->message === '') ? $this->handler->getHandlerMessage() : $this->message;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    // -----
    // Returns some basic information about the DbIo handlers available.
    //
    // Note: A handler can "refuse" availability if it's missing some of its pre-requisites by returning
    // a non-array value (like false) in its getHandlerInformation function.
    //
    public function getAvailableHandlers(): array
    {
        $handlers = glob(DIR_FS_DBIO_CLASSES . 'DbIo*Handler.php');
        $handler_info = [];
        if (is_array($handlers)) {
            foreach ($handlers as $current_handler) {
                $handler_class = str_replace([DIR_FS_DBIO_CLASSES, '.php'], '', $current_handler);
                if ($handler_class !== 'DbIoHandler') {
                    $dbio_type = str_replace(['DbIo', 'Handler'], '', $handler_class);
                    $current_handler_info = $handler_class::getHandlerInformation();
                    if (is_array($current_handler_info)) {
                        $handler_info[$dbio_type] = $current_handler_info;
                        $handler_info[$dbio_type]['class_name'] = $handler_class;
                    }
                }
            }
        }
        $this->message = (count($handler_info) === 0) ? DBIO_MESSAGE_NO_HANDLERS_FOUND : '';
        return $handler_info;
    }

    // -----
    // Function to initialize the configuration settings for a given import type.  A given import type's configuration
    // is controlled by a file named class.dbio.$dbio_type.php, present in the DIR_FS_DBIO directory. That class-file
    // is a dbio_handler-class object.
    //
    // This function also calls a helper-function to ensure that the directories used by the DbIo for its operation
    // exist and are writable.
    //
    protected function initializeConfig(string $dbio_type): bool
    {
        unset($this->handler);
        $this->message = '';

        $this->dbio_type = $dbio_type;
        if ($this->directoryCheck() === false) {
            $this->initialized = false;
        } elseif ($this->dbio_type === '') {
            $this->initialized = true;
        } else {
            $handler_classname = 'DbIo' . $dbio_type . 'Handler';
            $dbio_handler = DIR_FS_DBIO_CLASSES . $handler_classname . '.php';
            if (!file_exists($dbio_handler)) {
                $this->message = sprintf(DBIO_FORMAT_MESSAGE_NO_HANDLER, $dbio_handler);
                trigger_error($this->message, E_USER_WARNING);
            } elseif (!class_exists($handler_classname)) {
                $this->message = sprintf(DBIO_FORMAT_MESSAGE_NO_CLASS, $handler_classname, $dbio_handler);
                trigger_error($this->message, E_USER_WARNING);
            } else {
                $this->initialized = true;
                $this->handler = new $handler_classname($this->file_suffix);
                if (!method_exists($this->handler, 'debugMessage')) {
                    dbioLogError("DbIo handler ($handler_classname) missing the \"debugMessage\" method; terminating.");
                }
            }
        }
        return $this->initialized;
    }

    // -----
    // This function checks to ensure that the DbIo's input/output directories are present and writable, returning
    // a boolean indicator (true == OK, false == not-OK).
    //
    protected function directoryCheck(): bool
    {
        $ok = false;
        if (!is_dir(DIR_FS_DBIO)) {
            $this->message = sprintf(DBIO_ERROR_MISSING_DIRECTORY, DIR_FS_DBIO);
        } elseif (!is_writable(DIR_FS_DBIO)) {
            $this->message = sprintf(DBIO_ERROR_DIRECTORY_NOT_WRITABLE, DIR_FS_DBIO);
        } elseif (!is_dir(DIR_FS_DBIO_LOGS)) {
            $this->message = sprintf(DBIO_ERROR_MISSING_DIRECTORY, DIR_FS_DBIO_LOGS);
        } elseif (!is_writable(DIR_FS_DBIO_LOGS)) {
            $this->message = sprintf(DBIO_ERROR_DIRECTORY_NOT_WRITABLE, DIR_FS_DBIO_LOGS);
        } else {
            $ok = true;
        }
        return $ok;
    }

    // -----
    // Function that handles an export for the currently-active DbIo type.  The function takes an input that determines
    // where the exported data "goes", either to a 'file' or 'download' to auto-download the generated .csv file.
    //
    public function dbioExport(string $export_to = 'file', string $language = 'all'): array
    {
        global $db;

        $completion_code = false;
        $this->message = '';
        if ($this->initialized === false) {
            $this->message = DBIO_MESSAGE_EXPORT_NOT_INITIALIZED;
            trigger_error($this->message, E_USER_WARNING);
        } elseif ($this->handler->exportInitialize($language)) {
            $this->handler->startTimer();

            $this->csv_parms = $this->handler->getCsvParameters();
            $this->export_sql = $this->handler->exportGetSql();
            $export_info = $db->Execute($this->export_sql);
            if ($export_info->EOF) {
                $this->message = DBIO_EXPORT_NOTHING_TO_DO;
            } else {
                $this->export_filename = 'dbio.' . $this->dbio_type . '.' . $this->file_suffix . '.csv';
                $this->export_fp = fopen(DIR_FS_DBIO . $this->export_filename, 'wb+');
                if ($this->export_fp === false) {
                    $this->message = sprintf(DBIO_FORMAT_MESSAGE_EXPORT_NO_FP, DIR_FS_DBIO_EXPORT . $this->export_filename);
                    trigger_error($this->message, E_USER_WARNING);
                } else {
                    $this->debugMessage('dbioExport: Begin CSV creation loop.');
                    ini_set('max_execution_time', zen_config('DBIO_MAX_EXECUTION_TIME'));

                    $this->writeCsvRecord($this->handler->exportGetHeader());
                    foreach ($export_info as $next_record) {
                        $this->writeCsvRecord($this->handler->exportPrepareFields($next_record));
                    }
                    $completion_code = true;
                    $this->debugMessage('dbioExport: Finished CSV creation loop.');
                }
                unset($export_info);

                if ($completion_code !== false && $export_to === 'download') {
                    if (dbio_strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
                        header('Content-Type: text/csv; charset=' . ((zen_config('DBIO_CHARSET') === 'utf8') ? 'UTF-8' : 'Windows-1252'));
                        header('Content-Disposition: attachment; filename="' . $this->export_filename . '"');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                        header('Pragma: public');
                        header("Content-Length: " . filesize(DIR_FS_DBIO_EXPORT . $this->export_filename));
                    } else {
                        header('Content-Type: text/csv; charset=' . ((zen_config('DBIO_CHARSET') === 'utf8') ? 'UTF-8' : 'Windows-1252'));
                        header('Content-Disposition: attachment; filename="' . $this->export_filename . '"');
                        header('Expires: 0');
                        header('Pragma: no-cache');
                        header("Content-Length: " . filesize(DIR_FS_DBIO_EXPORT . $this->export_filename));
                    }
                    rewind($this->export_fp);
                    fpassthru($this->export_fp);
                    fclose($this->export_fp);
                    exit();
                }
                if ($this->export_fp !== false) {
                    fclose($this->export_fp);
                    $this->export_fp = false;
                }
            }
            $this->handler->stopTimer();
        }
        if ($completion_code === false && $this->message === '') {
            $this->message = $this->handler->getHandlerMessage();
        }
        return [
            'status' => $completion_code,
            'export_filename' => !empty($this->export_filename) ? $this->export_filename : '',
            'message' => $this->message,
            'io_errors' => $this->handler->getIOErrors(),
            'stats' => $this->handler->stats,
            'handler' => $this->dbio_type,
        ];
    }

    // -----
    // Import processing sequencer for the specified file.  The operation can be one of
    // 'check' (check-only, no database update), 'run' (runs the import).  The language
    // is specified as either 'all' (all store languages) or by the 2-character ISO code
    // associated with the language, e.g. 'en' for English or 'es' for Spanish.
    //
    public function dbioImport(string $filename, string $operation = 'check', string $language = 'all'): array
    {
        $completion_code = false;
        $this->message = '';
        $import_file = DIR_FS_DBIO . $filename;
        if ($this->initialized === false) {
            $this->message = DBIO_MESSAGE_IMPORT_NOT_INITIALIZED;
            trigger_error($this->message, E_USER_WARNING);
        } else {
            $this->handler->startTimer();
            if (!file_exists($import_file)) {
                $this->message = sprintf(DBIO_FORMAT_MESSAGE_IMPORT_FILE_MISSING, $import_file);
                trigger_error($this->message, E_USER_WARNING);
            } elseif (($this->import_fp = fopen($import_file, 'r')) === false) {
                $this->message = sprintf(DBIO_FORMAT_MESSAGE_EXPORT_NO_FP, $import_file);
                trigger_error($this->message, E_USER_WARNING);
            } else {
                $import_ok = $this->handler->importInitialize($language, $operation);
                if ($import_ok === false) {
                    $this->message = $this->handler->getHandlerMessage();
                } else {
                    $this->csv_parms = $this->handler->getCsvParameters();
                    if (!$this->handler->importGetHeader(($this->handler->isHeaderIncluded()) ? $this->getCsvRecord() : false)) {
                        $this->message = $this->handler->getHandlerMessage();
                    } else {
                        ini_set('max_execution_time', zen_config('DBIO_MAX_EXECUTION_TIME'));
                        while (($data = $this->getCsvRecord()) !== false) {
                            $this->handler->importCsvRecord($data);
                        }
                        $completion_code = true;
                    }
                }
                fclose($this->import_fp);

                if ($import_ok === true) {
                    $this->handler->importPostProcess();
                }
            }
            $this->handler->stopTimer();
        }
        return [
            'status' => $completion_code,
            'message' => $this->message,
            'io_errors' => $this->handler->getIOErrors(),
            'stats' => $this->handler->stats,
            'handler' => $this->dbio_type
        ];
    }

    // -----
    // Redirect any debug-messages from this level of processing to the handler's message handling, so that
    // all messages for a given import are recorded in a single location.
    //
    protected function debugMessage(string $message): void
    {
        $this->handler->debugMessage($message);
    }

    // -----
    // Write the specified array (or arrays!) of data to the current export .csv file.
    //
    private function writeCsvRecord(false|array $csv_record): void
    {
        if (is_array($csv_record) && count($csv_record) !== 0) {
            // -----
            // Regardless of the current PHP version, if the first element of the to-be-written information is, itself,
            // an array then the ASSUMPTION is that the export has returned an array of CSV records to be written.
            //
            if (isset($csv_record[0]) && is_array($csv_record[0])) {
                foreach ($csv_record as $next_record) {
                    fputcsv($this->export_fp, $next_record, $this->csv_parms['delimiter'], $this->csv_parms['enclosure'], $this->csv_parms['escape']);
                }
            } else {
                fputcsv($this->export_fp, $csv_record, $this->csv_parms['delimiter'], $this->csv_parms['enclosure'], $this->csv_parms['escape']);
            }
        }
    }

    // -----
    // Retrieve (and return) the next record from the current import .csv file.
    //
    private function getCsvRecord(): false|array
    {
        return fgetcsv($this->import_fp, 0, $this->csv_parms['delimiter'], $this->csv_parms['enclosure'], $this->csv_parms['escape']);
    }
}
