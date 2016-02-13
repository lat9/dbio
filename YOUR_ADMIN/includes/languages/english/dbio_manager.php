<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//
define ('HEADING_TITLE', 'Database I/O (dbIO) Manager');

define ('BUTTON_CHANGE_CONFIG', 'Change');
define ('BUTTON_CHANGE_CONFIG_TITLE', 'Click here to change the &quot;dbIO Manager&quot; configuration settings');
define ('BUTTON_SPLIT', 'Split');
define ('BUTTON_SPLIT_TITLE', 'Click here to split the selected .CSV file into multiples of %u records.');
define ('BUTTON_IMPORT', 'Import');
define ('BUTTON_IMPORT_TITLE', 'Click here to import the selected .CSV file, based on the criteria specified above.');
define ('BUTTON_EXPORT', 'Export');
define ('BUTTON_EXPORT_TITLE', 'Click here to export the information associated with the selected dbIO report.');

//define ('DBIO_CURRENT_CONFIGURATION', 'The <em>dbIO Manager</em> configuration currently uses these characters (' . DBIO_DELIMITER . ', ' . DBIO_ENCLOSURE . ', '), 

define ('DBIO_FORMAT_CONFIGURATION_ERROR', 'The <em>dbIO Manager</em> does not support your installation, due to a mismatch between your DB_CHARSET (%1$s) and CHARSET (%2$s) values.');
define ('DBIO_FORM_SUBMISSION_ERROR', 'There were some missing values for your form\'s submission, please try again.');

define ('DBIO_MGR_EXPORT_SUCCESSFUL', 'Your <em>%1$s</em> export was successfully completed into %2$s.');
