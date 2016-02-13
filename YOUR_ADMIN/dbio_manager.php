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
                if (!preg_match ("~^dbio\.($handler_name_list)\.(.*)\.csv$~", $current_csv_filename)) {
                    continue;
                }
                $filename_hash = md5 ($current_csv_file);
                $dbio_files[$filename_hash] = array (
                    'full_filepath' => $current_csv_file,
                    'filename_only' => $current_csv_filename,
                    'last_modified' => $file_stats[9],
                    'bytes' => $file_stats[7],
                );
            }
        }
        unset ($files_check, $current_csv_file, $file_stats, $handler_name_list);
        
        $action = (isset ($_GET['action'])) ? $_GET['action'] : '';
        switch ($action) {
            case 'process':
                if (!isset ($_POST['handler_name']) || !isset ($_POST['report_action']) || !in_array ($_POST['report_action'], array ('import', 'export'))) {
                    $error_message = DBIO_FORM_SUBMISSION_ERROR;
                } elseif ($_POST['report_action'] == 'export') {
                    unset ($dbio);
                    $dbio = new DbIo ($_POST['handler_name']);
                    $export_info = $dbio->dbioExport ('file');
                    if ($export_info['status'] === false) {
                        $error_message = $dbio->getMessage ();
                    } else {
                        $info_message = sprintf (DBIO_MGR_EXPORT_SUCCESSFUL, $_POST['handler_name'], $export_info['export_filename']);
                    }
                } else {
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
#file-list { display: table; border-collapse: collapse; border: 1px solid #ddd; }
#main-wrapper { text-align: center; padding: 1em; }
#message { border: 2px solid #ddd; display: inline-block; padding: 0.5em; border-radius: 0.75em; }
#message.error { border-color: red; }
#message.info { border-color: green; }
#reports-instr { padding-bottom: 0.5em; }
#reports-list { padding-left: 0.5em; }
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
.smaller { font-size: smaller; }
.error { color: red; }
.even { }
.odd, .file-row-header { background-color: #ebebeb; }
.instructions { font-size: 12px; padding-bottom: 10px; padding-top: 10px; }
.name-input { width: 90%; }
.input { display: inline-block; }
.input-label { float: left; text-align: right; font-weight: bold; padding-right: 0.5em; }
.input-field { float: left; text-align: left; }
.file-row-header { font-weight: bold; }
.file-row, .file-row-header { display: table-row; }
.file-item { display: table-cell; padding: 0.5em; border: 1px solid #ddd; }
.file-row:hover { background-color: #ccccff; }
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
?>
    <div id="main-contents"><?php echo zen_draw_form ('dbio', FILENAME_DBIO_MANAGER, 'action=process'); ?>
        <div id="reports" class="left">
            <div id="reports-instr">The following import/export options are available:</div>
            <div id="reports-list">
<?php
    foreach ($dbio_handlers as $handler_name => $handler_info) {
        echo zen_draw_radio_field ('handler', $handler_name, true) . '&nbsp;&nbsp;<strong>' . $handler_name . ':</strong>&nbsp;' . $handler_info['description'];
    }
?>
            </div>
        </div>
        <div id="file-list">
<?php
    if (!is_array ($dbio_files) || count ($dbio_files) == 0) {
?>
            <div class="no-files">No import/export files available for the current handler.</div>
<?php
    } else {
?>
            <div class="file-row-header">
                <div class="file-item left">File Name</div>
                <div class="file-item">Bytes</div>
                <div class="file-item">Last-Modified Date</div>
                <div class="file-item">Split</div>
                <div class="file-item">Delete</div>
                <div class="file-item">Import</div>
                <div class="file-item">Download</div>
            </div>
<?php
        $even_odd = 'even';
        foreach ($dbio_files as $name_hash => $file_info) {
?>
            <div class="file-row <?php echo $even_odd; ?>">
                <div class="file-item left"><?php echo $file_info['filename_only']; ?></div>
                <div class="file-item"><?php echo $file_info['bytes']; ?></div>
                <div class="file-item"><?php echo date (DBIO_DEBUG_DATE_FORMAT, $file_info['last_modified']); ?></div>
                <div class="file-item">&nbsp;</div>
                <div class="file-item">&nbsp;</div>
                <div class="file-item">&nbsp;</div>
                <div class="file-item">&nbsp;</div>
            </div>
<?php
            $even_odd = ($even_odd == 'even') ? 'odd' : 'even';
        }
    }
?>
        </div>
    </form></div>
<?php
}  //-END processing, configuration OK
?>
  </div>

<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
