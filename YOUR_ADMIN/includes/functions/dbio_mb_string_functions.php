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
// to abstract the presence (or absence) of the PHP mbstring functions.  This file
// is normally loaded ... unless the current host has chosen not to provide the mbstring library of functions, in
// which case its non-mb string version (dbio_string_functions.php) is loaded.
//
function dbio_string_initialize()
{
    mb_internal_encoding (CHARSET);
    ini_set('mbstring.substitute_character', DBIO_INVALID_CHAR_REPLACEMENT);
}

function dbio_get_string_info()
{
    return 'PHP multi-byte settings: ' . print_r(mb_get_info(), true);
}

function dbio_strtolower($string)
{
    return mb_strtolower($string);
}

function dbio_strtoupper($string)
{
    return mb_strtoupper($string);
}

function dbio_strpos($haystack, $needle, $offset = 0)
{
    return mb_strpos($haystack, $needle, $offset);
}

function dbio_strlen($string)
{
    return mb_strlen($string);
}

function dbio_substr($string, $start, $length = null)
{
    return ($length === null) ? mb_substr($string, $start) : mb_substr($string, $start, $length);
}
