<?php
// -----
// Part of the DataBase I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017-2023, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.2.0
//
require 'includes/application_top.php';
require DIR_FS_ADMIN . 'includes/functions/dbio_manager_functions.php';

$languages = zen_get_languages();

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

if ($ok_to_proceed === false) {
    $error_message = sprintf(DBIO_FORMAT_CONFIGURATION_ERROR, DB_CHARSET, CHARSET);
} else {
    $action = (isset($_GET['action'])) ? $_GET['action'] : '';

    $report_name_length = zen_field_length(TABLE_DBIO_REPORTS, 'report_name');

    require DIR_FS_DBIO_CLASSES . 'DbIo.php';
    $dbio = new DbIo;
    $dbio_handlers = $dbio->getAvailableHandlers();
    if (count($dbio_handlers) === 0) {
        $ok_to_proceed = false;
        $error_message = DBIO_MESSAGE_NO_HANDLERS_FOUND;
    } elseif (!isset($_GET['handler'])) {
        zen_redirect(zen_href_link(FILENAME_DBIO_MANAGER));
    } else {
        $handler_name = $_GET['handler'];
        $handler_valid = false;
        $error = false;
        foreach ($dbio_handlers as $current_handler => $handler_info) {
            if (!isset($handler_info['allow_export_customizations']) || $handler_info['allow_export_customizations'] !== true) {
                continue;
            }
            if ($handler_name === $current_handler) {
                $handler_valid = true;
                break;
            }
        }
        if ($handler_valid === false) {
            $messageStack->add_session(ERROR_UNKNOWN_HANDLER, 'error');
            zen_redirect(zen_href_link(FILENAME_DBIO_CUSTOMIZE));
        }
        $handler_info = $dbio_handlers[$handler_name];
        
        $tID = (int)(isset($_GET['tID'])) ? $_GET['tID'] : 0;
        $template_scope = (int)(isset($_POST['template_scope'])) ? $_POST['template_scope'] : 0;
        $report_name = (isset($_POST['report_name'])) ? zen_db_prepare_input($_POST['report_name']) : '';
        $report_description = (isset($_POST['report_description'])) ? zen_db_prepare_input($_POST['report_description']) : [];
        $customized = (isset($_POST['customized'])) ? zen_db_prepare_input($_POST['customized']) : [];

        switch ($action) {
            case 'update':
            case 'insert_copy':
                if ($tID === 0) {
                    zen_redirect(zen_href_link(FILENAME_DBIO_CUSTOMIZE));
                }                                                            //-Falls thru if tID is set
            case 'insert':
                $dbio_languages = zen_get_languages();
                $name_check = $db->Execute(
                    "SELECT dr.dbio_reports_id, dr.report_name
                       FROM " . TABLE_DBIO_REPORTS . " dr
                      WHERE dr.report_name = '$report_name'
                        AND dr.dbio_reports_id != " . (($action != 'update') ? 0 : $tID) . "
                        AND dr.handler_name = '$handler_name'
                        AND dr.admin_id = $template_scope
                      LIMIT 1"
                );
                if (!$name_check->EOF) {
                    $scope_name = ($template_scope === 0) ? TEXT_SCOPE_PUBLIC : TEXT_SCOPE_PRIVATE;
                    $messageStack->add(sprintf(ERROR_TEMPLATE_NAME_EXISTS, $handler_name, $report_name, $scope_name), 'error');
                    $error = true;
                } elseif (!isset($report_name) || !preg_match('/^[a-zA-Z0-9_]+$/', $report_name)) {
                    $messageStack->add(ERROR_TEMPLATE_NAME_INVALID_CHARS, 'error');
                    $error = true;
                } elseif (strlen($report_name) > $report_name_length) {
                    $messageStack->add(sprintf(ERROR_TEMPLATE_NAME_TOO_LONG, $report_name_length), 'error');
                    $error = true;
                }

                if (!is_array($customized) || count($customized) === 0) {
                    $messageStack->add(ERROR_TEMPLATE_NO_FIELDS, 'error');
                    $error = true;
                }

                if ($error === true) {
                    $action = ($action === 'insert') ? 'new' : (($action === 'update') ? 'edit' : 'copy');
                } else {
                    $report_name = zen_db_input($report_name);
                    $field_info = zen_db_input(json_encode($customized));

                    if ($action === 'insert_copy') {
                        $copy_info = $db->Execute(
                            "SELECT field_info
                               FROM " . TABLE_DBIO_REPORTS . "
                              WHERE dbio_reports_id = $tID
                              LIMIT 1"
                        );
                        if ($copy_info->EOF) {
                            zen_redirect(zen_href_link(FILENAME_DBIO_CUSTOMIZE, zen_get_all_get_params(['action', 'tID'])));
                        }
                        $field_info = $copy_info->fields['field_info'];

                        unset ($copy_info);
                        $copy_info = $db->Execute(
                            "SELECT language_id, report_description
                               FROM " . TABLE_DBIO_REPORTS_DESCRIPTION . "
                              WHERE dbio_reports_id = $tID"
                        );
                        $report_description = [];
                        foreach ($copy_info as $copy) {
                            $report_description[$copy['language_id']] = $copy['report_description'];
                        } 
                        unset($copy_info);
                    }

                    if ($action === 'insert' || $action === 'insert_copy') {
                        $db->Execute(
                            "INSERT INTO " . TABLE_DBIO_REPORTS . "
                                (handler_name, report_name, admin_id, last_updated_by, last_updated, field_info)
                             VALUES
                                ('$handler_name', '$report_name', $template_scope, " . $_SESSION['admin_id'] . ", now(), '$field_info')"
                        );
                        $dbio_reports_id = $db->insert_ID();
                        foreach ($dbio_languages as $current_language) {
                            $the_description = isset($report_description[$current_language['id']]) ? zen_db_input($report_description[$current_language['id']]) : '';
                            $db->Execute(
                                "INSERT INTO " . TABLE_DBIO_REPORTS_DESCRIPTION . "
                                    (dbio_reports_id, language_id, report_description)
                                 VALUES
                                    ($dbio_reports_id, " . $current_language['id'] . ", '$the_description')"
                            );
                        }
                        $messageStack->add_session(sprintf(SUCCESS_TEMPLATE_ADDED, $report_name), 'success');
                    } else {
                        $db->Execute(
                            "UPDATE " . TABLE_DBIO_REPORTS . "
                                SET report_name = '$report_name',
                                    admin_id = $template_scope,
                                    last_updated_by = " . $_SESSION['admin_id'] . ",
                                    last_updated = now(),
                                    field_info = '$field_info'
                              WHERE dbio_reports_id = $tID
                              LIMIT 1"
                        );
                        foreach ($dbio_languages as $current_language) {
                            $db->Execute(
                                "UPDATE " . TABLE_DBIO_REPORTS_DESCRIPTION . "
                                    SET report_description = '" . zen_db_input($report_description[$current_language['id']]) . "'
                                  WHERE dbio_reports_id = $tID
                                    AND language_id = " . $current_language['id'] . "
                                  LIMIT 1"
                            );
                        }
                        $messageStack->add_session(sprintf(SUCCESS_TEMPLATE_UPDATED, $report_name), 'success');
                    }
                    zen_redirect(zen_href_link(FILENAME_DBIO_CUSTOMIZE, zen_get_all_get_params(['action'])));
                }
                break;
            case 'remove':
                $db->Execute(
                    "DELETE FROM " . TABLE_DBIO_REPORTS . "
                      WHERE dbio_reports_id = $tID"
                );
                $db->Execute(
                    "DELETE FROM " . TABLE_DBIO_REPORTS_DESCRIPTION . "
                      WHERE dbio_reports_id = $tID"
                );
                $messageStack->add_session(sprintf(SUCCESS_TEMPLATE_REMOVED, $report_name), 'success');
                zen_redirect(zen_href_link(FILENAME_DBIO_CUSTOMIZE, zen_get_all_get_params(['action'])));
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
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
    <link rel="stylesheet" href="includes/javascript/dbio/colorbox.css">
    <style>
input[type="submit"] { cursor: pointer; }
select { padding: 0.1em; margin: 0.5em; }
table { border-collapse: collapse; }
td, th { padding: 0.7em; }
legend { background-color: #fff8dc; padding: 0.3em; border: 1px solid #e5e5e5; }
#main-wrapper { text-align: center; padding: 1em; }
#main-contents, #the-list, #template { width: 100%; }
#move-fields > div { float: left; }
.move-buttons { padding: 1em 0.5em; }
#move-left, #move-up { padding-bottom: 0.5em; }
#move-left, #move-right, #move-up, #move-down { cursor: pointer; color: #e8a317; }
.file-row td, #file-row-header th { border-bottom: 1px solid #e5e5e5; }
.file-row:nth-child(odd), #file-row-header { background-color: #fbf6d9; }
.file-row:hover { background-color: #ebebeb; }
.smaller { font-size: smaller; }
.instructions { font-size: 12px; padding: 0.5em 1em; }
<?php
if ($action === 'new' || $action === 'edit' || $action === 'copy') {
    if ($action !== 'copy') {
?>
#available { resize: vertical; overflow: hidden; padding: 1px; }
<?php
    }
?>
#customized { resize: vertical; overflow: hidden; padding: 1px; }
<?php
}
?>
    </style>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div id="main-wrapper">
    <h1><?php echo sprintf(HEADING_TITLE, $handler_name); ?> <span class="smaller">v<?php echo DBIO_MODULE_VERSION; ?></span></h1>
<?php
if ($ok_to_proceed === false) {
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
    if ($action !== 'new' && $action !== 'edit' && $action !== 'copy') {
?>
        <tr>
            <td class="text-right">
                <?php echo zen_draw_input_field('return', BUTTON_RETURN, ' title="' . BUTTON_RETURN_TITLE . '" onclick="window.location.href=\'' . zen_href_link(FILENAME_DBIO_MANAGER) . '\'"', false, 'button'); ?>
            </td>
        </tr>
        
        <tr>
            <td class="instructions"><?php echo sprintf(INSTRUCTIONS_MAIN, $handler_name); ?></td>
        </tr>
        
        <tr>
            <td id="main-area"><table id="the-list">
<?php
        $edit_link = zen_href_link(FILENAME_DBIO_CUSTOMIZE, "action=edit&tID=%u&handler=$handler_name");
        $edit_action = zen_draw_input_field('return', BUTTON_EDIT, ' title="' . BUTTON_EDIT_TITLE . '" onclick="window.location.href=\'' . $edit_link . '\'"', false, 'button');

        $copy_link = zen_href_link(FILENAME_DBIO_CUSTOMIZE, "action=copy&tID=%u&handler=$handler_name");
        $copy_action = zen_draw_input_field('return', BUTTON_COPY, ' title="' . BUTTON_COPY_TITLE . '" onclick="window.location.href=\'' . $copy_link . '\'"', false, 'button');

        $remove_action = zen_draw_input_field('return', BUTTON_REMOVE, ' title="' . BUTTON_REMOVE_TITLE . '" onclick="confirmRemove(%u);"', false, 'button');
?>               
                <tr id="file-row-header">
                    <th><?php echo HEADING_SCOPE; ?></th>
                    <th><?php echo HEADING_TEMPLATE_NAME; ?></th>
                    <th><?php echo HEADING_DESCRIPTION; ?></th>
                    <th><?php echo HEADING_UPDATED_BY; ?></th>
                    <th><?php echo HEADING_LAST_UPDATE; ?></th>
                    <th class="text-right"><?php echo HEADING_ACTION; ?></th>
                </tr>
<?php
        $active_templates = $db->Execute(
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
                    <td colspan="6" class="text-center"><?php echo NO_TEMPLATES_EXIST; ?></td>
                </tr>
<?php
        } else {
            foreach ($active_templates as $next_template) {
                if ($next_template['last_updated_by'] === '0') {
                    $last_updated_by = TEXT_SYSTEM_UPDATE;
                } else {
                    $last_updated_by = zen_get_admin_name($next_template['last_updated_by']) . ' [' . $next_template['last_updated_by'] . ']';
                }
                $reports_id = $next_template['dbio_reports_id'];
?>
                <tr class="file-row">
                    <td><?php echo ($next_template['admin_id'] == 0) ? TEXT_SCOPE_PUBLIC : TEXT_SCOPE_PRIVATE; ?></td>
                    <td><?php echo $next_template['report_name']; ?></td>
                    <td><?php echo $next_template['report_description']; ?></td>
                    <td><?php echo $last_updated_by; ?></td>
                    <td><?php echo zen_date_long($next_template['last_updated']); ?></td>
                    <td class="text-right"><?php echo sprintf($edit_action, $reports_id) . '&nbsp;' . sprintf($copy_action, $reports_id) . '&nbsp;' . sprintf($remove_action, $reports_id); ?></td>
                </tr>
<?php
            }
        }
?>
                <tr class="file-row">
                    <td colspan="6" class="text-right">
                        <button type="button" onclick="window.location.href='<?php echo zen_href_link(FILENAME_DBIO_CUSTOMIZE, zen_get_all_get_params(['action', 'handler']) . "action=new&handler=$handler_name"); ?>'">
                            <?php echo BUTTON_NEW; ?>
                        </button>
                    </td>
                </tr>
<?php
    // -----
    // This section renders the page when we're either editing, copying or creating a template.
    //
    } else {
        $next_action = ($action === 'edit') ? 'update' : (($action === 'copy') ? 'insert_copy' : 'insert');

        // -----
        // Initialize template values when editing or copying, if no previous error.  On an error, the previously entered
        // fields have been captures by the "header" processing.
        //
        if ($action !== 'new' && $error === false) {
            $report_info = $db->Execute(
               "SELECT admin_id, report_name, field_info
                  FROM " . TABLE_DBIO_REPORTS . "
                 WHERE handler_name = '$handler_name'
                   AND admin_id IN (0, " . $_SESSION['admin_id'] . ")
                   AND dbio_reports_id = $tID
                 LIMIT 1"
            );
            if ($report_info->EOF) {
                zen_redirect(zen_href_link(FILENAME_DBIO_CUSTOMIZE));
            }
            $template_scope = $report_info->fields['admin_id'];
            $report_name = $report_info->fields['report_name'];
            $customized = json_decode($report_info->fields['field_info']);
            
            unset ($report_info);
            $report_info = $db->Execute(
                "SELECT language_id, report_description
                   FROM " . TABLE_DBIO_REPORTS_DESCRIPTION . "
                  WHERE dbio_reports_id = $tID"
            );
            $report_description = [];
            foreach ($report_info as $next_report) {
                $report_description[$next_report['language_id']] = $next_report['report_description'];
            }
        }

        $heading_format_string = ($action === 'copy') ? HEADING_TITLE_COPY : (($action === 'edit') ? HEADING_TITLE_EDIT : HEADING_TITLE_NEW);
?>
                <tr>
                    <td><?php echo sprintf($heading_format_string, $handler_name); ?></td>
                </tr>

                <tr>
                    <td>
                        <?php echo zen_draw_form('template', FILENAME_DBIO_CUSTOMIZE, zen_get_all_get_params(array('action', 'handler')) . "action=$next_action&amp;handler=$handler_name", 'post', 'id="main-form"'); ?>
                        <table id="template" class="table">
<?php
        $scope_choices = [
            [
                'id' => 0,
                'text' => TEXT_SCOPE_PUBLIC
            ],
            [
                'id' => $_SESSION['admin_id'],
                'text' => TEXT_SCOPE_PRIVATE,
            ],
        ];
?>
                            <tr>
                                <td class="dbio-label"><?php echo COLUMN_HEADING_SCOPE; ?></td>
                                <td class="dbio-field"><?php echo zen_draw_pull_down_menu('template_scope', $scope_choices, $template_scope); ?></td>
                                <td class="dbio-desc"><?php echo INSTRUCTIONS_SCOPE; ?></td>
                            </tr>

                            <tr>
                                <td class="dbio-label"><?php echo COLUMN_HEADING_NAME; ?></td>
                                <td class="dbio-field"><?php echo zen_draw_input_field('report_name', $report_name, 'id="report-name"'); ?></td>
                                <td class="dbio-desc"><?php echo INSTRUCTIONS_NAME; ?></td>
                            </tr>
                        
<?php
        unset($scope_choices);

        $disabled = ($action === 'copy') ? ' disabled' : '';
        
        $dbio_languages = zen_get_languages();
        $language_instructions = INSTRUCTIONS_DESCRIPTION;
        foreach ($dbio_languages as $current_language) {
            $language_image = zen_image(DIR_WS_CATALOG_LANGUAGES . $current_language['directory'] . '/images/' . $current_language['image'], $current_language['name']);
            $description = (isset($report_description[$current_language['id']])) ? $report_description[$current_language['id']] : TEXT_ENTER_REPORT_DESCRIPTION_HERE;
            $description = htmlspecialchars(stripslashes($description), ENT_COMPAT, CHARSET, TRUE);
?>
                            <tr>
                                <td class="dbio-label"><?php echo $language_image . '&nbsp;' . COLUMN_HEADING_DESCRIPTION; ?></td>
                                <td class="dbio-field"><?php echo zen_draw_textarea_field('report_description[' . $current_language['id'] . ']', 'soft', '100%', '5', $description, $disabled); ?></td>
                                <td class="dbio-desc"><?php echo $language_instructions; ?></td>
                            </tr>
<?php
            $languages_description = '&nbsp;';
        }
        unset($dbio_languages, $languages_description);

        unset($dbio);
        $dbio = new DbIo($handler_name);
        $handler_fields = $dbio->handler->getCustomizableFields();

        if (count($customized) === 0 && isset($handler_fields['keys'])) {
            $customized = $handler_fields['keys'];
        }
        $current_fields = [];
        foreach ($customized as $next_field) {
            $current_fields[] = [
                'id' => $next_field,
                'text' => $next_field
            ];
        }

        $available_fields = [];
        foreach ($handler_fields['fields'] as $db_field) {
            if (in_array($db_field, $customized)) {
                continue;
            }
            $available_fields[] = [
                'id' => $db_field,
                'text' => $db_field
            ];
        }
?>
                            <tr>
                                <td class="dbio-label"><?php echo ($action === 'copy') ? COLUMN_HEADING_COPY_FIELDS : COLUMN_HEADING_CHOOSE_FIELDS; ?></td>
                                <td class="dbio-field" id="move-fields">
<?php
        if ($action !== 'copy') {
            // -----
            // Making iOS "happy", hopefully.
            //
            $available_fields_select = zen_draw_pull_down_menu('available_fields', $available_fields, '', 'id="available" multiple="multiple"');
            $available_fields_select = preg_replace('/<option /', '<optgroup disabled hidden></optgroup><option ', $available_fields_select, 1);
?>
                                    <div><?php echo $available_fields_select; ?></div>
                                    <div id="move-left-right" class="move-buttons">
                                        <div id="move-left"><i class="fa fa-arrow-circle-o-left fa-2x"></i></div>
                                        <div id="move-right"><i class="fa fa-arrow-circle-o-right fa-2x"></i></div>
                                    </div>
<?php
        }

        // -----
        // Making iOS "happy", hopefully.
        //
        $customized_fields_select = zen_draw_pull_down_menu('customized[]', $current_fields, '', 'id="customized" multiple="multiple"');
        $customized_fields_select = preg_replace('/<option /', '<optgroup disabled hidden></optgroup><option ', $customized_fields_select, 1);
?>
                                    <div><?php echo $customized_fields_select; ?></div>
<?php
        if ($action !== 'copy') {
?>
                                    <div id="move-up-down" class="move-buttons">
                                        <div id="move-up"><i class="fa fa-arrow-circle-o-up fa-2x"></i></div>
                                        <div id="move-down"><i class="fa fa-arrow-circle-o-down fa-2x"></i></div>
                                    </div>
<?php
        }
?>
                                </td>
                                <td class="dbio-desc"><?php echo ($action === 'copy') ? INSTRUCTIONS_CHOOSE_COPY : INSTRUCTIONS_CHOOSE; ?></td>
                            </tr>
<?php
        if ($next_action === 'update') {
            $button_name = BUTTON_UPDATE;
            $button_title = BUTTON_UPDATE_TITLE;
        } else {
            $button_name = BUTTON_INSERT;
            $button_title = BUTTON_INSERT_TITLE;
        }
?>      
                            <tr>
                                <td>
                                    <?php echo zen_draw_input_field('cancel', BUTTON_CANCEL, ' title="' . BUTTON_CANCEL_TITLE . '" onclick="window.location.href=\'' . zen_href_link(FILENAME_DBIO_CUSTOMIZE, zen_get_all_get_params(['action', 'tID'])) . '\'"', false, 'button'); ?>
                                </td>
                                <td colspan="2" class="text-right">
                                    <?php echo zen_draw_input_field('go_button', $button_name, 'title="' . $button_title . '" id="go-button"', false, 'submit'); ?>
                                </td>
                            </tr>
                        </table>
                        <?php echo '</form>'; ?>
                    </td>
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

<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
<?php
$keys_list = '';
if (isset($handler_fields['keys'])) {
    foreach ($handler_fields['keys'] as $db_field) {
        $keys_list .= '"' . $db_field . '", ';
    }
}
$keys_list = ($keys_list == '') ? '' : substr($keys_list, 0, -2);
?>
<script>
function confirmRemove(tID)
{
    var removeIt = confirm( '<?php echo JS_MESSAGE_CONFIRM_REMOVE; ?>' );
    if (removeIt) {
        var theLocation = '<?php echo zen_href_link(FILENAME_DBIO_CUSTOMIZE, "action=remove&handler=$handler_name&tID=%u"); ?>';
        theLocation = theLocation.replace('%u', tID);
        window.location.href = theLocation;
    }
}

$(document).ready(function(){
    var dbioKeys = [<?php echo $keys_list; ?>];
    for (var i = 0, n = dbioKeys.length; i < n; i++) {
        $('#customized option[value="'+dbioKeys[i]+'"').prop('disabled', true);
    }
});

$(function () {
    function moveLeftRight(origin, dest) {
        $(origin).find(':selected').appendTo(dest);
    }

    $('#move-left').on('click', function () {
        moveLeftRight('#customized', '#available');
    });

    $('#move-right').on('click', function () {
        moveLeftRight('#available', '#customized');
    });
    
    function moveUpDown(direction) {
        var $op = $('#customized option:selected'),
            $this = $(this);
        if ($op.length){
            (direction == 'Up') ? 
                $op.first().prev().before($op) : 
                $op.last().next().after($op);
        }
    }

    $('#move-up').click(function(){
        moveUpDown( 'Up' );
    });

    $('#move-down').click(function(){
        moveUpDown( 'Down' );
    });

    $('#main-form').on('submit', function(event){
        var message = '';
        var reportNameLength = $( '#report-name' ).val().length;
        if (reportNameLength == 0) {
            message = '[*] <?php echo JS_MESSAGE_NAME_CANT_BE_EMPTY; ?>' + '\n';
        } else if (reportNameLength > <?php echo $report_name_length; ?>) {
            message = '[*] <?php echo sprintf (JS_MESSAGE_NAME_TOO_LONG, $report_name_length); ?>' + '\n';
        }
        if ($('#customized option').length == 0) {
            message += '[*] <?php echo JS_MESSAGE_AT_LEAST_ONE_FIELD; ?>' + '\n';
        }
        if (message != '') {
            alert( '<?php echo JS_MESSAGE_ERRORS_EXIST; ?>' + '\n\n' + message + '\n' + '<?php echo JS_MESSAGE_TRY_AGAIN; ?>' );
            event.preventDefault();
        } else {
            $('#customized option').prop('disabled', false);
            $('#customized option').prop('selected', true);
            $('#available option').prop('selected', false);
        }
    });
});
</script>
</body>
</html>
<?php
require DIR_WS_INCLUDES . 'application_bottom.php';
