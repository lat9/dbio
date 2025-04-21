<?php
// -----
// Part of the DataBase I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2025, Vinos de Frutas Tropicales.
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

function dbioGetFieldValue(string $field_name): array|string
{
    return $_POST[$field_name] ?? $_SESSION['dbio_vars'][$field_name] ?? '';
}

function dbioDrawOrdersStatusDropdown(string $field_name): string
{
    global $db;
    $status_array = [
        ['id' => 0, 'text' => TEXT_ALL_ORDERS_STATUS_VALUES],
    ];
    $status_info = $db->Execute(
        "SELECT orders_status_id AS `id`, orders_status_name AS `text`
           FROM " . TABLE_ORDERS_STATUS . "
          WHERE language_id = " . (int)$_SESSION['languages_id'] . "
          ORDER BY orders_status_id ASC"
    );
    foreach ($status_info as $next_status) {
        $next_status['text'] .= ' [' . $next_status['id'] . ']';
        $status_array[] = $next_status;
    }
    return zen_draw_pull_down_menu($field_name, $status_array, dbioGetFieldValue($field_name));
}

function dbioLogError(string $message): void
{
    trigger_error("FATAL error: $message", E_USER_WARNING);
    zen_exit();
}
