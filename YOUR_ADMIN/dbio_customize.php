<?php
// -----
// Part of the DataBase I/O Manager (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017, Vinos de Frutas Tropicales.
//
require ('includes/application_top.php');
require (DIR_FS_ADMIN . 'includes/functions/dbio_manager_functions.php');

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
    $action = (isset ($_GET['action'])) ? $_GET['action'] : '';
    
    $report_name_length = zen_field_length (TABLE_DBIO_REPORTS, 'report_name');
    
    require (DIR_FS_DBIO_CLASSES . 'DbIo.php');
    $dbio = new DbIo;
    $dbio_handlers = $dbio->getAvailableHandlers ();
    if (count ($dbio_handlers) == 0) {
        $ok_to_proceed = false;
        $error_message = DBIO_MESSAGE_NO_HANDLERS_FOUND;
    } else {
        $available_handlers = array ();
        $handler_name = (isset ($_GET['handler'])) ? $_GET['handler'] : false;
        $first_handler = false;
        $handler_valid = false;
        foreach ($dbio_handlers as $current_handler => $handler_info) {
            if (!isset ($handler_info['allow_export_customizations']) || $handler_info['allow_export_customizations'] !== true) {
                continue;
            }
            if ($handler_name !== false && $handler_name == $current_handler) {
                $handler_valid = true;
            }
            if ($first_handler === false) {
                $first_handler = $current_handler;
            }
            $available_handlers[] = array (
                'id' => $current_handler,
                'text' => $current_handler
            );
        }
        if ($handler_name !== false) {
            if (!$handler_valid) {
                $messageStack->add_session (ERROR_UNKNOWN_HANDLER, 'error');
                zen_redirect (zen_href_link (FILENAME_DBIO_CUSTOMIZE));
            }
        } else {
            $handler_name = $first_handler;
        }
        $handler_info = $dbio_handlers[$handler_name];
        
        $template_scope = (int)(isset ($_POST['template_scope'])) ? $_POST['template_scope'] : 0;
        $report_name = (isset ($_POST['report_name'])) ? zen_db_prepare_input ($_POST['report_name']) : '';
        $report_description = (isset ($_POST['report_description'])) ? zen_db_prepare_input ($_POST['report_description']) : array ();
        $customized = (isset ($_POST['customized'])) ? zen_db_prepare_input ($_POST['customized']) : array ();
        $active_template = (int)(isset ($_POST['active_template'])) ? $_POST['active_template'] : 0;
        
        switch ($action) {
            case 'choose':
                zen_redirect (zen_href_link (FILENAME_DBIO_CUSTOMIZE, zen_get_all_get_params (array ('action'))));
                break;
            case 'insert':
            case 'update':
                $dbio_languages = zen_get_languages ();
                $error = false;
                $name_check = $db->Execute (
                    "SELECT dr.dbio_reports_id, dr.report_name
                       FROM " . TABLE_DBIO_REPORTS . " dr
                      WHERE dr.report_name = '$report_name'
                        AND dr.dbio_reports_id != $active_template
                        AND dr.handler_name = '$handler_name'
                        AND dr.admin_id = $template_scope
                      LIMIT 1"
                );
                if (!$name_check->EOF) {
                    $scope_name = ($template_scope == 0) ? TEXT_SCOPE_PUBLIC : TEXT_SCOPE_PRIVATE;
                    $messageStack->add (sprintf (ERROR_TEMPLATE_NAME_EXISTS, $handler_name, $report_name, $scope_name), 'error');
                    $error = true;
                } elseif (!isset ($report_name) || !preg_match ('/^[a-zA-Z0-9_]+$/', $report_name)) {
                    $messageStack->add (ERROR_TEMPLATE_NAME_INVALID_CHARS, 'error');
                    $error = true;
                } elseif (strlen ($report_name) > $report_name_length) {
                    $messageStack->add (sprintf (ERROR_TEMPLATE_NAME_TOO_LONG, $report_name_length), 'error');
                    $error = true;
                }
                
                if (!is_array ($customized) || count ($customized) == 0) {
                    $messageStack->add (ERROR_TEMPLATE_NO_FIELDS, 'error');
                    $error = true;
                }
                
                if ($error) {
                    $action = ($action == 'insert') ? 'new' : 'edit';
                } else {
                    $report_name = zen_db_input ($report_name);
                    $field_info = zen_db_input (json_encode ($customized));
                    if ($action == 'insert') {
                        $db->Execute (
                            "INSERT INTO " . TABLE_DBIO_REPORTS . " (handler_name, report_name, admin_id, last_updated_by, last_updated, field_info)
                                VALUES ('$handler_name', '$report_name', $template_scope, " . $_SESSION['admin_id'] . ", now(), '$field_info')"
                        );
                        $dbio_reports_id = $db->insert_ID();
                        foreach ($dbio_languages as $current_language) {
                            $the_description = zen_db_input ($report_description[$current_language['id']]);
                            $db->Execute (
                                "INSERT INTO " . TABLE_DBIO_REPORTS_DESCRIPTION . " (dbio_reports_id, language_id, report_description)
                                    VALUES ( $dbio_reports_id, " . $current_language['id'] . ", '$the_description')"
                            );
                        }
                        $messageStack->add_session (sprintf (SUCCESS_TEMPLATE_ADDED, $report_name), 'success');
                    } else {
                    }
                    zen_redirect (zen_href_link (FILENAME_DBIO_CUSTOMIZE, zen_get_all_get_params (array ('action'))));
                }
                break;
            case 'remove':
                zen_redirect (zen_href_link (FILENAME_DBIO_CUSTOMIZE, zen_get_all_get_params (array ('action'))));
                break;
            case 'copy':
            case 'edit':        //-Fall-through ...
            case 'new':
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
<link rel="stylesheet" type="text/css" href="includes/stylesheet.css" />
<link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS" />
<?php
$zen_cart_version = PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR;
if (version_compare ($zen_cart_version, '1.5.5', '<')) {
?>
<link rel="stylesheet" type="text/css" href="//maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css" />
<?php
}
?>
<link rel="stylesheet" type="text/css" href="includes/javascript/dbio/colorbox.css" />
<style type="text/css">
<!--
input[type="submit"] { cursor: pointer; }
select { padding: 0.1em; margin: 0.5em; }
td, th { padding: 0.5em; }
legend { background-color: #fff8dc; padding: 0.3em; border: 1px solid #e5e5e5; }
#main-wrapper { text-align: center; padding: 1em; }
#main-contents, #the-list, #template { width: 100%; }
#move-fields > div { float: left; }
#move-left, #move-right, #move-up, #move-down { cursor: pointer; }

.centered { text-align: center; }
.right { text-align: right; }
.left { text-align: left; }
.smaller { font-size: smaller; }
.instructions { font-size: 12px; padding-bottom: 10px; padding-top: 10px; }

.file-row:nth-child(odd) { background-color: #fbf6d9; }
-->
</style>
<script type="text/javascript" src="includes/menu.js"></script>
</head>
<body onload="init();">
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<div id="main-wrapper">
    <h1><?php echo HEADING_TITLE; ?> <span class="smaller">v<?php echo DBIO_MODULE_VERSION; ?></span></h1>
<?php
if (!$ok_to_proceed) {
?>
    <div id="message" class="error"><?php echo $error_message; ?></div>
<?php
} else {
?>
    <table id="main-contents">
<?php
    // -----
    // If we're not editing (or creating) a template's details, just list the basic information for the templates
    // currently existing for the currently-selected handler.
    //
    if ($action != 'new' && $action != 'edit') {
?>
        <tr>
            <td id="dbio-choose"><?php echo TEXT_CHOOSE_HANDLER . ' ' . zen_draw_form ('dbio-select', FILENAME_DBIO_CUSTOMIZE, zen_get_all_get_params (array ('action', 'handler')) . 'action=choose', 'get') . zen_draw_pull_down_menu ('handler', $available_handlers, $handler_name, 'onchange="this.form.submit();"'); ?></form></td>
        </tr>
        
        <tr>
            <td id="main-area"><table id="the-list">
<?php
        $action_options = array (
            array (
                'id' => 'choose',
                'text' => DBIO_ACTION_PLEASE_CHOOSE
            ),
            array (
                'id' => 'edit',
                'text' => DBIO_ACTION_EDIT
            ),
            array (
                'id' => 'copy',
                'text' => DBIO_ACTION_COPY
            ),
            array (
                'id' => 'remove',
                'text' => DBIO_ACTION_REMOVE
            ),
        );
?>
                <tr>
                    <td colspan="6" class="right"><?php echo zen_draw_pull_down_menu ('choose_action', $action_options, 'choose'); ?></td>
                </tr>
                
                <tr>
                    <th><?php echo HEADING_SCOPE; ?></th>
                    <th><?php echo HEADING_TEMPLATE_NAME; ?></th>
                    <th><?php echo HEADING_DESCRIPTION; ?></th>
                    <th><?php echo HEADING_UPDATED_BY; ?></th>
                    <th><?php echo HEADING_LAST_UPDATE; ?></th>
                    <th><?php echo HEADING_ACTION; ?></th>
                </tr>

<?php
        $active_templates = $db->Execute (
            "SELECT dr.dbio_reports_id, dr.admin_id, dr.report_name, dr.last_updated_by, dr.last_updated, drd.report_description
                           FROM " . TABLE_DBIO_REPORTS . " dr, " . TABLE_DBIO_REPORTS_DESCRIPTION . " drd
                          WHERE dr.handler_name = '$handler_name'
                            AND dr.admin_id IN (0, " . $_SESSION['admin_id'] . ")
                            AND dr.dbio_reports_id = drd.dbio_reports_id
                            AND drd.language_id = " . $_SESSION['languages_id'] . "
                       ORDER BY dr.report_name"
        );
        if ($active_templates->EOF) {
?>
                <tr>
                    <td colspan="6" class="center"><?php echo NO_TEMPLATES_EXIST; ?></td>
                </tr>
<?php
        } else {
            while (!$active_templates->EOF) {
                if ($active_templates->fields['last_updated_by'] == 0) {
                    $last_updated_by = TEXT_SYSTEM_UPDATE;
                } else {
                    $last_updated_by = zen_get_admin_name ($active_templates->fields['last_updated_by']) . ' [' . $active_templates->fields['last_updated_by'] . ']';
                }
?>
                <tr>
                    <td><?php echo ($active_templates->fields['admin_id'] == 0) ? TEXT_SCOPE_PUBLIC : TEXT_SCOPE_PRIVATE; ?></td>
                    <td><?php echo $active_templates->fields['report_name']; ?></td>
                    <td><?php echo $active_templates->fields['report_description']; ?></td>
                    <td><?php echo $last_updated_by; ?></td>
                    <td><?php echo zen_date_long ($active_templates->fields['last_updated']); ?></td>
                </tr>
<?php
                $active_templates->MoveNext ();
            }
        }
?>
                <tr>
                    <td colspan="6" class="right"><button type="button" onclick="window.location.href='<?php echo zen_href_link (FILENAME_DBIO_CUSTOMIZE, zen_get_all_get_params (array ('action', 'handler')) . "action=new&amp;handler=$handler_name"); ?>'"><?php echo BUTTON_NEW; ?></button></td>
                </tr>
<?php
    // -----
    // This section renders the page when we're either editing or creating a template.
    //
    } else {
        $next_action = ($action == 'new') ? 'insert' : 'update';
?>
                <tr>
                    <td><?php echo sprintf (HEADING_CUSTOMIZING_FOR_HANDLER, $handler_name); ?></td>
                </tr>
                
                <tr>
                    <td><?php echo zen_draw_form ('template', FILENAME_DBIO_CUSTOMIZE, zen_get_all_get_params (array ('action', 'handler')) . "action=$next_action&amp;handler=$handler_name", 'post', 'id="main-form"'); ?><table id="template" class="table">
<?php
        $scope_choices = array (
            array (
                'id' => 0,
                'text' => TEXT_SCOPE_PUBLIC
            ),
            array (
                'id' => $_SESSION['admin_id'],
                'text' => TEXT_SCOPE_PRIVATE,
            )
        );
?>
                        <tr>
                            <td class="dbio-label"><?php echo COLUMN_HEADING_SCOPE; ?></td>
                            <td class="dbio-field"><?php echo zen_draw_pull_down_menu ('template_scope', $scope_choices, $template_scope); ?></td>
                            <td class="dbio-desc"><?php echo INSTRUCTIONS_SCOPE; ?></td>
                        </tr>
                        
                        <tr>
                            <td class="dbio-label"><?php echo COLUMN_HEADING_NAME; ?></td>
                            <td class="dbio-field"><?php echo zen_draw_input_field ('report_name', $report_name, 'id="report-name"'); ?></td>
                            <td class="dbio-desc"><?php echo INSTRUCTIONS_NAME; ?></td>
                        </tr>
                        
<?php
        unset ($scope_choices);
        
        $dbio_languages = zen_get_languages ();
        $language_instructions = INSTRUCTIONS_DESCRIPTION;
        foreach ($dbio_languages as $current_language) {
            $language_image = zen_image (DIR_WS_CATALOG_LANGUAGES . $current_language['directory'] . '/images/' . $current_language['image'], $current_language['name']);
?>

                        <tr>
                            <td class="dbio-label"><?php echo $language_image . '&nbsp;' . COLUMN_HEADING_DESCRIPTION; ?></td>
                            <td class="dbio-field"><?php echo zen_draw_textarea_field ('report_description[' . $current_language['id'] . ']', 'soft', '100%', '5', htmlspecialchars (stripslashes ($report_description[$current_language['id']]), ENT_COMPAT, CHARSET, TRUE)); ?></td>
                            <td class="dbio-desc"><?php echo $language_instructions; ?></td>
                        </tr>
<?php
            $languages_description = '&nbsp;';
        }
        unset ($dbio_languages, $languages_description);
 
        unset ($dbio);
        $dbio = new DbIo ($handler_name);
        $handler_fields = $dbio->handler->getCustomizableFields ();

        if (count ($customized) == 0) {
            $customized = $handler_fields['keys'];
        }
        $current_fields = array ();      
        foreach ($customized as $next_field) {
            $current_fields[] = array (
                'id' => $next_field,
                'text' => $next_field
            );
        }
        
        $available_fields = array ();
        foreach ($handler_fields['fields'] as $db_field) {
            if (in_array ($db_field, $customized)) {
                continue;
            }
            $available_fields[] = array (
                'id' => $db_field,
                'text' => $db_field
            );
        }
?>
                        <tr>
                            <td class="dbio-label"><?php echo COLUMN_HEADING_CHOOSE_FIELDS; ?></td>
                            <td class="dbio-field" id="move-fields">
                                <div><?php echo zen_draw_pull_down_menu ('available_fields', $available_fields, '', 'id="available" multiple="multiple"') . PHP_EOL; ?></div>
                                <div id="move-left-right">
                                    <div id="move-left"><i class="fa fa-arrow-circle-o-left fa-2x"></i></div>
                                    <div id="move-right"><i class="fa fa-arrow-circle-o-right fa-2x"></i></div>
                                </div>
                                <div><?php echo zen_draw_pull_down_menu ('customized[]', $current_fields, '', 'id="customized" multiple="multiple"'). PHP_EOL; ?></div>
                                <div id="move-up-down">
                                    <div id="move-up"><i class="fa fa-arrow-circle-o-up fa-2x"></i></div>
                                    <div id="move-down"><i class="fa fa-arrow-circle-o-down fa-2x"></i></div>
                                </div>
                            </td>
                            <td class="dbio-desc"><?php echo INSTRUCTIONS_CHOOSE; ?></td>
                        </tr>
<?php
        if ($next_action == 'insert') {
            $button_name = BUTTON_INSERT;
            $button_title = BUTTON_INSERT_TITLE;
        } else {
            $button_name = BUTTON_UPDATE;
            $button_title = BUTTON_UPDATE_TITLE;
        }
?>      
                        <tr>
                            <td colspan="3" class="right"><?php echo zen_draw_input_field ('go_button', $button_name, 'title="' . $button_title . '" id="go-button"', false, 'submit'); ?></td>
                        </tr>

                    </table></form></td>
                </tr>
<?php   
    }  //-END rendering page for edit/insert
?>            
            </table></td>
        </tr>
    </table>
<?php
}  //-END processing, configuration OK
?>
</div>

<?php require (DIR_WS_INCLUDES . 'footer.php'); ?>

<?php
$zen_cart_version = PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR;
if (version_compare ($zen_cart_version, '1.5.5', '<')) {
?>
<script src="//code.jquery.com/jquery-1.11.3.min.js"></script>
<?php
}

$keys_list = '';
foreach ($handler_fields['keys'] as $db_field) {
    $keys_list .= '"' . $db_field . '", ';
}
$keys_list = ($keys_list == '') ? '' : substr ($keys_list, 0, -2);
?>
<script type="text/javascript">
<!--
$(document).ready(function(){
    var dbioKeys = [<?php echo $keys_list; ?>];
    for (var i = 0, n = dbioKeys.length; i < n; i++) {
        $( '#customized option[value="'+dbioKeys[i]+'"' ).prop( 'disabled', true );
    }
});

$(function () {  
    function moveLeftRight(origin, dest) {
        $(origin).find( ':selected' ).appendTo(dest);
    }

    $( '#move-left' ).on('click', function () {
        moveLeftRight( '#customized', '#available');
    });

    $(' #move-right' ).on( 'click', function () {
        moveLeftRight( '#available', '#customized');
    });
    
    function moveUpDown(direction) {
        var $op = $( '#customized option:selected' ),
            $this = $(this);
        if ($op.length){
            (direction == 'Up') ? 
                $op.first().prev().before($op) : 
                $op.last().next().after($op);
        }
    }

    $( '#move-up' ).click(function(){
        moveUpDown( 'Up' );
    });
    
    $( '#move-down' ).click(function(){
        moveUpDown( 'Down' );
    });
    
    $( '#main-form' ).on( 'submit', function(event){
        var message = '';
        var reportNameLength = $( '#report-name' ).val().length;
        if (reportNameLength == 0) {
            message = '[*] <?php echo JS_MESSAGE_NAME_CANT_BE_EMPTY; ?>' + '\n';
        } else if (reportNameLength > <?php echo $report_name_length; ?>) {
            message = '[*] <?php echo sprintf (JS_MESSAGE_NAME_TOO_LONG, $report_name_length); ?>' + '\n';
        }
        if ($( '#customized option' ).length == 0) {
            message += '[*] <?php echo JS_MESSAGE_AT_LEAST_ONE_FIELD; ?>' + '\n';
        }
        if (message != '') {
            alert( '<?php echo JS_MESSAGE_ERRORS_EXIST; ?>' + '\n\n' + message + '\n' + '<?php echo JS_MESSAGE_TRY_AGAIN; ?>' );
            event.preventDefault();
        } else {
            $( '#customized option' ).prop( 'disabled', false);
            $( '#customized option' ).prop( 'selected', true);
            $( '#available option' ).prop( 'selected', false);
        }
    });

});
<?php
if ($action == 'new' || $action == 'edit') {
?>
document.getElementById( 'available' ).style.resize = 'vertical';
document.getElementById( 'customized' ).style.resize = 'vertical';
<?php
}
?>   
  // -->
</script>
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
