<?php
// -----
// Part of the DataBase I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2020, Vinos de Frutas Tropicales.
//
require 'includes/application_top.php';
require DIR_FS_ADMIN . 'includes/functions/dbio_manager_functions.php';

$languages = zen_get_languages();

if (!defined('DBIO_SUPPORTED_FILE_EXTENSIONS')) define('DBIO_SUPPORTED_FILE_EXTENSIONS', 'csv');

// -----
// Make sure that the database and I/O character sets match; any DbIo operation is disallowed otherwise.
//
$ok_to_proceed = true;
$error_message = '';
$info_message = '';
if (stripos(DB_CHARSET, 'utf') === 0) {
    if (stripos(CHARSET, 'utf') !== 0) {
        $ok_to_proceed = false;
    }
} else {
    if (stripos(CHARSET, 'utf') === 0) {
        $ok_to_proceed = false;
    }
}

if (!$ok_to_proceed) {
    $error_message = sprintf(DBIO_FORMAT_CONFIGURATION_ERROR, DB_CHARSET, CHARSET);
} else {
    $action = (isset($_GET['action'])) ? $_GET['action'] : '';
    
    if (!isset($_SESSION['dbio_show_filters'])) {
        $_SESSION['dbio_show_filters'] = false;
    }
    
    require DIR_FS_DBIO_CLASSES . 'DbIo.php';
    $dbio = new DbIo;
    if (!$dbio->isInitialized()) {
        $ok_to_proceed = false;
        $error_message = $dbio->getMessage();
    } else {
        $dbio_handlers = $dbio->getAvailableHandlers();
    }

    if (!$ok_to_proceed) {
        $error_message = $dbio->getMessage();
    } elseif (count($dbio_handlers) == 0) {
        $ok_to_proceed = false;
        $error_message = DBIO_MESSAGE_NO_HANDLERS_FOUND;
    } else {
        $available_handlers = array();
        $handler_name = (isset($_POST['handler'])) ? $_POST['handler'] : ((isset($_SESSION['dbio_vars']) && isset($_SESSION['dbio_vars']['handler'])) ? $_SESSION['dbio_vars']['handler'] : false);
        foreach ($dbio_handlers as $current_handler => $handler_info) {
            if ($handler_name === false) {
                $handler_name = $current_handler;
            }
            $available_handlers[] = array(
                'id' => $current_handler,
                'text' => $current_handler
            );
        }
        $handler_info = $dbio_handlers[$handler_name];
        
        if (!isset($_SESSION['dbio_vars'])) {
            $_SESSION['dbio_vars'] = array();
            $_SESSION['dbio_vars']['handler'] = $handler_name;
        }
        if ($_SESSION['dbio_vars']['handler'] != $handler_name) {
            unset($_SESSION['dbio_active_filename'], $_SESSION['dbio_import_result']);
        }
        $_SESSION['dbio_vars']['handler'] = $handler_name;
        
        // -----
        // Build up the array of potential input files for the current handler when processing a file-related
        // action or a simple page-display.  No sense in gathering the information for the other actions,
        // since the information isn't used.
        //
        $dbio_files = array();
        if ($action == 'file' || $action == '') {
            $files_check = false;
            $csv_check = glob(DIR_FS_DBIO . "dbio.$handler_name.*.csv");
            if (is_array($csv_check)) {
                $files_check = $csv_check;
                unset($csv_check);
            }
            $log_check = glob(DIR_FS_DBIO . "logs/dbio-$handler_name-*.log");
            if (is_array($log_check)) {
                if (is_array($files_check)) {
                    $files_check = array_merge($files_check, $log_check);
                } else {
                    $files_check = $log_check;
                }
                unset($log_check);
            }
            
            if (is_array($files_check)) {
                foreach ($files_check as $current_csv_file) {
                    $file_stats = stat($current_csv_file);
                    $current_csv_filename = str_replace(DIR_FS_DBIO, '', $current_csv_file);
                    if (!isset($_SESSION['dbio_active_filename'])) {
                        $_SESSION['dbio_active_filename'] = $current_csv_filename;
                    }
                    $filename_hash = md5($current_csv_file);
                    $dbio_files[$filename_hash] = array(
                        'full_filepath' => $current_csv_file,
                        'filename_only' => $current_csv_filename,
                        'selected' => ($_SESSION['dbio_active_filename'] == $current_csv_filename),
                        'last_modified' => $file_stats[9],
                        'bytes' => $file_stats[7],
                        'handler_name' => $handler_name,
                        'is_export_only' => (isset($dbio_handlers[$handler_name]) && isset($dbio_handlers[$handler_name]['export_only'])) ? $dbio_handlers[$handler_name]['export_only'] : false,
                        'is_header_included' => (isset($dbio_handlers[$handler_name]) && isset($dbio_handlers[$handler_name]['include_header'])) ? $dbio_handlers[$handler_name]['include_header'] : false,
                    );
                }
            }
            unset($files_check, $current_csv_file, $file_stats, $dbio_handlers, $filename_hash);
        }
        
        switch ($action) {
            case 'choose':
                zen_redirect(zen_href_link(FILENAME_DBIO_MANAGER, zen_get_all_get_params(array('action'))));
                break;

            case 'export':
                $_SESSION['dbio_show_filters'] = isset($_POST['show_filters']);
                if (isset($_POST['export_button'])) {
                    $report_suffix = '';
                    $customized_fields = false;
                    if (isset($_POST['custom']) && $_POST['custom'] != 0) {
                        $custom_info = $db->Execute(
                            "SELECT report_name, field_info
                              FROM " . TABLE_DBIO_REPORTS . "
                             WHERE dbio_reports_id = " . (int)$_POST['custom'] . "
                             LIMIT 1"
                        );
                        if ($custom_info->EOF) {
                            $messageStack->add_session(ERROR_UNKNOWN_TEMPLATE, 'error');
                            zen_redirect(zen_href_link(FILENAME_DBIO_MANAGER, zen_get_all_get_params(array ('action'))));
                        }
                        $report_suffix = $custom_info->fields['report_name'];
                        $customized_fields = $custom_info->fields['field_info'];
                        unset($custom_info);
                    }
                    
                    unset($dbio);
                    $dbio = new DbIo($handler_name, $report_suffix);
                    
                    if ($customized_fields !== false) {
                        $dbio->handler->exportCustomizeFields(json_decode ($customized_fields));
                    }
        
                    $_SESSION['dbio_auto_download'] = (isset($_POST['auto_download']));
                    
                    $export_info = $dbio->dbioExport('file');
                    if ($export_info['status'] === false) {
                        $messageStack->add_session($export_info['message'], 'error');
                    } else {
                        $messageStack->add_session(sprintf(DBIO_MGR_EXPORT_SUCCESSFUL, $handler_name, $export_info['export_filename'], $export_info['stats']['record_count']), 'success');
                        $_SESSION['dbio_vars'] = $_POST;
                        $_SESSION['dbio_vars']['handler'] = $handler_name;
                        $_SESSION['dbio_last_export'] = $export_info;
                        $_SESSION['dbio_active_filename'] = $export_info['export_filename'];
                        
                        $download_active_filename = (!empty($_SESSION['dbio_auto_download']));
                        if ($download_active_filename) {
                            $download_filename = $export_info['export_filename'];
                            $download_info = array(
                                'name' => $download_filename,
                                'bytes' => filesize(DIR_FS_DBIO . $download_filename),
                            );
                        }
                    }
                }
                
                if (empty($download_active_filename)) {
                    zen_redirect(zen_href_link(FILENAME_DBIO_MANAGER, zen_get_all_get_params(array('action'))));
                }
                break;

            case 'upload':
                if (empty($_FILES['upload_filename']['name'])) {
                    $messageStack->add(ERROR_NO_FILE_TO_UPLOAD, 'error');
                } else {
                    if (dbio_strpos($_FILES['upload_filename']['name'], "dbio.$handler_name.") !== 0) {
                        $messageStack->add_session(sprintf(ERROR_FILENAME_MISMATCH, $handler_name), 'error');
                    } else {
                        $upload = new upload('upload_filename');
                        $upload->set_extensions(explode (',', DBIO_SUPPORTED_FILE_EXTENSIONS));
                        $upload->set_destination(DIR_FS_DBIO);
                        if ($upload->parse()) {
                            $upload->save();
                        }
                        $_SESSION['dbio_active_filename'] = $_FILES['upload_filename']['name'];
                    }
                    zen_redirect(zen_href_link(FILENAME_DBIO_MANAGER, zen_get_all_get_params(array('action'))));
                }
                break;

            case 'file':
                if (!((isset($_POST['file_action']) && isset($_POST['filename_hash']) && isset($dbio_files[$_POST['filename_hash']])) ||
                      (isset($_POST['delete_button']) && isset($_POST['delete_hash'])))) {
                    $messageStack->add_session(DBIO_FORM_SUBMISSION_ERROR);
                } elseif (isset($_POST['delete_button'])) {
                    if (is_array($_POST['delete_hash'])) {
                        foreach ($_POST['delete_hash'] as $delete_hash => $delete_value) {
                            if (isset($dbio_files[$delete_hash])) {
                                if ($dbio_files[$delete_hash]['selected']) {
                                    unset($_SESSION['dbio_active_filename']);
                                }
                                $action_filename = $dbio_files[$delete_hash]['full_filepath'];
                                if (!is_writeable($action_filename) || !unlink($action_filename)) {
                                    $messageStack->add_session(sprintf(ERROR_CANT_DELETE_FILE, $action_filename));
                                } else {
                                    $messageStack->add_session(sprintf(SUCCESS_FILE_DELETED, $action_filename), 'success');
                                }
                            }
                        }
                    }
                    zen_redirect(zen_href_link(FILENAME_DBIO_MANAGER, zen_get_all_get_params(array('action'))));
                } else {
                    $action_filename = $dbio_files[$_POST['filename_hash']]['full_filepath'];
                    $active_filename = $dbio_files[$_POST['filename_hash']]['filename_only'];
                    switch ($_POST['file_action']) {
                        case 'none':
                            $messageStack->add_session(sprintf(ERROR_CHOOSE_FILE_ACTION, $action_filename));
                            zen_redirect(zen_href_link(FILENAME_DBIO_MANAGER, zen_get_all_get_params()));
                            break;

                        case 'import-run':
                        case 'import-check':
                            unset ($dbio);
                            $_SESSION['dbio_active_filename'] = $active_filename;
                            if ($dbio_files[$_POST['filename_hash']]['is_export_only']) {
                                $messageStack->add_session(sprintf(ERROR_FILE_IS_EXPORT_ONLY, $action_filename));
                            } else {
                                $dbio = new DbIo($dbio_files[$_POST['filename_hash']]['handler_name']);
                                $action_types = explode('-', $_POST['file_action']);
                                $import_result = $dbio->dbioImport($dbio_files[$_POST['filename_hash']]['filename_only'], $action_types[1]);
                                if ($import_result['status'] === true) {
                                    if (count($import_result['io_errors']) == 0) {
                                        $messageStack->add_session(sprintf(SUCCESSFUL_FILE_IMPORT, $action_filename, $import_result['stats']['record_count']), 'success');
                                    } else {
                                        $messageStack->add_session(sprintf(CAUTION_FILE_IMPORT, $action_filename, $import_result['stats']['errors'], $import_result['stats']['warnings'], $import_result['stats']['inserts'] + $import_result['stats']['updates']), 'warning');
                                    }
                                } else {
                                    $messageStack->add_session($import_result['message']);
                                }
                                $_SESSION['dbio_import_result'] = array_merge($import_result, array('import_filename' => $action_filename));
                            }
                            zen_redirect(zen_href_link(FILENAME_DBIO_MANAGER, zen_get_all_get_params(array('action'))));
                            break;

                        case 'split':
                            $_SESSION['dbio_active_filename'] = $active_filename;
                            if (!is_readable($action_filename) || ($fp = fopen($action_filename, "r")) === false) {
                                $messageStack->add_session(sprintf(ERROR_CANT_SPLIT_FILE_OPEN_ERROR, $action_filename));
                            } else {
                                $split_count = 0;
                                $record_count = 0;
                                $header_record = false;
                                $split_error = false;
                                $files_created = array();
                                $header_included = $dbio_files[$_POST['filename_hash']]['is_header_included'];

                                $split_file_info = pathinfo($action_filename);
                                $chunk_filename = $split_file_info['dirname'] . '/' . $split_file_info['filename'];
                                $chunk_extension = '.' . $split_file_info['extension'];
                                unset($split_file_info);

                                while (($data = fgetcsv($fp)) !== false) {
                                    if ($split_count == 0 && $header_included) {
                                        $header_record = $data;
                                    }
                                    if ($split_count == 0 || $record_count > (int)DBIO_SPLIT_RECORD_COUNT) {
                                        if (isset($fp_out)) {
                                            fclose($fp_out);
                                        }
                                        $split_count++;
                                        $out_filename = $chunk_filename . ".part-$split_count" . $chunk_extension;
                                        $fp_out = fopen($out_filename, "w");
                                        if ($fp_out === false) {
                                            $split_error = true;
                                            $messageStack->add_session(sprintf(ERROR_CREATING_SPLIT_FILE, $out_filename));
                                            break;
                                        }
                                        $files_created[] = $out_filename;
                                        $record_count = 0;
                                        if ($header_included) {
                                            $record_count++;
                                            if (fputcsv($fp_out, $header_record) === false) {
                                                $split_error = true;
                                                $messageStack->add_session(sprintf(ERROR_WRITING_SPLIT_FILE, $out_filename, $record_count));
                                                break;
                                            }
                                            if ($split_count == 1) {
                                                continue;
                                            }
                                        }
                                    }
                                    if (!($record_count == 0 && $header_included)) {
                                        $record_count++;
                                        if (fputcsv($fp_out, $data) === false) {
                                            $split_error = true;
                                            $messageStack->add_session(sprintf(ERROR_WRITING_SPLIT_FILE, $out_filename, $record_count));
                                            break;
                                        }
                                    }
                                }

                                if (isset($fp_out) && $fp_out !== false) {
                                    fclose($fp_out);
                                }
                                if (!$split_error && !feof($fp)) {
                                    $messageStack->add_session(sprintf(ERROR_SPLIT_INPUT_NOT_AT_EOF, $action_filename));
                                    $split_error = true;
                                }
                                fclose($fp);
                                
                                if (!$split_error && $split_count == 1) {
                                    $messageStack->add_session(sprintf(WARNING_FILE_TOO_SMALL_TO_SPLIT, $action_filename, $record_count), 'caution');
                                    $split_error = true;
                                } else {
                                    $messageStack->add_session(sprintf(FILE_SUCCESSFULLY_SPLIT, $action_filename, $split_count), 'success');
                                }
                                
                                if ($split_error) {
                                    foreach ($files_created as $file_to_remove) {
                                        unlink($file_to_remove);
                                    }
                                }
                            }
                            zen_redirect(zen_href_link(FILENAME_DBIO_MANAGER, zen_get_all_get_params(array('action'))));
                            break;

                        case 'download':
                            $_SESSION['dbio_active_filename'] = $active_filename;
                            $download_info = array(
                                'name' => $dbio_files[$_POST['filename_hash']]['filename_only'],
                                'bytes' => $dbio_files[$_POST['filename_hash']]['bytes'],
                            );
                            break;

                        default:
                            break;
                    }
                }
                break;

             default:
                $action = '';
                break;
        }
        
        // -----
        // If a file-download is requested (either via direct "Download" selection or as a result of an
        // export's automatic download), download the file now.
        //
        if (!empty($download_info)) {
            $action_filename = $download_info['name'];
            $fp = fopen(DIR_FS_DBIO . $action_filename, 'r');
            if ($fp === false) {
                $messageStack->add_session(sprintf(DBIO_CANT_OPEN_FILE, $action_filename), 'error');
                zen_redirect(zen_href_link(FILENAME_DBIO_MANAGER, zen_get_all_get_params(array('action'))));
            } else {
                if (dbio_strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
                    header('Content-Type: "application/octet-stream"');
                    header('Content-Disposition: attachment; filename="' . $action_filename . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header("Content-Transfer-Encoding: binary");
                    header('Pragma: public');
                    header("Content-Length: " . $download_info['bytes']);
                } else {
                    header('Content-Type: "application/octet-stream"');
                    header('Content-Disposition: attachment; filename="' . $action_filename . '"');
                    header("Content-Transfer-Encoding: binary");
                    header('Expires: 0');
                    header('Pragma: no-cache');
                    header("Content-Length: " . $download_info['bytes']);
                }
                fpassthru($fp);
                fclose($fp);
                unset($_GET['action']);
                exit();
            }
        }
    }
}  //-END configuration OK, proceeding ...
?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css" />
<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS" />
<link rel="stylesheet" type="text/css" href="includes/javascript/dbio/colorbox.css" />
<style type="text/css">
<!--
input[type="submit"] { cursor: pointer; }
select { padding: 0.1em; margin: 0.5em; }
td, th { padding: 0.5em; }
legend { background-color: #fff8dc; padding: 0.3em; border: 1px solid #e5e5e5; }
#main-wrapper { text-align: center; padding: 1em; }
#main-contents, #config-list, #file-actions { width: 100%; }
#message { border: 2px solid #ddd; display: inline-block; padding: 0.5em; border-radius: 0.75em; }
#message.error { border-color: red; }
#message.info { border-color: green; }
#reports-instr { padding-bottom: 0.5em; }
#export-form, #upload-form, #file-list, #configuration { vertical-align: top; }
#export-form form, #upload-form form { display: block; }
#configuration { width: 25%; }
#submit-report { text-align: right; margin: 0.5em 0; }
#file-delete-action, #file-toggle { text-align: center; }
#configuration-info { padding-bottom: 0.5em; }
#file-list, #export-form { border-right: 1px solid #e5e5e5; }
#file-row-header { background-color: #fbf6d9; }
.reports-details-row, #submit-report { border-top: 1px solid #ebebeb; margin-top: 0.5em; padding-top: 0.5em; }
#export-customize { float: left; text-align: left;}
#export-button { float: right; text-align: right; }
#top-instructions { text-align: left; padding: 0.5em 0.7em; border-bottom: 1px solid #e5e5e5; }
#file-instrs { padding: 0 0.5em 0.7em; border-bottom: 1px solid #e5e5e5; }

.centered { text-align: center; }
.right { text-align: right; }
.left { text-align: left; }
.float-left { float: left; }
.smaller { font-size: smaller; }
.error { color: red; }
.file-row-header { background-color: #ebebeb; }
.instructions { font-size: 12px; padding-bottom: 10px; padding-top: 10px; }

.config-header { padding: 0.5em; background-color: #ebebeb; }
.config-list {  }
.config-group { list-style-type: none; padding: 0; }
.config-title { }
.config-value { padding-left: 0.5em; font-weight: bold; }
.input { display: inline-block; }
.input-label { float: left; text-align: right; font-weight: bold; padding-right: 0.5em; }
.input-field { float: left; text-align: left; }

.file-row-header, .config-header, .reports-details-name { font-weight: bold; }
.reports-details-select, .reports-details, .reports-filter-label { padding: 0.5em; }
.reports-filter-label { padding-left: 2em; clear: both; float: left; font-weight: bold; }
.reports-filter-label.multi { font-style: italic; }
.reports-filter-field.multi { clear: both; }
.filter-subfield-label { padding: 0 1em; width: 14em; text-align: right; display: inline-block; clear: both; }
.filter-subfield { }

.file-row:nth-child(odd) { background-color: #fbf6d9; }
.file-row:hover { background-color: #ebebeb; }
.file-sorter a { font-size: 20px; text-decoration: none; line-height: 11px; color: #599659; }
.file-sorter.selected-sort a, .flii-error .flii-item { color: red; }
div.export-only span { color: red; font-weight: bold; }
#flii-details { list-style-type: none; }
.flii-label { display: inline-block; width: 15em; text-align: right; font-weight: bold; }
.flii-value { display: inline-block; text-align: left; padding-left: 0.5em; }
#flii-message-intro { padding-bottom: 1em; }
.flii-item { font-weight: bold; }
.flii-warning .flii-item { color: #cccc00; }
.flii-info .flii-item { color: green; }
-->
</style>
<script type="text/javascript" src="includes/menu.js"></script>
</head>
<body onload="init();">
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
  <div id="main-wrapper">
    <h1><?php echo HEADING_TITLE; ?> <span class="smaller">v<?php echo DBIO_MODULE_VERSION; ?></span></h1>
    <p id="top-instructions"><?php echo TEXT_INSTRUCTIONS; ?></p>
<?php
if (!$ok_to_proceed || $error_message !== '') {
?>
    <div id="message" class="error"><?php echo $error_message; ?></div>
<?php
} else {
    if ($info_message !== '') {
?>
    <div id="message" class="info"><?php echo $info_message; ?></div>
<?php
    }
?>
    <table id="main-contents">
        <tr>
            <td id="dbio-choose" colspan="2"><?php echo TEXT_CHOOSE_HANDLER . ' ' . zen_draw_form('dbio-select', FILENAME_DBIO_MANAGER, zen_get_all_get_params(array('action')) . 'action=choose', 'post') . zen_draw_pull_down_menu('handler', $available_handlers, $handler_name, 'onchange="this.form.submit();"'); ?></form></td>
        </tr>
        
        <tr>
            <td id="export-form"><?php echo zen_draw_form('dbio', FILENAME_DBIO_MANAGER, zen_get_all_get_params(array('action')) . 'action=export', 'post', 'enctype="multipart/form-data"'); ?>
                <fieldset id="reports-export">
                    <legend><?php echo LEGEND_EXPORT; ?></legend>
                    <p><?php echo $handler_info['description']; ?></p>
<?php
    $handler_class = 'DbIo' . $handler_name . 'Handler';
    $filter_check = new \ReflectionMethod($handler_class, 'getHandlerExportFilters');
    $handler_has_filters = ($filter_check->getDeclaringClass()->getName() != 'DbIoHandler');
    unset($filter_check);
    
    if ($handler_has_filters) {
?>
                    <div id="show-filters"><?php echo zen_draw_checkbox_field('show_filters', '', $_SESSION['dbio_show_filters'], '', 'onchange="this.form.submit();"') . ' ' . TEXT_SHOW_HIDE_FILTERS; ?></div>
<?php
        if ($_SESSION['dbio_show_filters']) {
?>
                    <div class="reports-details-row">
<?php
            $handler_filters = $handler_class::getHandlerExportFilters();
            if (is_array ($handler_filters)) {
?>
                        <div class="reports-export-filters">
<?php
                foreach ($handler_filters as $field_name => $field_parms) {
                    if (!isset($field_parms['type']) || !isset ($field_parms['label'])) {
                        trigger_error("DbIo: Missing type and/or label for $handler_name::$field_name export filters:\n" . print_r($field_parms, true), E_USER_WARNING); 
                    } else {
                        $extra_field_class = '';
                        $dropdown_options = '';
                        $dropdown_field_suffix = '';
                        switch ($field_parms['type']) {
                            case 'input':
                                $form_field = zen_draw_input_field($field_name, dbioGetFieldValue ($field_name));
                                break;
                            case 'dropdown_multiple':
                                $dropdown_options = 'multiple';  
                                $dropdown_field_suffix = '[]';      //-Fall-through to dropdown handling
                            case 'dropdown':
                                if (!isset($field_parms['dropdown_options']) || !is_array ($field_parms['dropdown_options'])) {
                                    $form_field = false;
                                    trigger_error("DbIo: Missing dropdown_options for $handler_name::$field_name export filter:\n" . print_r($field_parms, true), E_USER_WARNING);
                                } else {
                                    $form_field = zen_draw_pull_down_menu($field_name . $dropdown_field_suffix, $field_parms['dropdown_options'], dbioGetFieldValue($field_name), $dropdown_options);
                                }
                                break;
                            case 'select_orders_status':
                                $form_field = dbioDrawOrdersStatusDropdown($field_name);
                                break;
                            case 'array':
                                $extra_field_class = ' multi';
                                if (!isset ($field_parms['fields'])) {
                                    $form_field = false;
                                    trigger_error("DbIo: Missing additional filter variable values for $handler_name::$field_name.", E_USER_WARNING);
                                } else {
                                    $form_field = '<span class="filter-subfield-wrap">';
                                    foreach ($field_parms['fields'] as $subfield_name => $subfield_parms) {
                                        $dropdown_options = '';
                                        $dropdown_field_suffix = '';
                                        $form_field .= '<span class="filter-subfield-label">' . $subfield_parms['label'] . '</span>';
                                        switch ($subfield_parms['type']) {
                                            case 'input':
                                                $form_field .= '<span class="filter-subfield">' . zen_draw_input_field($subfield_name, dbioGetFieldValue ($subfield_name)) . '</span>';
                                                break;
                                            case 'dropdown_multiple':
                                                $dropdown_options = 'multiple';
                                                $dropdown_field_suffix = '[]';      //-Fall-through to dropdown handling
                                            case 'dropdown':
                                                if (!isset($subfield_parms['dropdown_options']) || !is_array($subfield_parms['dropdown_options'])) {
                                                    $form_field = false;
                                                    trigger_error("DbIo: Missing dropdown_options for $handler_name::$field_name export filter:\n" . print_r($subfield_parms, true), E_USER_WARNING);
                                                } else {
                                                    $form_field .= zen_draw_pull_down_menu($subfield_name . $dropdown_field_suffix, $subfield_parms['dropdown_options'], dbioGetFieldValue($subfield_name), $dropdown_options);
                                                }
                                                break;
                                            case 'select_orders_status':
                                                $form_field .= dbioDrawOrdersStatusDropdown($subfield_name);
                                                break;
                                             default:
                                                $form_field = false;
                                                trigger_error("DbIo: Unknown filter subfield type (" . $subfield_parms['type'] . ") specified for $handler_name::$field_name::$subfield_name.", E_USER_WARNING);
                                                break;
                                        }
                                    }
                                    if ($form_field !== false) {
                                        $form_field .= '</span>';
                                    }
                                }
                                break;
                            default:
                                $form_field = false;
                                trigger_error("DbIo: Unknown export filter type (" . $field_parms['type'] . ") specified for $handler_name::$field_name.", E_USER_WARNING);
                                break;
                        }
                        if ($form_field !== false) {
?>
                            <div class="reports-filter-row">
                                <div class="reports-filter-label<?php echo $extra_field_class; ?>"><?php echo $field_parms['label']; ?></div>
                                <div class="reports-filter-field<?php echo $extra_field_class; ?>"><?php echo $form_field; ?></div>
                            </div>
<?php
                        }
                    }
                }
?>
                        </div>
<?php
            }
?>
                    </div>
<?php
        }
    }
?>
                </fieldset>
                <div id="submit-report">
                    <div id="export-customize">
<?php
    // -----
    // Check to see if the current handler supports export customizations and, if so, present a dropdown list to the admin to choose from and
    // a button to create a new customization.
    //
    unset($dbio);
    $dbio = new DbIo($handler_name);
    $customization_choices = array();
    $customizable_fields = $dbio->handler->getCustomizableFields();
    if (count ($customizable_fields) != 0) {
        $customizations = $db->Execute(
            "SELECT dr.dbio_reports_id, dr.admin_id, dr.report_name, drd.report_description
               FROM " . TABLE_DBIO_REPORTS . " dr, " . TABLE_DBIO_REPORTS_DESCRIPTION . " drd
              WHERE dr.handler_name = '$handler_name'
                AND dr.admin_id IN (0, " . $_SESSION['admin_id'] . ")
                AND dr.dbio_reports_id = drd.dbio_reports_id
                AND drd.language_id = " . $_SESSION['languages_id'] . "
           ORDER BY dr.report_name"
        );
        $customization_choices[] = array(
            'id' => 0,
            'text' => TEXT_ALL_FIELDS,
            'desc' => TEXT_ALL_FIELDS_DESCRIPTION,
        );
        while (!$customizations->EOF) {
            $customization_choices[] = array(
                'id' => $customizations->fields['dbio_reports_id'],
                'text' => $customizations->fields['report_name'],
                'desc' => (($customizations->fields['admin_id'] == 0) ? TEXT_SCOPE_PUBLIC : TEXT_SCOPE_PRIVATE) . ': ' . $customizations->fields['report_description'],
            );
            $customizations->MoveNext();
        }
        unset($customizations);
?>
                        <strong><?php echo LABEL_CHOOSE_CUSTOMIZATION; ?></strong> <?php echo zen_draw_pull_down_menu('custom', $customization_choices, 0, 'id="custom-change"') . '&nbsp;&nbsp;' . zen_draw_input_field('customize', TEXT_BUTTON_MANAGE_CUSTOMIZATION, 'onclick="window.location.href=\'' . zen_href_link(FILENAME_DBIO_CUSTOMIZE, "handler=$handler_name") . '\'"', false, 'button'); ?>
                        <p id="custom-desc"><?php echo htmlentities($customization_choices[0]['desc'], ENT_COMPAT, CHARSET); ?></p>
<?php
    }
?>
                    </div>
                    <div id="export-button">
                        <?php echo zen_draw_checkbox_field('auto_download', '', !empty($_SESSION['dbio_auto_download'])) . ' ' . TEXT_AUTO_DOWNLOAD; ?>&nbsp;
                        <?php echo zen_draw_input_field('export_button', BUTTON_EXPORT, 'title="' . BUTTON_EXPORT_TITLE . '"', false, 'submit'); ?>
                    </div>
                    <div class="clearBoth"></div>
                </div>
            </form></td>
            
            <td id="upload-form"><?php echo zen_draw_form('dbio', FILENAME_DBIO_MANAGER, zen_get_all_get_params(array('action')) . 'action=upload', 'post', 'enctype="multipart/form-data"'); ?>
                <fieldset>
                    <legend><?php echo LEGEND_FILE_UPLOADS; ?></legend>
<?php
    if (isset($handler_info['export_only']) && $handler_info['export_only'] === true) {
?>
                    <p><?php echo sprintf(TEXT_UPLOAD_FOR_IMPORT_ONLY, $handler_name); ?></p>
<?php
    } else {
?>
                    <div id="upload-file">
                        <p id="upload-instructions"><?php echo sprintf(TEXT_FILE_UPLOAD_INSTRUCTIONS, $handler_name, DBIO_SUPPORTED_FILE_EXTENSIONS); ?></p>
                        <p id="upload-file-field"><?php echo TEXT_CHOOSE_FILE . ' ' . zen_draw_file_field('upload_filename'); ?></p>
                        <p id="upload-button" class="right"><?php echo zen_draw_input_field('upload_button', BUTTON_UPLOAD, 'title="' . BUTTON_UPLOAD_TITLE . '"', false, 'submit'); ?></p>
                    </div>
<?php
    }
?>
                </fieldset>
            </form></td>
           
        </tr>
        
        <tr>        
            <td id="file-list"><?php echo zen_draw_form('file_form', FILENAME_DBIO_MANAGER, zen_get_all_get_params(array('action')) . 'action=file'); ?>
                <fieldset>
                    <legend><?php echo LEGEND_FILE_ACTIONS; ?></legend>
<?php
    if (!is_array($dbio_files) || count($dbio_files) == 0) {
?>
                    <p class="no-files"><?php echo sprintf(TEXT_NO_DBIO_FILES_AVAILABLE, $handler_name); ?></p>
<?php
    } else {
        $file_actions_array = array (
            array('id' => 'none', 'text' => DBIO_ACTION_PLEASE_SELECT),
            array('id' => 'split', 'text' => DBIO_ACTION_SPLIT),
            array('id' => 'download', 'text' => DBIO_ACTION_DOWNLOAD),
        );
        if (isset($handler_info['export_only']) && $handler_info['export_only'] !== true) {
            $file_actions_array[] = array('id' => 'import-run', 'text' => DBIO_ACTION_FULL_IMPORT);
            $file_actions_array[] = array('id' => 'import-check', 'text' => DBIO_ACTION_CHECK_IMPORT);
        }
        $file_action = (isset($_POST['file_action'])) ? $_POST['file_action'] : 'none';
        
        $sort_1a = $sort_1d = $sort_2a = $sort_2d = $sort_3a = $sort_3d = '';
        $sort_type = 'sort_' . ((isset($_GET['sort']) && in_array($_GET['sort'], explode(',', '1a,1d,2a,2d,3a,3d'))) ? $_GET['sort'] : DBIO_FILE_SORT_DEFAULT);
        $$sort_type = ' selected-sort';
        $last_update_button = '';
        if (isset($_SESSION['dbio_import_result'])) {
            $last_update_button = '<a class="import-info" href="#file-last-import" title="' . TEXT_IMPORT_LAST_STATS . '">' . TEXT_VIEW_STATS . '</a>';
        }
?>
                    <table id="file-actions">
                        <tr>
                            <td colspan="5" id="file-instrs"><?php echo TEXT_FILE_ACTION_INSTRUCTIONS; ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="file-row-caption"><?php echo TEXT_CHOOSE_ACTION . ' ' . zen_draw_pull_down_menu('file_action', $file_actions_array, $file_action, 'id="file-action"'); ?>&nbsp;&nbsp;<?php echo zen_draw_input_field('go_button', DBIO_BUTTON_GO, 'title="' . DBIO_BUTTON_GO_TITLE . '" onclick="return checkSubmit ();"', false, 'submit') . "&nbsp;&nbsp;$last_update_button"; ?></td>
                            <td id="file-toggle"><button type="button" id="select-all" title="<?php echo DBIO_SELECT_ALL_TITLE; ?>"><?php echo DBIO_SELECT_ALL; ?></button><br /><button type="button" id="unselect-all" title="<?php echo DBIO_UNSELECT_ALL_TITLE; ?>"><?php echo DBIO_UNSELECT_ALL; ?></button></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="right"><?php echo TEXT_FILE_ACTION_DELETE_INSTRUCTIONS; ?></td>
                            <td id="file-delete-action"> <?php echo zen_draw_input_field('delete_button', DBIO_BUTTON_DELETE, 'title="' . DBIO_BUTTON_DELETE_TITLE . '" onclick="return checkDelete ();"', false, 'submit'); ?></td>
                        </tr>
                        <tr id="file-row-header">
                            <td class="file-item"><?php echo HEADING_CHOOSE_FILE; ?></td>
                            <td class="file-item left"><span class="file-sorter<?php echo $sort_1a; ?>"><a href="<?php echo zen_href_link(FILENAME_DBIO_MANAGER, 'sort=1a'); ?>" title="<?php echo TEXT_SORT_NAME_ASC; ?>">&utrif;</a></span><?php echo HEADING_FILENAME; ?><span class="file-sorter<?php echo $sort_1d;?>"><a href="<?php echo zen_href_link(FILENAME_DBIO_MANAGER, 'sort=1d'); ?>" title="<?php echo TEXT_SORT_NAME_DESC; ?>">&dtrif;</a></span></td>
                            <td class="file-item center"><span class="file-sorter<?php echo $sort_2a; ?>"><a href="<?php echo zen_href_link(FILENAME_DBIO_MANAGER, 'sort=2a'); ?>" title="<?php echo TEXT_SORT_SIZE_ASC; ?>">&utrif;</a></span><?php echo HEADING_BYTES; ?><span class="file-sorter<?php echo $sort_2d; ?>"><a href="<?php echo zen_href_link(FILENAME_DBIO_MANAGER, 'sort=2d'); ?>" title="<?php echo TEXT_SORT_SIZE_DESC; ?>">&dtrif;</a></span></td>
                            <td class="file-item center"><span class="file-sorter<?php echo $sort_3a; ?>"><a href="<?php echo zen_href_link(FILENAME_DBIO_MANAGER, 'sort=3a'); ?>" title="<?php echo TEXT_SORT_DATE_ASC; ?>">&utrif;</a></span><?php echo HEADING_LAST_MODIFIED; ?><span class="file-sorter<?php echo $sort_3d; ?>"><a href="<?php echo zen_href_link(FILENAME_DBIO_MANAGER, 'sort=3d'); ?>" title="<?php echo TEXT_SORT_DATE_DESC; ?>">&dtrif;</a></span></td>
                            <td class="file-item center"><?php echo HEADING_DELETE; ?></td>
                        </tr>
<?php
        uasort($dbio_files, function($a, $b)
        {
            $sort_type = (isset($_GET['sort'])) ? $_GET['sort'] : DBIO_FILE_SORT_DEFAULT;
            switch ($sort_type) {
                case '1d':          //-File-name, descending
                    $compare_value = strcmp($b['filename_only'], $a['filename_only']);
                    break;
                case '2a':          //-File-size, ascending
                    $compare_value = ($a['bytes'] == $b['bytes']) ? 0 : (($a['bytes'] < $b['bytes']) ? -1 : 1);
                    break;
                case '2d':          //-File-size, descending
                    $compare_value = ($a['bytes'] == $b['bytes']) ? 0 : (($a['bytes'] > $b['bytes']) ? -1 : 1);
                    break;
                case '3a':          //-File-date, ascending
                    $compare_value = ($a['last_modified'] == $b['last_modified']) ? 0 : (($a['last_modified'] < $b['last_modified']) ? -1 : 1);
                    break;
                case '3d':          //-File-date, descending
                    $compare_value = ($a['last_modified'] == $b['last_modified']) ? 0 : (($a['last_modified'] > $b['last_modified']) ? -1 : 1);
                    break;
                default:            //-File-name, ascending (default)
                    $compare_value = strcmp($a['filename_only'], $b['filename_only']);
                    break;
            }
            return $compare_value;
        });

        $first_file = true;
        foreach ($dbio_files as $name_hash => $file_info) {
            $select_parms = (dbio_strpos($file_info['filename_only'], 'logs') === 0) ? 'class="dbio-log"' : '';
?>
                        <tr class="file-row">
                            <td class="file-item"><?php echo zen_draw_radio_field('filename_hash', $name_hash, $file_info['selected'], '', $select_parms); ?></td>
                            <td class="file-item left"><?php echo $file_info['filename_only']; ?></td>
                            <td class="file-item center"><?php echo $file_info['bytes']; ?></td>
                            <td class="file-item center"><?php echo date(DBIO_DEBUG_DATE_FORMAT, $file_info['last_modified']); ?></td>
                            <td class="file-item center"><?php echo zen_draw_checkbox_field('delete_hash[' . $name_hash . ']', '', false, '', 'class="delete-hash"'); ?></td>
                        </tr>
<?php
            $first_file = false;
        }
?>
                    </table>
<?php
    }
?>
                </fieldset>
            </form></td>
<?php
    $config_check = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'Database I/O Manager Settings' LIMIT 1");
    $configuration_group_id = ($config_check->EOF) ? 0 : $config_check->fields['configuration_group_id'];
?>            
            <td id="configuration">
                <fieldset>
                    <legend><?php echo LEGEND_CONFIGURATION; ?></legend>
                    <p id="configuration-info"><?php echo sprintf(TEXT_FORMAT_CONFIG_INFO, zen_href_link(FILENAME_CONFIGURATION, "gID=$configuration_group_id")); ?></p>
<?php
    unset($config_check, $configuration_group_id);
    $dbio_configuration = array(
        TEXT_DBIO_SETTINGS => array(
            TEXT_CSV_DELIMITER => DBIO_CSV_DELIMITER,
            TEXT_CSV_ENCLOSURE => DBIO_CSV_ENCLOSURE,
            TEXT_CSV_ESCAPE => DBIO_CSV_ESCAPE,
            TEXT_CSV_ENCODING => DBIO_CHARSET,
            TEXT_CSV_DATE_FORMAT => DBIO_IMPORT_DATE_FORMAT,
            TEXT_MAX_EXECUTION => DBIO_MAX_EXECUTION_TIME,
            TEXT_SPLIT_RECORD_COUNT => DBIO_SPLIT_RECORD_COUNT,
            TEXT_FILE_DEFAULT_SORT_ORDER => DBIO_FILE_SORT_DEFAULT,
            TEXT_ALLOW_DUPLICATE_MODELS => DBIO_PRODUCTS_ALLOW_DUPLICATE_MODELS,
            TEXT_AUTO_CREATE_CATEGORIES => DBIO_PRODUCTS_AUTO_CREATE_CATEGORIES,
            TEXT_INSERT_REQUIRES_COMMAND => DBIO_PRODUCTS_INSERT_REQUIRES_COMMAND,
            TEXT_DEBUG_ENABLED => DBIO_DEBUG,
            TEXT_DATE_FORMAT => DBIO_DEBUG_DATE_FORMAT,
        ),
        TEXT_DBIO_SYSTEM_SETTINGS => array(
           TEXT_MAX_UPLOAD_FILE_SIZE => ini_get ('upload_max_filesize'),
           TEXT_CHARSET => CHARSET,
           TEXT_DB_CHARSET => DB_CHARSET,
           TEXT_DEFAULT_LANGUAGE => DEFAULT_LANGUAGE,
        ),
    );
?>
                    <table id="config-list">
<?php
    foreach ($dbio_configuration as $config_group_name => $config_values) {
?>
                        <tr>
                            <td colspan="2" class="config-header"><?php echo $config_group_name; ?></td>
                        </tr>
<?php
        foreach ($config_values as $config_title => $config_value) {
?>
                        <tr>
                            <td class="config-title"><?php echo $config_title; ?>:</td>
                            <td class="config-value"><?php echo $config_value; ?></td>
                        </tr>
<?php
        }
    }
?>
                    </table>
                </fieldset>
            </td>
            
        </tr>

    </table>
<?php
    if (isset($_SESSION['dbio_import_result'])) {
?>        
    <div style="display: none;"><div id="file-last-import">
        <div id="file-last-import-info">
            <p><?php echo LAST_STATS_LEAD_IN; ?></p>
            <ul id="flii-details">
                <li><span class="flii-label"><?php echo LAST_STATS_FILE_NAME; ?></span><span class="flii-value"><?php echo $_SESSION['dbio_import_result']['import_filename']; ?></span></li>
                <li><span class="flii-label"><?php echo LAST_STATS_OPERATION; ?></span><span class="flii-value"><?php echo $_SESSION['dbio_import_result']['stats']['action']; ?></span></li>                        
                <li><span class="flii-label"><?php echo LAST_STATS_RECORDS_READ; ?></span><span class="flii-value"><?php echo $_SESSION['dbio_import_result']['stats']['record_count']; ?></span></li>
                <li><span class="flii-label"><?php echo LAST_STATS_RECORDS_INSERTED; ?></span><span class="flii-value"><?php echo $_SESSION['dbio_import_result']['stats']['inserts']; ?></span></li>
                <li><span class="flii-label"><?php echo LAST_STATS_RECORDS_UPDATED; ?></span><span class="flii-value"><?php echo $_SESSION['dbio_import_result']['stats']['updates']; ?></span></li>                        
                <li><span class="flii-label"><?php echo LAST_STATS_WARNINGS; ?></span><span class="flii-value"><?php echo $_SESSION['dbio_import_result']['stats']['warnings']; ?></span></li>
                <li><span class="flii-label"><?php echo LAST_STATS_ERRORS; ?></span><span class="flii-value"><?php echo $_SESSION['dbio_import_result']['stats']['errors']; ?></span></li>
                <li><span class="flii-label"><?php echo LAST_STATS_PARSE_TIME; ?></span><span class="flii-value"><?php echo $_SESSION['dbio_import_result']['stats']['parse_time']; ?></span></li>
            </ul>
        </div>
<?php
        if (isset ($_SESSION['dbio_import_result']['io_errors']) && count ($_SESSION['dbio_import_result']['io_errors']) > 0) {
?>
        <div id="flii-messages">
            <div id="flii-message-intro"><?php echo LAST_STATS_MESSAGES_EXIST; ?></div>
<?php
            foreach ($_SESSION['dbio_import_result']['io_errors'] as $current_error) {
                $message_status = ($current_error[2] & DbIoHandler::DBIO_WARNING) ? 'warning' : (($current_error[2] & DbIoHandler::DBIO_ERROR) ? 'error' : 'info');
?>
            <div class="flii-<?php echo $message_status; ?>"><?php echo str_replace('[*]', '<span class="flii-item">&cross;</span>', $current_error[0]); ?></div>
<?php
            }
?>
        </div>
<?php
        }
?>
    </div></div>
<?php
    }
}  //-END processing, configuration OK
?>
  </div>

<?php require DIR_WS_INCLUDES . 'footer.php'; ?>

<?php
$zen_cart_version = PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR;
if ($zen_cart_version < '1.5.5a') {
?>
<script type="text/javascript" src="//code.jquery.com/jquery-3.4.1.min.js"></script>
<?php
}
?>
<script type="text/javascript" src="includes/javascript/dbio/jquery.colorbox-min.js"></script>
<script type="text/javascript">
<!--
    $(document).ready( function() {
        $(".import-info").colorbox({inline:true, width:"auto"});
        
        $('#select-all').on('click', function() {
            $('.delete-hash').prop('checked', true);
        });
        
        $('#unselect-all').on('click', function() {
            $('.delete-hash').prop('checked', false);
        });
    });

    function init()
    {
        cssjsmenu('navbar');
        if (document.getElementById) {
            var kill = document.getElementById('hoverJS');
            kill.disabled = true;
        }
    }
  
    function checkSubmit () 
    {
        var e = document.getElementById( 'file-action' );
        var file_action = e.options[e.selectedIndex].value;
        if (file_action == 'none') {
            alert( '<?php echo JS_MESSAGE_CHOOSE_ACTION; ?>' );
            return false;
        }
        return true;
    }
    
    function checkDelete ()
    {
        var submitOK = false;
        var e = document.getElementsByClassName( 'delete-hash' );
        var n = e.length;
        var selected = 0;
        for (var i = 0; i < n; i++) {
            if (e[i].checked) {
                selected++;
            }
        }
        if (selected == 0) {
            alert( '<?php echo JS_MESSAGE_NO_FILES_SELECTED; ?>' );
        } else {
            submitOK = confirm( '<?php echo JS_MESSAGE_OK2DELETE_PART1; ?>'+selected+'<?php echo JS_MESSAGE_OK2DELETE_PART2; ?>' );
        }
        return submitOK;
    }
    
    var dbioDescriptions = [];
<?php
foreach ($customization_choices as $index => $info) {
?>
    dbioDescriptions[<?php echo $info['id']; ?>] = '<?php echo addslashes($info['desc']); ?>';
<?php
}
?>
    $( '#custom-change' ).on('change', function() {
        $( '#custom-desc' ).text( dbioDescriptions[this.value] );
    });
    
    $( 'input[type=radio][name="filename_hash"]' ).on('change', function() {
        $( '#file-action option[value!="none"]' ).prop( 'selected', false );
        $( '#file-action option[value="none"]' ).prop( 'selected', true );
        if ( $(this).attr( 'class' ) == 'dbio-log') {
            $( '#file-action option[value!="none"]' ).prop( 'disabled', true );
            $( '#file-action option[value="download"]' ).prop( 'disabled', false );
        } else {
            $( '#file-action option' ).prop( 'disabled', false );
        }
    });
    
    $('select[multiple]').css('resize', 'vertical');
  // -->
</script>
</body>
</html>
<?php 
require DIR_WS_INCLUDES . 'application_bottom.php';
