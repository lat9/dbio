<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2016, Vinos de Frutas Tropicales.
//
if (!defined ('IS_ADMIN_FLAG')) {
  exit ('Illegal access');
  
}

// ----
// These definitions are used by this sequencing class as well as the report-specific handlers.
//
define ('DBIO_WARNING', 1);
define ('DBIO_ERROR', 2);
define ('DBIO_INVALID_CHAR_REPLACEMENT', 167); //-Use the "section symbol" (§) as the invalid-character replacement

// -----
// These definitions will, eventually, be migrated to an /extra_datafiles file.
//
if (!defined ('DIR_FS_DBIO')) define ('DIR_FS_DBIO', DIR_FS_CATALOG . 'dbio/');
define ('DIR_FS_DBIO_IMPORT', DIR_FS_DBIO . 'import/');
define ('DIR_FS_DBIO_EXPORT', DIR_FS_DBIO . 'export/');
define ('DIR_FS_DBIO_PROCESSED', DIR_FS_DBIO . 'processed/');

// -----
// These definitions will, eventually, be migrated to language files.
//
define ('DBIO_FORMAT_TEXT_NO_DESCRIPTION', 'This dbIO handler has no description.');
define ('DBIO_MESSAGE_NO_HANDLERS_FOUND', 'No dbIO handlers were found; no report-generation is possible.');
define ('DBIO_FORMAT_MESSAGE_NO_HANDLER', 'Missing dbIO handler class file %s.');
define ('DBIO_FORMAT_MESSAGE_NO_CLASS', 'Missing dbIO handler class named "%1$s" in handler file %2$s.');
define ('DBIO_MESSAGE_EXPORT_NOT_INITIALIZED', 'Export aborted: No handler previously specified.');
define ('DBIO_MESSAGE_IMPORT_NOT_INITIALIZED', 'Import aborted: No handler previously specified.');
define ('DBIO_FORMAT_MESSAGE_EXPORT_NO_FP', 'Export aborted. Failure creating output file %s.');
define ('DBIO_EXPORT_NOTHING_TO_DO', 'dbIO Export: No matching fields were found.');
define ('DBIO_FORMAT_MESSAGE_IMPORT_FILE_MISSING', 'Import aborted:  Missing input file (%s).');
define ('DBIO_WARNING_ENCODING_ERROR', 'dbIO Import: Could not encode the input for ' . CHARSET . '.');

// -----
// These definitions will, eventually, be migrated to admin-level configuration values.
//
if (!defined ('DBIO_DEBUG')) define ('DBIO_DEBUG', 'true');                                 //-Either 'true' or 'false'
if (!defined ('DBIO_DEBUG_DATE_FORMAT')) define ('DBIO_DEBUG_DATE_FORMAT', 'Y-m-d H:i:s');  //-Date format used on the dbio log-output

if (!defined ('DBIO_CSV_DELIMITER')) define ('DBIO_CSV_DELIMITER', ',');
if (!defined ('DBIO_CSV_ENCLOSURE')) define ('DBIO_CSV_ENCLOSURE', '"');
if (!defined ('DBIO_CSV_ESCAPE')) define ('DBIO_CSV_ESCAPE', '\\');

if (!defined ('DBIO_IMPORT_DATE_FORMAT')) define ('DBIO_IMPORT_DATE_FORMAT', 'm/d/y');      //-Possible values: 'm-d-y', 'd-m-y', 'y-m-d'
if (!defined ('DBIO_CHARSET')) define ('DBIO_CHARSET', 'CP1252');                           //-Possible values: 'UTF-8', 'ISO-8859-1', 'ASCII', 'CP1252'
if (!defined ('DBIO_MAX_EXECUTION_TIME')) define ('DBIO_MAX_EXECUTION_TIME', '60');         //-Number of seconds for script time-out

class dbio extends base {

  function __construct ($dbio_type = '') {
    $this->message = '';
    $this->file_suffix = date ('Ymd-His-') . mt_rand (1000,999999);
    
    $this->languages = array ();
    if (!class_exists ('language')) {
      require (DIR_WS_CLASSES . 'language.php');
      
    }
    $languages = new language;
    foreach ($languages->catalog_languages as $iso_code_2 => $language_info) {
      $this->languages[$iso_code_2] = $language_info['id'];
      
    }
    unset ($languages);
    
    mb_internal_encoding (CHARSET);
    ini_set ('mbstring.substitute_character', DBIO_INVALID_CHAR_REPLACEMENT);
    ini_set ("auto_detect_line_endings", true);
    
    $this->initialize_config ($dbio_type);
 
  }
  
  // -----
  // Returns the last message issued by the dbIO processing.
  //
  function get_message () {
    return ($this->message == '') ? $this->handler->get_handler_message () : $this->message;
    
  }
  
  // -----
  // Returns some basic information about the dbIO handlers available.
  //
  function get_available_handlers () {
    $handlers = glob (DIR_FS_DBIO . 'class.dbio.*.php');
    $handler_info = array ();
    if (is_array ($handlers)) {
      foreach ($handlers as $current_handler) {
        $handler_class = 'dbio_' . substr (str_replace (DIR_FS_DBIO . 'class.dbio.', '', $current_handler), 0, -4);
        require $current_handler;
        $handler = new $handler_class ();
        $handler_info[$handler_class] = array ( 'description' => $handler->get_handler_description () );
        
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
  function initialize_config ($dbio_type) {
    unset ($this->handler);
    $this->initialized = false;
    $this->message = '';
    
    $this->dbio_type = $dbio_type;   
    if ($this->dbio_type != '') {
      if (!class_exists ('dbio_handler')) {
        require (DIR_FS_DBIO . 'class.dbio_handler.php');
        
      }
      $dbio_handler = DIR_FS_DBIO . "class.dbio.$dbio_type.php";
      if (!file_exists ($dbio_handler)) {
        $this->message = sprintf (DBIO_FORMAT_MESSAGE_NO_HANDLER, $dbio_handler);
        trigger_error ($this->message, E_USER_WARNING);
        
      } else {
        require ($dbio_handler);
        $handler_classname = "dbio_$dbio_type";
        if (!class_exists ($handler_classname)) {
          $this->message = sprintf (DBIO_FORMAT_MESSAGE_NO_CLASS, $handler_classname, $dbio_handler);
          trigger_error ($this->message, E_USER_WARNING);
          
        } else {
          $this->initialized = true;
          $this->handler = new $handler_classname ($this->file_suffix, $this->languages);
          
        }
      }
    }
    return $this->initialized;
    
  }
  
  // -----
  // Function that handles an export for the currently-active dbIO type.  The function takes an input that determines
  // where the exported data "goes", either to a 'file' or 'download' to auto-download the generated .csv file.
  //
  function export ($export_to = 'file', $language = 'all') {
    global $db;
    $completion_code = false;
    $this->message = '';
    if (!$this->initialized) {
      $this->message = DBIO_MESSAGE_EXPORT_NOT_INITIALIZED;
      trigger_error ($this->message, E_USER_WARNING);
      
    } else {
      $this->handler->export_initialize ($language);
      $this->export_sql = $this->handler->export_get_sql ();
      
      $this->export_filename = 'dbio.export.' . $this->dbio_type . '.' . $this->file_suffix . '.csv';
      $this->export_fp = fopen (DIR_FS_DBIO_EXPORT . $this->export_filename, 'wb+');
      if ($this->export_fp == false) {
        $this->message = sprintf (DBIO_FORMAT_MESSAGE_EXPORT_NO_FP, DIR_FS_DBIO_EXPORT . $this->export_filename);
        trigger_error ($this->message, E_USER_WARNING);
        
      } else {
        $export_info = $db->Execute ($this->export_sql);
        if ($export_info->EOF) {
          $this->message = DBIO_EXPORT_NOTHING_TO_DO;
          
        } else {
          ini_set ('max_execution_time', DBIO_MAX_EXECUTION_TIME);
          
          $this->write_csv_record ($this->handler->export_get_header ());
          while (!$export_info->EOF) {
            $this->write_csv_record ($this->handler->export_prepare_fields ($export_info->fields));
            $export_info->MoveNext ();
            
          }
          $completion_code = true;
          
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
    }
    return $completion_code;
    
  }
  
  // -----
  // Import processing sequencer for the specified file.  The operation can be one of
  // 'check' (check-only, no database update), 'run' (runs the import).  The language
  // is specified as either 'all' (all store languages) or by the 2-character ISO code
  // associated with the language, e.g. 'en' for English or 'es' for Spanish.
  //
  function import ($filename, $operation = 'check', $language = 'all') {
    $completion_code = false;
    $this->message = '';
    $import_file = DIR_FS_DBIO_IMPORT . $filename;
    if (!$this->initialized) {
      $this->message = DBIO_MESSAGE_IMPORT_NOT_INITIALIZED;
      trigger_error ($this->message, E_USER_WARNING);
      
    } else {
      $this->handler->start_timer ();
      if (!file_exists ($import_file)) {
        $this->message = sprintf (DBIO_FORMAT_MESSAGE_IMPORT_FILE_MISSING, $import_file);
        trigger_error ($this->message, E_USER_WARNING);
        
      } elseif (($this->import_fp = fopen ($import_file, 'r')) === false) {
        $this->message = sprintf (DBIO_FORMAT_MESSAGE_EXPORT_NO_FP, $import_file);
        trigger_error ($this->message, E_USER_WARNING);
        
      } else {
        $this->handler->import_initialize ($language, $operation);
        if (!$this->handler->import_get_header (($this->handler->config['include_header']) ? $this->get_csv_record () : false)) {
          $this->message = $this->handler->get_handler_message ();
          
        } else {
          ini_set ('max_execution_time', DBIO_MAX_EXECUTION_TIME);
          while (($data = $this->get_csv_record ()) !== false) {
            $this->handler->import_csv_record ($data);
            
          }
        }
        fclose ($this->import_fp);
        
      }
      $this->handler->stop_timer ();
      
    }
    return $completion_code;
    
  }

/*
** $value = iconv('Windows-1252', 'UTF-8//IGNORE//TRANSLIT', $value);
*/
  
  // -----
  // Redirect any debug-messages from this level of processing to the handler's message handling, so that
  // all messages for a given import are recorded in a single location.
  //
  function debug_message ($message) {
    if (!isset ($this->handler) || !method_exists ($this->handler, 'debug_message')) {
      trigger_error ("Missing handler or method for message: $message", E_USER_ERROR);
      exit ();
    }
    $this->handler->debug_message ($message);

  }
  
  // -----
  // Write the specified array of data to the current export .csv file.
  //
  private function write_csv_record ($csv_record) {
    if (is_array ($csv_record) && count ($csv_record) != 0) {
      if (version_compare (PHP_VERSION, '5.5.4', '>=')) {
        fputcsv ($this->export_fp, $csv_record, $this->handler->export['delimiter'], $this->handler->export['enclosure'], $this->handler->export['escape']);
        
      } else {
        fputcsv ($this->export_fp, $csv_record, $this->handler->export['delimiter'], $this->handler->export['enclosure']);
        
      }
    }
  }
  
  // -----
  // Retrieve (and return) the next record from the current import .csv file.
  //
  private function get_csv_record () {
    if (version_compare (PHP_VERSION, '5.3.0', '>=')) {
      $data = fgetcsv ($this->import_fp, 0, $this->handler->import['delimiter'], $this->handler->import['enclosure'], $this->handler->import['escape']);
      
    } else {
      $data = fgetcsv ($this->import_fp, 0, $this->handler->import['delimiter'], $this->handler->import['enclosure']);
      
    }
    return $data;
    
  }

}