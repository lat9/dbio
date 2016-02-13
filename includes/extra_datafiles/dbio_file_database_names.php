<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//
define ('DIR_FS_DBIO', DIR_FS_CATALOG . 'dbio/');

define ('DIR_FS_DBIO_CLASSES', DIR_FS_CATALOG . DIR_WS_CLASSES . 'dbio/');

// -----
// The name of the common dbIO messages file, present in /includes/languages/{current_language}/dbio
//
define ('FILENAME_DBIO_MESSAGES', 'dbio_messages.php');

// -----
// Database tables.
//
define ('TABLE_DBIO_STATS', DB_PREFIX . 'dbio_stats');