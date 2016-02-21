<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//
define ('HEADING_TITLE', 'Database I/O (dbIO) Manager');

define ('TEXT_IS_EXPORT_ONLY', "The '%s' dbIO handler does not support an import action.");

define ('BUTTON_EXPORT', 'Export');
define ('BUTTON_EXPORT_TITLE', 'Click here to export database information, based on the selected report type.');
define ('BUTTON_SPLIT', 'Split');
define ('BUTTON_SPLIT_TITLE', 'Click here to split the currently-selected file into multiples of %u records.');
define ('DBIO_BUTTON_DELETE', 'Delete');
define ('DBIO_BUTTON_DELETE_TITLE', 'Click here to delete the currently-selected file from the server.');
define ('DBIO_BUTTON_GO', 'Go');
define ('DBIO_BUTTON_GO_TITLE', 'Click here to perform the chosen action for the file selected below.');
define ('BUTTON_IMPORT', 'Import');
define ('BUTTON_IMPORT_TITLE', 'Click here to import the currently-selected file, based on the criteria specified above.');
define ('DBIO_BUTTON_DOWNLOAD', 'Download');
define ('DBIO_BUTTON_DOWNLOAD_TITLE', 'Click here to download the contents of the currently-selected file.');
define ('BUTTON_EXPORT', 'Export');
define ('BUTTON_EXPORT_TITLE', 'Click here to export the information associated with the selected dbIO report.');

define ('TEXT_FILE_ACTION_INSTRUCTIONS', 'You can perform the following actions on the selected file.');

define ('DBIO_FORMAT_CONFIGURATION_ERROR', 'The <em>dbIO Manager</em> does not support your installation, due to a mismatch between your DB_CHARSET (%1$s) and CHARSET (%2$s) values.');
define ('DBIO_FORM_SUBMISSION_ERROR', 'There were some missing values for your form\'s submission, please try again.');

define ('ERROR_CHOOSE_FILE_ACTION', 'Please choose the action to be performed on the file named &quot;%s&quot;.');

define ('ERROR_CANT_DELETE_FILE', 'The requested file (%s) was not deleted; it was not found or its permissions are not set properly.');
define ('SUCCESS_FILE_DELETED', 'The requested file (%s) was successfully deleted.');

define ('ERROR_CANT_SPLIT_FILE_OPEN_ERROR', 'The requested file (%s) was not split; it could not be opened.');
define ('ERROR_CREATING_SPLIT_FILE', 'An error occurred during the split operation.  The file (%s) could not be created.');
define ('ERROR_WRITING_SPLIT_FILE', 'An error occurred writing record #%$2u of the split file (%$1s).');
define ('ERROR_SPLIT_INPUT_NOT_AT_EOF', 'An unknown error occurred reading the split input file (%s).  The operation was cancelled.');
define ('WARNING_FILE_TOO_SMALL_TO_SPLIT', 'The file (%1$s) contains too few records (%2$u) to split.');
define ('FILE_SUCCESSFULLY_SPLIT', 'The file (%1$s) was successfully split into %2$u chunks.'); 

define ('ERROR_FILE_IS_EXPORT_ONLY', 'The file (%s) was not imported.  It is associated with an <em>export-only</em> report.');
define ('DBIO_MGR_EXPORT_SUCCESSFUL', 'Your <em>%1$s</em> export was successfully completed into %2$s.');

define ('JS_MESSAGE_OK2DELETE', 'Are you sure you want to permanently remove the selected file from the server?');
define ('JS_MESSAGE_CHOOSE_ACTION', 'Please choose an action to perform on the selected file.');
