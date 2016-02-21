<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//
require ('includes/application_top.php');
$languages = zen_get_languages();

// -----
// Make sure that the database and I/O character sets match; any dbIO operation is disallowed otherwise.
//
$ok_to_proceed = true;
$error_message = '';
$info_message = '';
if (strtolower (DB_CHARSET) == 'utf8') {
    if (stripos (CHARSET, 'utf') !== 0) {
        $ok_to_proceed = false;
    }
} else {
    if (stripos (CHARSET, 'utf') === 0) {
        $ok_to_proceed = false;
    }
}

if (!$ok_to_proceed) {
    $error_message = sprintf (DBIO_FORMAT_CONFIGURATION_ERROR, DB_CHARSET, CHARSET);
} else {
    require (DIR_FS_DBIO_CLASSES . 'DbIo.php');
    $dbio = new DbIo;
    $dbio_handlers = $dbio->getAvailableHandlers ();
    if (count ($dbio_handlers) == 0) {
        $ok_to_proceed = false;
        $error_message = DBIO_MESSAGE_NO_HANDLERS_FOUND;
    } else {
        $handler_name_list = '';
        foreach ($dbio_handlers as $handler_name => $handler_info) {
            $handler_name_list .= $handler_name . '|';
        }
        $handler_name_list = substr ($handler_name_list, 0, -1);
        $dbio_files = array ();
        $files_check = glob (DIR_FS_DBIO . 'dbio.*.csv');
        if (is_array ($files_check)) {
            foreach ($files_check as $current_csv_file) {
                $file_stats = stat ($current_csv_file);
                $current_csv_filename = str_replace (DIR_FS_DBIO, '', $current_csv_file);
                if (!preg_match ("~^dbio\.($handler_name_list)\.(.*)\.csv$~", $current_csv_filename, $matches)) {
                    continue;
                }
                $filename_hash = md5 ($current_csv_file);
                $dbio_files[$filename_hash] = array (
                    'full_filepath' => $current_csv_file,
                    'filename_only' => $current_csv_filename,
                    'last_modified' => $file_stats[9],
                    'bytes' => $file_stats[7],
                    'handler_name' => $matches[1],
                    'is_export_only' => (isset ($dbio_handlers[$matches[1]])) ? $dbio_handlers[$matches[1]]['is_export_only'] : false,
                    'is_header_included' => (isset ($dbio_handlers[$matches[1]])) ? $dbio_handlers[$matches[1]]['is_header_included'] : false,
                );
            }
        }
        unset ($files_check, $current_csv_file, $file_stats, $handler_name_list);
        
        $action = (isset ($_GET['action'])) ? $_GET['action'] : '';
        switch ($action) {
            case 'export':
                unset ($_SESSION['dbio_stats']);
                if (!isset ($_POST['handler'])) {
                    $messageStack->add_session (DBIO_FORM_SUBMISSION_ERROR);
                } else {
                    unset ($dbio);
                    $dbio = new DbIo ($_POST['handler']);
                    $export_info = $dbio->dbioExport ('file');
                    if ($export_info['status'] === false) {
                        $messageStack->add_session ($dbio->getMessage ());
                    } else {
                        $messageStack->add_session (sprintf (DBIO_MGR_EXPORT_SUCCESSFUL, $_POST['handler'], $export_info['export_filename']), 'success');
                    }
                    $_SESSION['dbio_stats'] = $dbio->handler->getStatsArray ();
                }
                zen_redirect (zen_href_link (FILENAME_DBIO_MANAGER));
                break;
            case 'file':
                if (!isset ($_POST['file_action']) || !isset ($_POST['filename_hash']) || !isset ($dbio_files[$_POST['filename_hash']])) {
                    $messageStack->add_session (DBIO_FORM_SUBMISSION_ERROR);
                } else {
                    $action_filename = $dbio_files[$_POST['filename_hash']]['full_filepath'];
                    switch ($_POST['file_action']) {
                        case 'none':
                            $messageStack->add_session (sprintf (ERROR_CHOOSE_FILE_ACTION, $action_filename));
                            zen_redirect (zen_href_link (FILENAME_DBIO_MANAGER));
                            break;
                        case 'import-run':
                        case 'import-check':
                            unset ($dbio);
                            if ($dbio_files[$_POST['filename_hash']]['is_export_only']) {
                                $messageStack->add_session (sprintf (ERROR_FILE_IS_EXPORT_ONLY, $action_filename));
                            } else {
                                $dbio = new DbIo ($dbio_files[$_POST['filename_hash']]['handler_name']);
                                $action_types = explode ('-', $_POST['file_action']);
                                $import_info = $dbio->dbioImport ($dbio_files[$_POST['filename_hash']]['filename_only'], $action_types[1]);
                                if ($import_info['status'] === true) {
                                    $messageStack->add_session (sprintf (SUCCESSFUL_FILE_IMPORT, $action_filename), 'success');
                                } else {
                                    $messageStack->add_session ($import_info['message']);
                                }
                            }
                            zen_redirect (zen_href_link (FILENAME_DBIO_MANAGER));
                            break;
                        case 'delete':
                            if (!is_writeable ($action_filename) || !unlink ($action_filename)) {
                                $messageStack->add_session (sprintf (ERROR_CANT_DELETE_FILE, $action_filename));
                            } else {
                                $messageStack->add_session (sprintf (SUCCESS_FILE_DELETED, $action_filename), 'success');
                            }
                            zen_redirect (zen_href_link (FILENAME_DBIO_MANAGER));
                            break;
                        case 'split':
                            if (!is_readable ($action_filename) || ($fp = fopen ($action_filename, "r")) === false) {
                                $messageStack->add_session (sprintf (ERROR_CANT_SPLIT_FILE_OPEN_ERROR, $action_filename));
                            } else {
                                $split_count = 0;
                                $record_count = 0;
                                $header_record = false;
                                $split_error = false;
                                $files_created = array ();
                                $header_included = $dbio_files[$_POST['filename_hash']]['is_header_included'];
                                $split_file_info = pathinfo ($action_filename);
                                $chunk_filename = $split_file_info['dirname'] . '/' . $split_file_info['filename'];
                                $chunk_extension = '.' . $split_file_info['extension'];
                                unset ($split_file_info);
                                while (($data = fgetcsv ($fp)) !== false) {
                                    if ($record_count == 0 && $header_included) {
                                        $header_record = $data;
                                    }
                                    if ($record_count == 0 || $record_count > DBIO_SPLIT_RECORD_COUNT) {
                                        if (isset ($fp_out)) {
                                            fclose ($fp_out);
                                        }
                                        $split_count++;
                                        $out_filename = $chunk_filename . ".part-$split_count" . $chunk_extension;
                                        $fp_out = fopen ($out_filename, "w");
                                        if ($fp_out === false) {
                                            $split_error = true;
                                            $messageStack->add_session (sprintf (ERROR_CREATING_SPLIT_FILE, $out_filename));
                                            break;
                                        }
                                        $files_created[] = $out_filename;
                                        $record_count = 0;
                                        if ($header_included) {
                                            $record_count++;
                                            if (fputcsv ($fp_out, $header_record) === false) {
                                                $split_error = true;
                                                $messageStack->add_session (sprintf (ERROR_WRITING_SPLIT_FILE, $out_filename, $record_count));
                                                break;
                                            }
                                        }
                                    }
                                    if (!($record_count == 0 && $header_included)) {
                                        $record_count++;
                                        if (fputcsv ($fp_out, $data) === false) {
                                            $split_error = true;
                                            $messageStack->add_session (sprintf (ERROR_WRITING_SPLIT_FILE, $out_filename, $record_count));
                                            break;
                                        }
                                    }
                                }

                                if (isset ($fp_out) && $fp_out !== false) {
                                    fclose ($fp_out);
                                }
                                if (!$split_error && !feof ($fp)) {
                                    $messageStack->add_session (sprintf (ERROR_SPLIT_INPUT_NOT_AT_EOF, $action_filename));
                                    $split_error = true;
                                }
                                fclose ($fp);
                                
                                if (!$split_error && $split_count == 1) {
                                    $messageStack->add_session (sprintf (WARNING_FILE_TOO_SMALL_TO_SPLIT, $action_filename, $record_count), 'caution');
                                    $split_error = true;
                                } else {
                                    $messageStack->add_session (sprintf (FILE_SUCCESSFULLY_SPLIT, $action_filename, $split_count), 'success');
                                }
                                
                                if ($split_error) {
                                    foreach ($files_created as $file_to_remove) {
                                        unlink ($file_to_remove);
                                    }
                                }
                            }
                            zen_redirect (zen_href_link (FILENAME_DBIO_MANAGER));
                            break;
                        case 'download':
                            $fp = fopen ($action_filename, 'r');
                            if ($fp === false) {
                                $_SESSION['dbio_message'] = array ( 'error', sprintf (DBIO_CANT_OPEN_FILE, $action_filename) );
                            } else {
                                if (strpos ($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false) {
                                    header('Content-Type: "application/octet-stream"');
                                    header('Content-Disposition: attachment; filename="' . $dbio_files[$_POST['filename_hash']]['filename_only'] . '"');
                                    header('Expires: 0');
                                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                                    header("Content-Transfer-Encoding: binary");
                                    header('Pragma: public');
                                    header("Content-Length: " . $dbio_files[$_POST['filename_hash']]['bytes']);
                                } else {
                                    header('Content-Type: "application/octet-stream"');
                                    header('Content-Disposition: attachment; filename="' . $dbio_files[$_POST['filename_hash']]['filename_only'] . '"');
                                    header("Content-Transfer-Encoding: binary");
                                    header('Expires: 0');
                                    header('Pragma: no-cache');
                                    header("Content-Length: " . $dbio_files[$_POST['filename_hash']]['bytes']);
                                }
                                fpassthru ($fp);
                                fclose ($fp);
                            }
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
    }
}  //-END configuration OK, proceeding ...
?>
<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo TITLE; ?></title>
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
<style type="text/css">
<!--
hr { background: #333 linear-gradient(to right, #ccc, #333, #ccc) repeat scroll 0 0; border: 0 none; height: 1px; }
input[type="submit"] { cursor: pointer; }
#main-wrapper { text-align: center; padding: 1em; }
#configuration { float: right; max-width: 400px; }
#message { border: 2px solid #ddd; display: inline-block; padding: 0.5em; border-radius: 0.75em; }
#message.error { border-color: red; }
#message.info { border-color: green; }
#reports { float: left; }
#reports-instr { padding-bottom: 0.5em; }
#reports-list { padding-left: 0.5em; }
#file-list { display: table; border-collapse: collapse; border: 1px solid #ddd; margin-top: 1em; }
#submit-report { text-align: right; margin: 0.5em 0; }
.buttonLink, .buttonLink:link, .buttonLink:hover, input.buttonLink { 
  background-color:white;
  border:1px solid #003366;
  color:#404040;
  border-radius:6px;
  display:inline-block;
  font-family:Verdana;
  font-size:11px;
  font-weight:bold;
  margin: 2px;
  padding:3px 8px;
  text-decoration:none; }
a.buttonLink:hover { background-color: #dcdcdc; }
.hoverRow:hover > .dataTableRow { background-color: white!important; }
.centered { text-align: center; }
.right { text-align: right; }
.left { text-align: left; }
.float-left { float: left; }
.smaller { font-size: smaller; }
.error { color: red; }
.even { }
.odd, .file-row-header { background-color: #ebebeb; }
.instructions { font-size: 12px; padding-bottom: 10px; padding-top: 10px; }
.name-input { width: 90%; }
.config-list { float: right; }
.config-group { list-style-type: none; text-align: right; padding: 0; }
.config-title { }
.config-value { padding-left: 0.5em; display: inline-block; width: 7em; }
.input { display: inline-block; }
.input-label { float: left; text-align: right; font-weight: bold; padding-right: 0.5em; }
.input-field { float: left; text-align: left; }
.file-row-header, .config-header { font-weight: bold; }
.file-row, .file-row-header { display: table-row; }
.file-row-caption { display: table-caption; border: 1px solid #ddd; padding: 0.5em; }
.file-item{ display: table-cell; padding: 0.5em; border: 1px solid #ddd; }
.file-row:hover { background-color: #ccccff; }
div.export-only span { color: red; font-weight: bold; }
-->
</style>
<script type="text/javascript" src="includes/menu.js"></script>
<script type="text/javascript">
    <!--
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
        if (file_action == 'delete') {
            return confirm( '<?php echo JS_MESSAGE_OK2DELETE; ?>' );
        }
        return true;
    }
    
    function checkFileOptions ()
    {
    }
  // -->
</script>
</head>
<body onload="init();">
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>

  <div id="main-wrapper">
    <h1><?php echo HEADING_TITLE; ?> <span class="smaller">v<?php echo DBIO_MODULE_VERSION; ?></span></h1>
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
    $config_check = $db->Execute ("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'Database I/O Manager Settings' LIMIT 1");
    $configuration_group_id = ($config_check->EOF) ? 0 : $config_check->fields['configuration_group_id'];
?>
    <div id="main-contents"><?php echo zen_draw_form ('dbio', FILENAME_DBIO_MANAGER, 'action=export'); ?>
        <div id="configuration">
            <div id="configuration-info">This section shows the current settings that affect the <em>dbIO Manager</em>'s operation.  The <em>dbIO Settings</em> values can be changed by clicking <a href="<?php echo zen_href_link (FILENAME_CONFIGURATION, "gID=$configuration_group_id"); ?>">here</a>.</div>
<?php
    unset ($config_check, $configuration_group_id);
    $dbio_configuration = array (
        'dbIO Settings' => array (
            'CSV: Delimiter' => DBIO_CSV_DELIMITER,
            'CSV: Enclosure' => DBIO_CSV_ENCLOSURE,
            'CSV: Escape' => DBIO_CSV_ESCAPE,
            'CSV: Encoding' => DBIO_CHARSET,
            'CSV: Import Date Format' => DBIO_IMPORT_DATE_FORMAT,
            'Maximum Execution Time' => DBIO_MAX_EXECUTION_TIME,
            'Split Record Count' => DBIO_SPLIT_RECORD_COUNT,
            'Debug Enabled' => DBIO_DEBUG,
            'Display/Log Date Format' => DBIO_DEBUG_DATE_FORMAT,
        ),
        'System Settings' => array (
            'Maximum Upload File Size' => ini_get ('upload_max_filesize'),
           'Internal Character Encoding' => CHARSET,
           'Database Character Encoding' => DB_CHARSET,
           'Default Language' => DEFAULT_LANGUAGE,
        ),
    );
    foreach ($dbio_configuration as $config_group_name => $config_values) {
?>
            <div class="config-list">
                <div class="config-header"><?php echo $config_group_name; ?></div>
                <ul class="config-group">
<?php
        foreach ($config_values as $config_title => $config_value) {
?>
                    <li class="config-item"><span class="config-title"><?php echo $config_title; ?>:</span><span class="config-value"><?php echo $config_value; ?></span></li>
<?php
        }
?>
                </ul>
            </div>
            <div class="clearBoth"></div>
<?php
    }
?>
        </div>
        <div id="reports" class="float-left left">
            <div id="reports-instr">The following <em>dbIO</em> options are available:</div>
            <div id="reports-list">
<?php
    foreach ($dbio_handlers as $handler_name => $handler_info) {
        echo zen_draw_radio_field ('handler', $handler_name, true) . '&nbsp;&nbsp;<strong>' . $handler_name . ':</strong>&nbsp;' . $handler_info['description'] . '<br />';
    }
?>
            </div>
            <div id="submit-report"><?php echo zen_draw_input_field ('export_button', BUTTON_EXPORT, 'title="' . BUTTON_EXPORT_TITLE . '"', false, 'submit'); ?></div>
        </div><div class="clearBoth"></div></form>
        
        <div id="file-list" class="clearBoth"><?php echo zen_draw_form ('file_form', FILENAME_DBIO_MANAGER, 'action=file'); ?>
<?php
    if (!is_array ($dbio_files) || count ($dbio_files) == 0) {
?>
            <div class="no-files">No import/export files available for the current handler.</div>
<?php
    } else {
        $file_actions_array = array (
            array ( 'id' => 'none', 'text' => 'Please Select' ),
            array ( 'id' => 'split', 'text' => 'Split' ),
            array ( 'id' => 'delete', 'text' => 'Delete' ),
            array ( 'id' => 'import-run', 'text' => 'Import (Full)' ),
            array ( 'id' => 'import-check', 'text' => 'Import (Check-only)' ),
            array ( 'id' => 'download', 'text' => 'Download' ),
        );
        $file_action = (isset ($_POST['file_action'])) ? $_POST['file_action'] : 'none';
?>
            <div class="file-row-caption">Choose the action to be performed for the file selected below: <?php echo zen_draw_pull_down_menu ('file_action', $file_actions_array, $file_action, 'id="file-action"'); ?>&nbsp;&nbsp;<?php echo zen_draw_input_field ('go_button', DBIO_BUTTON_GO, 'title="' . DBIO_BUTTON_GO_TITLE . '" onclick="return checkSubmit ();"', false, 'submit'); ?><hr /><?php echo TEXT_FILE_ACTION_INSTRUCTIONS; ?></div>
            <div class="file-row-header">
                <div class="file-item">Choose File</div>
                <div class="file-item left">File Name</div>
                <div class="file-item">Bytes</div>
                <div class="file-item">Last-Modified Date</div>
            </div>
<?php
        $even_odd = 'even';
        $button_split_title = sprintf (BUTTON_SPLIT_TITLE, (int)DBIO_SPLIT_RECORD_COUNT);
        
        foreach ($dbio_files as $name_hash => $file_info) {
?>
            <div class="file-row <?php echo $even_odd; ?>">
                <div class="file-item"><?php echo zen_draw_radio_field ('filename_hash', $name_hash, true, '', 'onclick="checkFileOptions ();"'); ?></div>
                <div class="file-item left"><?php echo $file_info['filename_only']; ?></div>
                <div class="file-item"><?php echo $file_info['bytes']; ?></div>
                <div class="file-item"><?php echo date (DBIO_DEBUG_DATE_FORMAT, $file_info['last_modified']); ?></div>
            </div>
<?php
            $even_odd = ($even_odd == 'even') ? 'odd' : 'even';
        }
    }
?>
        </form></div>
    </div>
<?php
}  //-END processing, configuration OK
?>
  </div>

<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
