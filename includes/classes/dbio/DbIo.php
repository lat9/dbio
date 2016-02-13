<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) { 
  exit ('Illegal access');
  
}

class DbIo extends base 
{
    public function __construct ($dbio_type = '') 
    {
        $this->message = '';
        $this->file_suffix = date ('Ymd-His-') . mt_rand (1000,999999);
        
        $message_file_name = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/dbio/' . FILENAME_DBIO_MESSAGES;
        if (!file_exists ($message_file_name)) {
            trigger_error ("Missing dbIO message file ($message_file_name)", E_USER_WARNING);
        } else {
            require ($message_file_name);
        }

        mb_internal_encoding (CHARSET);
        ini_set ('mbstring.substitute_character', DBIO_INVALID_CHAR_REPLACEMENT);
        ini_set ("auto_detect_line_endings", true);
        
        if (!class_exists ('DbIoHandler')) {
            require (DIR_FS_DBIO_CLASSES . 'DbIoHandler.php');
        }

        $this->initializeConfig ($dbio_type);

    }
  
    // -----
    // Returns the last message issued by the dbIO processing.
    //
    public function getMessage () 
    {
        return ($this->message == '') ? $this->handler->get_handler_message () : $this->message;
    }
  
    // -----
    // Returns some basic information about the dbIO handlers available.
    //
    public function getAvailableHandlers () 
    {
        $handlers = glob (DIR_FS_DBIO_CLASSES . 'DbIo*Handler.php');
        $handler_info = array ();
        if (is_array ($handlers)) {
            foreach ($handlers as $current_handler) {
                $handler_class = str_replace (array (DIR_FS_DBIO_CLASSES, '.php'), '', $current_handler);
                if ($handler_class != 'DbIoHandler') {
                    require $current_handler;
                    $handler = new $handler_class ($this->file_suffix);
                    $dbio_type = str_replace (array ('DbIo', 'Handler'), '', $handler_class);
                    $handler_info[$dbio_type] = array ( 
                        'description' => $handler->getHandlerDescription (), 
                        'class_name' => $handler_class,
                        'is_export_only' => $handler->isExportOnly (),
                        'is_header_included' => $handler->isHeaderIncluded (),
                    );
                }
            }
        }
        $this->message = (count ($handler_info) == 0) ? DBIO_MESSAGE_NO_HANDLERS_FOUND : '';
        return $handler_info;

    }
  
    // -----
    // Function to initialize the configuration settings for a given import type.  A given import type's configuration
    // is controlled by a file named class.dbio.$dbio_type.php, present in the DIR_FS_DBIO directory. That class-file 
    // is a dbio_handler-class object.
    //
    protected function initializeConfig ($dbio_type) 
    {
        unset ($this->handler);
        $this->initialized = false;
        $this->message = '';

        $this->dbio_type = $dbio_type;   
        if ($this->dbio_type != '') {
            $handler_classname = 'DbIo' . $dbio_type . 'Handler';
            $dbio_handler = DIR_FS_DBIO_CLASSES . $handler_classname . '.php';
            if (!file_exists ($dbio_handler)) {
                $this->message = sprintf (DBIO_FORMAT_MESSAGE_NO_HANDLER, $dbio_handler);
                trigger_error ($this->message, E_USER_WARNING);
            
            } else {
                if (!class_exists ($handler_classname)) {
                    require ($dbio_handler);
                }
                if (!class_exists ($handler_classname)) {
                    $this->message = sprintf (DBIO_FORMAT_MESSAGE_NO_CLASS, $handler_classname, $dbio_handler);
                    trigger_error ($this->message, E_USER_WARNING);
              
                } else {
                    $this->initialized = true;
                    $this->handler = new $handler_classname ($this->file_suffix);
                    if (!method_exists ($this->handler, 'debugMessage')) {
                        trigger_error ("dbIO handler ($handler_classname) missing the \"debugMessage\" method; terminating.", E_USER_ERROR);
                        exit ();
                    }
                }
            }
        }
        return $this->initialized;
    }
  
    // -----
    // Function that handles an export for the currently-active dbIO type.  The function takes an input that determines
    // where the exported data "goes", either to a 'file' or 'download' to auto-download the generated .csv file.
    //
    public function dbioExport ($export_to = 'file', $language = 'all') 
    {
        global $db;
        $completion_code = false;
        $this->message = '';
        if (!$this->initialized) {
            $this->message = DBIO_MESSAGE_EXPORT_NOT_INITIALIZED;
            trigger_error ($this->message, E_USER_WARNING);
          
        } elseif ($this->handler->exportInitialize ($language)) {
            $this->handler->startTimer ();
 
            $this->csv_parms = $this->handler->getCsvParameters ();
            $this->export_sql = $this->handler->exportGetSql ();
          
            $this->export_filename = 'dbio.' . $this->dbio_type . '.' . $this->file_suffix . '.csv';
            $this->export_fp = fopen (DIR_FS_DBIO . $this->export_filename, 'wb+');
            if ($this->export_fp == false) {
                $this->message = sprintf (DBIO_FORMAT_MESSAGE_EXPORT_NO_FP, DIR_FS_DBIO_EXPORT . $this->export_filename);
                trigger_error ($this->message, E_USER_WARNING);
            
            } else {
                $export_info = $db->Execute ($this->export_sql);
                if ($export_info->EOF) {
                    $this->message = DBIO_EXPORT_NOTHING_TO_DO;
              
                } else {
                    $this->debugMessage ('dbioExport: Begin CSV creation loop.');
                    ini_set ('max_execution_time', DBIO_MAX_EXECUTION_TIME);
              
                    $this->writeCsvRecord ($this->handler->exportGetHeader ());
                    while (!$export_info->EOF) {
                        $this->writeCsvRecord ($this->handler->exportPrepareFields ($export_info->fields));
                        $export_info->MoveNext ();
                
                    }
                    $completion_code = true;
                    $this->debugMessage ('dbioExport: Finished CSV creation loop.');
                }
                unset ($export_info);
            
                if ($completion_code !== false && $export_to == 'download') {
                    if (strpos ($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
                        header('Content-Type: "application/octet-stream"');
                        header('Content-Disposition: attachment; filename="' . $this->export_filename . '"');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                        header("Content-Transfer-Encoding: binary");
                        header('Pragma: public');
                        header("Content-Length: " . filesize (DIR_FS_DBIO_EXPORT . $this->export_filename));
                    } else {
                        header('Content-Type: "application/octet-stream"');
                        header('Content-Disposition: attachment; filename="' . $this->export_filename . '"');
                        header("Content-Transfer-Encoding: binary");
                        header('Expires: 0');
                        header('Pragma: no-cache');
                        header("Content-Length: " . filesize (DIR_FS_DBIO_EXPORT . $this->export_filename));
                    }
                    rewind ($this->export_fp);
                    fpassthru ($this->export_fp);
                }
                fclose ($this->export_fp);
            }
            $this->handler->stopTimer ();
        }
        if (!$completion_code && $this->message == '') {
            $this->message = $this->handler->getHandlerMessage ();
        }
        return array ( 'status' => $completion_code, 'export_filename' => $this->export_filename );
    }
  
    // -----
    // Import processing sequencer for the specified file.  The operation can be one of
    // 'check' (check-only, no database update), 'run' (runs the import).  The language
    // is specified as either 'all' (all store languages) or by the 2-character ISO code
    // associated with the language, e.g. 'en' for English or 'es' for Spanish.
    //
    public function dbioImport ($filename, $operation = 'check', $language = 'all') 
    {
        $completion_code = false;
        $this->message = '';
        $import_file = DIR_FS_DBIO . $filename;
        if (!$this->initialized) {
            $this->message = DBIO_MESSAGE_IMPORT_NOT_INITIALIZED;
            trigger_error ($this->message, E_USER_WARNING);
          
        } else {
            $this->handler->startTimer ();
            if (!file_exists ($import_file)) {
                $this->message = sprintf (DBIO_FORMAT_MESSAGE_IMPORT_FILE_MISSING, $import_file);
                trigger_error ($this->message, E_USER_WARNING);
            
            } elseif (($this->import_fp = fopen ($import_file, 'r')) === false) {
                $this->message = sprintf (DBIO_FORMAT_MESSAGE_EXPORT_NO_FP, $import_file);
                trigger_error ($this->message, E_USER_WARNING);
            
            } else {
                $this->handler->importInitialize ($language, $operation);
                $this->csv_parms = $this->handler->getCsvParameters ();
                if (!$this->handler->importGetHeader (($this->handler->isHeaderIncluded ()) ? $this->getCsvRecord () : false)) {
                    $this->message = $this->handler->getHandlerMessage ();
              
                } else {
                    ini_set ('max_execution_time', DBIO_MAX_EXECUTION_TIME);
                    while (($data = $this->getCsvRecord ()) !== false) {
                        $this->handler->importCsvRecord ($data);
                    }
                }
                fclose ($this->import_fp);
            }
            $this->handler->stopTimer ();
        }
        return $completion_code;
    }

    // -----
    // Redirect any debug-messages from this level of processing to the handler's message handling, so that
    // all messages for a given import are recorded in a single location.
    //
    protected function debugMessage ($message) 
    {
        $this->handler->debugMessage ($message);
    }
  
    // -----
    // Write the specified array of data to the current export .csv file.
    //
    private function writeCsvRecord ($csv_record) 
    {
        if (is_array ($csv_record) && count ($csv_record) != 0) {
            if (version_compare (PHP_VERSION, '5.5.4', '>=')) {
                fputcsv ($this->export_fp, $csv_record, $this->csv_parms['delimiter'], $this->csv_parms['enclosure'], $this->csv_parms['escape']);
            
            } else {
                fputcsv ($this->export_fp, $csv_record, $this->csv_parms['delimiter'], $this->csv_parms['enclosure']);
            
            }
        }
    }
  
    // -----
    // Retrieve (and return) the next record from the current import .csv file.
    //
    private function getCsvRecord () 
    {
        if (version_compare (PHP_VERSION, '5.3.0', '>=')) {
            $data = fgetcsv ($this->import_fp, 0, $this->csv_parms['delimiter'], $this->csv_parms['enclosure'], $this->csv_parms['escape']);
          
        } else {
            $data = fgetcsv ($this->import_fp, 0, $this->csv_parms['delimiter'], $this->csv_parms['enclosure']);
          
        }
        return $data;
    }

}