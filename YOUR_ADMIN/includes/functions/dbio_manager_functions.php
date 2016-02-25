<?php
// -----
// Part of the DataBase I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

function dbioGetFieldValue ($field_name)
{
    return (isset ($_POST[$field_name])) ? $_POST[$field_name] : ((isset ($_SESSION['dbio_vars']) && isset ($_SESSION['dbio_vars'][$field_name])) ? $_SESSION['dbio_vars'][$field_name] : '');
}

function dbioDrawOrdersStatusDropdown ($field_name)
{
    global $db;
    $status_array = array ( array ( 'id' => 0, 'text' => TEXT_ALL_ORDERS_STATUS_VALUES ) );
    $status_info = $db->Execute ("SELECT orders_status_id as `id`, orders_status_name as `text` FROM " . TABLE_ORDERS_STATUS . " WHERE language_id = " . (int)$_SESSION['languages_id'] . " ORDER BY orders_status_id ASC");
    while (!$status_info->EOF) {
        $status_info->fields['text'] .= ' [' . $status_info->fields['id'] . ']';
        $status_array[] = $status_info->fields;
        $status_info->MoveNext ();
    }
    return zen_draw_pull_down_menu ($field_name, $status_array, dbioGetFieldValue ($field_name));
}

function dbioFormatValidateDate ($date_value, $imported = false, $log = true)
{
    $parsed_date = date_parse ($date_value);
    if ($parsed_date['error_count'] == 0 && checkdate ($parsed_date['month'], $parsed_date['day'], $parsed_date['year'])) {
        $return_date = sprintf ('%u-%02u-%02u', $parsed_date['year'], $parsed_date['month'], $parsed_date['day']);
    } else {
        $return_date = false;
        if ($log) {
            trigger_error ("Invalid date ($date_value) supplied.\n" . var_export ($parsed_date, true), E_USER_WARNING);
        }
    }
    return $return_date;
}
