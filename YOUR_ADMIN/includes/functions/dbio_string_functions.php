<?php
// -----
// Part of the DataBase I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2018, Vinos de Frutas Tropicales.
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

// -----
// This function-file provides string-related functions used by the DbIo and its handlers as a method
// to abstract the presence (or absence) of the PHP mbstring functions.  The dbio_mb_string_functions.php file
// is normally loaded ... unless the current host has chosen not to provide the mbstring library of functions.
//
function dbio_string_initialize()
{
}

function dbio_get_string_info()
{
    return 'PHP single-byte; "php-mbstring" extension is not loaded';
}

function dbio_strtolower($string)
{
    return strtolower($string);
}

function dbio_strtoupper($string)
{
    return strtoupper($string);
}

function dbio_strpos($haystack, $needle, $offset = 0)
{
    return strpos($haystack, $needle, $offset);
}

function dbio_strlen($string)
{
    return strlen($string);
}

function dbio_substr($string, $start, $length = null)
{
    return ($length === null) ? substr($string, $start) : substr($string, $start, $length);
}