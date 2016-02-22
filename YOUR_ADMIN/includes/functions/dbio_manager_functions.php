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