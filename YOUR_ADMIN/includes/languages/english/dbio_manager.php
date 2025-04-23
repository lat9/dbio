<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2025, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.0.2
//
define('HEADING_TITLE', 'Database I/O (DbIo) Manager');

define('TEXT_ALL_ORDERS_STATUS_VALUES', 'All Values');

define('TEXT_IS_EXPORT_ONLY', "The '%s' DbIo handler does not support an import action.");

define('TEXT_SCOPE_PUBLIC', 'Public');
define('TEXT_SCOPE_PRIVATE', 'Private');

define('TEXT_FORMAT_CONFIG_INFO', 'This section shows the current settings that affect the <em>DbIo Manager</em>\'s operation.  The <em>DbIo Settings</em> values can be changed by clicking <a href="%s">here</a>.');
define('TEXT_DBIO_SETTINGS', 'DbIo Settings');
define('TEXT_CSV_DELIMITER', 'CSV: Delimiter');
define('TEXT_CSV_ENCLOSURE', 'CSV: Enclosure');
define('TEXT_CSV_ESCAPE', 'CSV: Escape');
define('TEXT_CSV_ENCODING', 'CSV: Encoding');
define('TEXT_CSV_DATE_FORMAT', 'CSV: Import Date Format');
define('TEXT_FILE_DEFAULT_SORT_ORDER', 'Default File Sort Order');
define('TEXT_ALLOW_DUPLICATE_MODELS', 'Products: Allow Duplicate Models');
define('TEXT_AUTO_CREATE_CATEGORIES', 'Products: Automatically Create Categories');
define('TEXT_INSERT_REQUIRES_COMMAND', 'Products: Product Creation Requires Command');
define('TEXT_MAX_EXECUTION', 'Maximum Execution Time');
define('TEXT_SPLIT_RECORD_COUNT', 'Split Record Count');
define('TEXT_DEBUG_ENABLED', 'Debug Enabled');
define('TEXT_DATE_FORMAT', 'Display/Log Date Format');
define('TEXT_DBIO_SYSTEM_SETTINGS', 'System Settings');
define('TEXT_MAX_UPLOAD_FILE_SIZE', 'Maximum Upload File Size');
define('TEXT_CHARSET', 'Internal Character Encoding');
define('TEXT_DB_CHARSET', 'Database Character Encoding');
define('TEXT_DEFAULT_LANGUAGE', 'Default Language');
define('TEXT_CHOOSE_HANDLER', 'Choose the handler to use:');

define('LEGEND_EXPORT', 'Export');
define('LEGEND_CONFIGURATION', 'Configuration');
define('LEGEND_FILE_ACTIONS', 'File Actions');
define('LEGEND_FILE_UPLOADS', 'File Uploads');

define('TEXT_INSTRUCTIONS', 'The <b><i>DbIo Manager</i></b> provides <em>handlers</em> that enable exports and, conditionally, imports of database information, using a comma-separated-value (CSV) file.  Choose the handler to use from the drop-down list below and that handler\'s features, e.g. filters and/or template-customization options, will be displayed.<br><br>For additional information, refer to the plugin\'s <a href="https://github.com/lat9/dbio/wiki" target="_blank" rel="noreferrer noopener">Wiki articles</a>.');

define('DBIO_BUTTON_DELETE', 'Delete');
define('DBIO_BUTTON_DELETE_TITLE', 'Click here to delete the currently-selected file(s) from the server.');
define('DBIO_BUTTON_GO', 'Go');
define('DBIO_BUTTON_GO_TITLE', 'Click here to perform the chosen action for the file chosen below.');
define('TEXT_AUTO_DOWNLOAD', 'Download export immediately after generation');
define('BUTTON_EXPORT', 'Export');
define('BUTTON_EXPORT_TITLE', 'Click here to export the information associated with the selected DbIo report.');
define('BUTTON_UPLOAD', 'Upload');
define('BUTTON_UPLOAD_TITLE', 'Click here to upload the selected file.');

define('TEXT_FILE_ACTION_DELETE_INSTRUCTIONS', 'You can remove one or more of the files below from the server. Select the file(s) to be deleted, then click the &quot;Delete&quot; button.');

define('TEXT_SHOW_HIDE_FILTERS', 'Click to show (or hide) the filters for <strong>all</strong> handlers.  When the filters are <em>hidden</em>, then they do not apply to the current export.');
define('TEXT_BUTTON_MANAGE_CUSTOMIZATION', 'Manage Templates');
define('LABEL_CHOOSE_CUSTOMIZATION', 'Choose Template:');
define('TEXT_ALL_FIELDS', 'All Fields');
define('TEXT_ALL_FIELDS_DESCRIPTION', 'The current export will include all fields supported by the current handler.');

define('DBIO_FORM_SUBMISSION_ERROR', 'There were some missing values for your form\'s submission, please try again.');

define('TEXT_NO_DBIO_FILES_AVAILABLE', 'No import/export files are available for the <em>%s</em> handler.');
define('ERROR_FILENAME_MISMATCH', 'Please choose an upload file that is associated with the current handler (%1$s), e.g. <em>dbio.%1$s.*.csv</em>.');
define('TEXT_UPLOAD_FOR_IMPORT_ONLY', 'The <em>%s</em> handler does not support file-imports, so file uploads have been disabled.');
define('TEXT_CHOOSE_ACTION', 'Choose the action to be performed for the file chosen below:');
define('TEXT_FILE_UPLOAD_INSTRUCTIONS', 'You can also upload a file from your computer (extensions %2$s <b>only</b>) for import using the <em>DbIo Manager</em>.  Choose a file that is processable by the current handler (%1$s), e.g. <em>dbio.%1$s.*.csv</em>, then click the &quot;Upload&quot; button.');
define('TEXT_CHOOSE_FILE', 'Your file:');

define('DBIO_ACTION_PLEASE_SELECT', 'Please select');
define('DBIO_ACTION_SPLIT', 'Split');
define('DBIO_ACTION_DELETE', 'Delete');
define('DBIO_ACTION_FULL_IMPORT', 'Import (Full)');
define('DBIO_ACTION_CHECK_IMPORT', 'Import (Check-only)');
define('DBIO_ACTION_DOWNLOAD', 'Download');

define('TEXT_FILE_ACTION_INSTRUCTIONS',
    'The following file-related actions are supported, but might be limited by the current <em>handler</em>:' . PHP_EOL .
    '<ol>' . PHP_EOL .
    '   <li><strong>' . DBIO_ACTION_SPLIT . '</strong>: Splits a .CSV file into multiple files, using your store\'s current setting for the <b>Split Record Count</b>, enabling you to download large exports in sections.</li>' . PHP_EOL .
    '   <li><strong>' . DBIO_ACTION_DOWNLOAD . '</strong>: Downloads the selected file (either .csv or .log) to your computer for your review.</li>' . PHP_EOL .
    '   <li><strong>' . DBIO_ACTION_FULL_IMPORT . '</strong>: This action, enabled only if the selected handler supports imports, uses the selected .csv file to make changes to your database.</li>' . PHP_EOL .
    '   <li><strong>' . DBIO_ACTION_CHECK_IMPORT . '</strong>: This action, enabled only if the selected handler supports imports, gives you the opportunity to verify the database actions that will occur when you perform a &quot;full&quot; import; no database changes occur.  Upon completion, a log-file is generated that contains the <em>DbIo</em>\'s analysis.</li>' . PHP_EOL .
    '</ol>' . PHP_EOL .
    'Choose an <em>action</em> and an associated file and then click the &quot;Go&quot; button.' . PHP_EOL
);

define('HEADING_CHOOSE_FILE', 'Choose File');
define('HEADING_FILENAME', 'File Name');
define('HEADING_BYTES', 'Bytes');
define('HEADING_LAST_MODIFIED', 'Last-Modified Date');
define('HEADING_DELETE', 'Delete?');

define('TEXT_SORT_NAME_ASC', 'Click here to sort by file-name, ascending');
define('TEXT_SORT_NAME_DESC', 'Click here to sort by file-name, descending');
define('TEXT_SORT_SIZE_ASC', 'Click here to sort by file-size, ascending');
define('TEXT_SORT_SIZE_DESC', 'Click here to sort by file-size, descending');
define('TEXT_SORT_DATE_ASC', 'Click here to sort by file-date, ascending');
define('TEXT_SORT_DATE_DESC', 'Click here to sort by file-date, descending');

define('TEXT_VIEW_STATS', 'View Import Details');
define('TEXT_IMPORT_LAST_STATS', 'Click here to view the details about the last DbIo import');

define('ERROR_CHOOSE_FILE_ACTION', 'Please choose the action to be performed on the file named &quot;%s&quot;.');

define('SUCCESSFUL_FILE_IMPORT', 'The DbIo import from file &quot;%1$s&quot; was successfully completed.  %2$u records were processed.');
define('CAUTION_FILE_IMPORT', 'The DbIo import from file &quot;%1$s&quot; was completed with %2$u errors and %3$u warnings. %4$u records were inserted or updated.');

define('ERROR_CANT_DELETE_FILE', 'The requested file (%s) was not deleted; it was not found or its permissions are not set properly.');
define('SUCCESS_FILE_DELETED', 'The requested file (%s) was successfully deleted.');

define('ERROR_CANT_SPLIT_FILE_OPEN_ERROR', 'The requested file (%s) was not split; it could not be opened.');
define('ERROR_CREATING_SPLIT_FILE', 'An error occurred during the split operation.  The file (%s) could not be created.');
define('ERROR_WRITING_SPLIT_FILE', 'An error occurred writing record #%2$u of the split file (%1$s).');
define('ERROR_SPLIT_INPUT_NOT_AT_EOF', 'An unknown error occurred reading the split input file (%s).  The operation was cancelled.');
define('WARNING_FILE_TOO_SMALL_TO_SPLIT', 'The file (%1$s) contains too few records (%2$u) to split.');
define('FILE_SUCCESSFULLY_SPLIT', 'The file (%1$s) was successfully split into %2$u chunks.');

define('ERROR_FILE_IS_EXPORT_ONLY', 'The file (%s) was not imported.  It is associated with an <em>export-only</em> report.');
define('ERROR_UNKNOWN_TEMPLATE', 'The DbIo template you requested could not be found; please try again.');
define('DBIO_MGR_EXPORT_SUCCESSFUL', 'Your <em>%1$s</em> export was successfully completed into %2$s, creating %3$u records.');

define('ERROR_NO_FILE_TO_UPLOAD', 'No file was selected for the upload.  Please try again.');
define('FILE_UPLOADED_SUCCESSFULLY', 'The file <em>%s</em> was successfully uploaded.');

define('DBIO_CANT_OPEN_FILE', "Download unsuccessful, the file '%s' does not exist.");

define('JS_MESSAGE_OK2DELETE_PART1', 'Are you sure you want to permanently remove the ');  //-The count of files selected is inserted between these two messages
define('JS_MESSAGE_OK2DELETE_PART2', ' selected file(s) from the server?');
define('JS_MESSAGE_NO_FILES_SELECTED', 'No files were selected to delete; please try again.');
define('JS_MESSAGE_CHOOSE_ACTION', 'Please choose an action to perform on the selected file.');

define('LAST_STATS_LEAD_IN', 'Statistics for the last file imported in the current admin session:');
define('LAST_STATS_FILE_NAME', 'Import File Name:');
define('LAST_STATS_OPERATION', 'Operation:');
define('LAST_STATS_RECORDS_READ', 'Records Read:');
define('LAST_STATS_RECORDS_INSERTED', 'Records Inserted:');
define('LAST_STATS_RECORDS_UPDATED', 'Records Updated:');
define('LAST_STATS_WARNINGS', 'Warnings:');
define('LAST_STATS_ERRORS', 'Errors:');
define('LAST_STATS_PARSE_TIME', 'Parse Time:');
define('LAST_STATS_MESSAGES_EXIST', 'The following warnings/errors were generated by the above action:');

define('DBIO_SELECT_ALL', 'Select All');
define('DBIO_SELECT_ALL_TITLE', 'Click here to select all');
define('DBIO_UNSELECT_ALL', 'Unselect All');
define('DBIO_UNSELECT_ALL_TITLE', 'Click here to unselect all');
