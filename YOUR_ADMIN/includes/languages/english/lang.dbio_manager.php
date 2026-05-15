<?php

declare(strict_types=1);

// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2025, Vinos de Frutas Tropicales.
//
// Last updated: DbIo v2.0.2
//
$define = [
    'HEADING_TITLE' => 'Database I/O (DbIo) Manager',

    'TEXT_ALL_ORDERS_STATUS_VALUES' => 'All Values',

    'TEXT_IS_EXPORT_ONLY' => "The '%s' DbIo handler does not support an import action.",

    'TEXT_SCOPE_PUBLIC' => 'Public',
    'TEXT_SCOPE_PRIVATE' => 'Private',

    'TEXT_FORMAT_CONFIG_INFO' => 'This section shows the current settings that affect the <em>DbIo Manager</em>\'s operation.  The <em>DbIo Settings</em> values can be changed by clicking <a href="%s">here</a>.',
    'TEXT_DBIO_SETTINGS' => 'DbIo Settings',
    'TEXT_CSV_DELIMITER' => 'CSV: Delimiter',
    'TEXT_CSV_ENCLOSURE' => 'CSV: Enclosure',
    'TEXT_CSV_ESCAPE' => 'CSV: Escape',
    'TEXT_CSV_ENCODING' => 'CSV: Encoding',
    'TEXT_CSV_DATE_FORMAT' => 'CSV: Import Date Format',
    'TEXT_FILE_DEFAULT_SORT_ORDER' => 'Default File Sort Order',
    'TEXT_ALLOW_DUPLICATE_MODELS' => 'Products: Allow Duplicate Models',
    'TEXT_AUTO_CREATE_CATEGORIES' => 'Products: Automatically Create Categories',
    'TEXT_INSERT_REQUIRES_COMMAND' => 'Products: Product Creation Requires Command',
    'TEXT_MAX_EXECUTION' => 'Maximum Execution Time',
    'TEXT_SPLIT_RECORD_COUNT' => 'Split Record Count',
    'TEXT_DEBUG_ENABLED' => 'Debug Enabled',
    'TEXT_DATE_FORMAT' => 'Display/Log Date Format',
    'TEXT_DBIO_SYSTEM_SETTINGS' => 'System Settings',
    'TEXT_MAX_UPLOAD_FILE_SIZE' => 'Maximum Upload File Size',
    'TEXT_CHARSET' => 'Internal Character Encoding',
    'TEXT_DB_CHARSET' => 'Database Character Encoding',
    'TEXT_DEFAULT_LANGUAGE' => 'Default Language',
    'TEXT_CHOOSE_HANDLER' => 'Choose the handler to use:',

    'LEGEND_EXPORT' => 'Export',
    'LEGEND_CONFIGURATION' => 'Configuration',
    'LEGEND_FILE_ACTIONS' => 'File Actions',
    'LEGEND_FILE_UPLOADS' => 'File Uploads',

    'TEXT_INSTRUCTIONS' => 'The <b><i>DbIo Manager</i></b> provides <em>handlers</em> that enable exports and, conditionally, imports of database information, using a comma-separated-value (CSV) file.  Choose the handler to use from the drop-down list below and that handler\'s features, e.g. filters and/or template-customization options, will be displayed.<br><br>For additional information, refer to the plugin\'s <a href="https://github.com/lat9/dbio/wiki" target="_blank" rel="noreferrer noopener">Wiki articles</a>.',

    'DBIO_BUTTON_DELETE' => 'Delete',
    'DBIO_BUTTON_DELETE_TITLE' => 'Click here to delete the currently-selected file(s) from the server.',
    'DBIO_BUTTON_GO' => 'Go',
    'DBIO_BUTTON_GO_TITLE' => 'Click here to perform the chosen action for the file chosen below.',
    'TEXT_AUTO_DOWNLOAD' => 'Download export immediately after generation',
    'BUTTON_EXPORT' => 'Export',
    'BUTTON_EXPORT_TITLE' => 'Click here to export the information associated with the selected DbIo report.',
    'BUTTON_UPLOAD' => 'Upload',
    'BUTTON_UPLOAD_TITLE' => 'Click here to upload the selected file.',

    'TEXT_FILE_ACTION_DELETE_INSTRUCTIONS' => 'You can remove one or more of the files below from the server. Select the file(s) to be deleted, then click the &quot;Delete&quot; button.',

    'TEXT_SHOW_HIDE_FILTERS' => 'Click to show (or hide) the filters for <strong>all</strong> handlers.  When the filters are <em>hidden</em>, then they do not apply to the current export.',
    'TEXT_BUTTON_MANAGE_CUSTOMIZATION' => 'Manage Templates',
    'LABEL_CHOOSE_CUSTOMIZATION' => 'Choose Template:',
    'TEXT_ALL_FIELDS' => 'All Fields',
    'TEXT_ALL_FIELDS_DESCRIPTION' => 'The current export will include all fields supported by the current handler.',

    'DBIO_FORM_SUBMISSION_ERROR' => 'There were some missing values for your form\'s submission, please try again.',

    'TEXT_NO_DBIO_FILES_AVAILABLE' => 'No import/export files are available for the <em>%s</em> handler.',
    'ERROR_FILENAME_MISMATCH' => 'Please choose an upload file that is associated with the current handler (%1$s), e.g. <em>dbio.%1$s.*.csv</em>.',
    'TEXT_UPLOAD_FOR_IMPORT_ONLY' => 'The <em>%s</em> handler does not support file-imports, so file uploads have been disabled.',
    'TEXT_CHOOSE_ACTION' => 'Choose the action to be performed for the file chosen below:',
    'TEXT_FILE_UPLOAD_INSTRUCTIONS' => 'You can also upload a file from your computer (extensions %2$s <b>only</b>) for import using the <em>DbIo Manager</em>.  Choose a file that is processable by the current handler (%1$s), e.g. <em>dbio.%1$s.*.csv</em>, then click the &quot;Upload&quot; button.',
    'TEXT_CHOOSE_FILE' => 'Your file:',

    'DBIO_ACTION_PLEASE_SELECT' => 'Please select',
    'DBIO_ACTION_SPLIT' => 'Split',
    'DBIO_ACTION_DELETE' => 'Delete',
    'DBIO_ACTION_FULL_IMPORT' => 'Import (Full)',
    'DBIO_ACTION_CHECK_IMPORT' => 'Import (Check-only)',
    'DBIO_ACTION_DOWNLOAD' => 'Download',

    'TEXT_FILE_ACTION_INSTRUCTIONS' =>
    'The following file-related actions are supported, but might be limited by the current <em>handler</em>:' . PHP_EOL .
    '<ol>' . PHP_EOL .
    '   <li><strong>%%DBIO_ACTION_SPLIT%%</strong>: Splits a .CSV file into multiple files, using your store\'s current setting for the <b>Split Record Count</b>, enabling you to download large exports in sections.</li>' . PHP_EOL .
    '   <li><strong>%%DBIO_ACTION_DOWNLOAD%%</strong>: Downloads the selected file (either .csv or .log) to your computer for your review.</li>' . PHP_EOL .
    '   <li><strong>%%DBIO_ACTION_FULL_IMPORT%%</strong>: This action, enabled only if the selected handler supports imports, uses the selected .csv file to make changes to your database.</li>' . PHP_EOL .
    '   <li><strong>%%DBIO_ACTION_CHECK_IMPORT%%</strong>: This action, enabled only if the selected handler supports imports, gives you the opportunity to verify the database actions that will occur when you perform a &quot;full&quot; import; no database changes occur.  Upon completion, a log-file is generated that contains the <em>DbIo</em>\'s analysis.</li>' . PHP_EOL .
    '</ol>' . PHP_EOL .
    'Choose an <em>action</em> and an associated file and then click the &quot;Go&quot; button.' . PHP_EOL,

    'HEADING_CHOOSE_FILE' => 'Choose File',
    'HEADING_FILENAME' => 'File Name',
    'HEADING_BYTES' => 'Bytes',
    'HEADING_LAST_MODIFIED' => 'Last-Modified Date',
    'HEADING_DELETE' => 'Delete?',

    'TEXT_SORT_NAME_ASC' => 'Click here to sort by file-name, ascending',
    'TEXT_SORT_NAME_DESC' => 'Click here to sort by file-name, descending',
    'TEXT_SORT_SIZE_ASC' => 'Click here to sort by file-size, ascending',
    'TEXT_SORT_SIZE_DESC' => 'Click here to sort by file-size, descending',
    'TEXT_SORT_DATE_ASC' => 'Click here to sort by file-date, ascending',
    'TEXT_SORT_DATE_DESC' => 'Click here to sort by file-date, descending',

    'TEXT_VIEW_STATS' => 'View Import Details',
    'TEXT_IMPORT_LAST_STATS' => 'Click here to view the details about the last DbIo import',

    'ERROR_CHOOSE_FILE_ACTION' => 'Please choose the action to be performed on the file named &quot;%s&quot;.',

    'SUCCESSFUL_FILE_IMPORT' => 'The DbIo import from file &quot;%1$s&quot; was successfully completed.  %2$u records were processed.',
    'CAUTION_FILE_IMPORT' => 'The DbIo import from file &quot;%1$s&quot; was completed with %2$u errors and %3$u warnings. %4$u records were inserted or updated.',

    'ERROR_CANT_DELETE_FILE' => 'The requested file (%s) was not deleted; it was not found or its permissions are not set properly.',
    'SUCCESS_FILE_DELETED' => 'The requested file (%s) was successfully deleted.',

    'ERROR_CANT_SPLIT_FILE_OPEN_ERROR' => 'The requested file (%s) was not split; it could not be opened.',
    'ERROR_CREATING_SPLIT_FILE' => 'An error occurred during the split operation.  The file (%s) could not be created.',
    'ERROR_WRITING_SPLIT_FILE' => 'An error occurred writing record #%2$u of the split file (%1$s).',
    'ERROR_SPLIT_INPUT_NOT_AT_EOF' => 'An unknown error occurred reading the split input file (%s).  The operation was cancelled.',
    'WARNING_FILE_TOO_SMALL_TO_SPLIT' => 'The file (%1$s) contains too few records (%2$u) to split.',
    'FILE_SUCCESSFULLY_SPLIT' => 'The file (%1$s) was successfully split into %2$u chunks.',

    'ERROR_FILE_IS_EXPORT_ONLY' => 'The file (%s) was not imported.  It is associated with an <em>export-only</em> report.',
    'ERROR_UNKNOWN_TEMPLATE' => 'The DbIo template you requested could not be found; please try again.',
    'DBIO_MGR_EXPORT_SUCCESSFUL' => 'Your <em>%1$s</em> export was successfully completed into %2$s, creating %3$u records.',

    'ERROR_NO_FILE_TO_UPLOAD' => 'No file was selected for the upload.  Please try again.',
    'FILE_UPLOADED_SUCCESSFULLY' => 'The file <em>%s</em> was successfully uploaded.',

    'DBIO_CANT_OPEN_FILE' => "Download unsuccessful, the file '%s' does not exist.",

    //-The count of files selected is inserted between these two messages
    'JS_MESSAGE_OK2DELETE_PART1' => 'Are you sure you want to permanently remove the ',
    'JS_MESSAGE_OK2DELETE_PART2' => ' selected file(s) from the server?',
    'JS_MESSAGE_NO_FILES_SELECTED' => 'No files were selected to delete; please try again.',
    'JS_MESSAGE_CHOOSE_ACTION' => 'Please choose an action to perform on the selected file.',

    'LAST_STATS_LEAD_IN' => 'Statistics for the last file imported in the current admin session:',
    'LAST_STATS_FILE_NAME' => 'Import File Name:',
    'LAST_STATS_OPERATION' => 'Operation:',
    'LAST_STATS_RECORDS_READ' => 'Records Read:',
    'LAST_STATS_RECORDS_INSERTED' => 'Records Inserted:',
    'LAST_STATS_RECORDS_UPDATED' => 'Records Updated:',
    'LAST_STATS_WARNINGS' => 'Warnings:',
    'LAST_STATS_ERRORS' => 'Errors:',
    'LAST_STATS_PARSE_TIME' => 'Parse Time:',
    'LAST_STATS_MESSAGES_EXIST' => 'The following warnings/errors were generated by the above action:',

    'DBIO_SELECT_ALL' => 'Select All',
    'DBIO_SELECT_ALL_TITLE' => 'Click here to select all',
    'DBIO_UNSELECT_ALL' => 'Unselect All',
    'DBIO_UNSELECT_ALL_TITLE' => 'Click here to unselect all',
];

return $define;
