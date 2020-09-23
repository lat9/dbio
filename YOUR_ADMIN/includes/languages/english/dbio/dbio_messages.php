<?php
// -----
// Part of the DataBase I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2020, Vinos de Frutas Tropicales.
//

// ----
// These definitions are used by this sequencing class as well as the report-specific handlers.
//
define('DBIO_INVALID_CHAR_REPLACEMENT', 167); //-Use the "section symbol" (??) as the invalid-character replacement

// -----
// Messages used by the DbIo class.
//
define('DBIO_FORMAT_TEXT_NO_DESCRIPTION', 'This DbIo handler has not provided its description.');
define('DBIO_MESSAGE_NO_HANDLERS_FOUND', 'No DbIo handlers were found; no report-generation is possible.');
define('DBIO_FORMAT_MESSAGE_NO_HANDLER', 'Missing DbIo handler class file %s.');
define('DBIO_FORMAT_MESSAGE_NO_CLASS', 'Missing DbIo handler class named "%1$s" in handler file %2$s.');
define('DBIO_MESSAGE_EXPORT_NOT_INITIALIZED', 'Export aborted: No handler previously specified.');
define('DBIO_MESSAGE_IMPORT_NOT_INITIALIZED', 'Import aborted: No handler previously specified.');
define('DBIO_FORMAT_MESSAGE_EXPORT_NO_FP', 'Export aborted. Failure creating output file %s.');
define('DBIO_EXPORT_NOTHING_TO_DO', 'DbIo Export: No records matched the requested criteria.');
define('DBIO_FORMAT_MESSAGE_IMPORT_FILE_MISSING', 'Import aborted:  Missing input file (%s).');
define('DBIO_WARNING_ENCODING_ERROR', 'DbIo Import: Could not encode the input for ' . CHARSET . '.');
define('DBIO_ERROR_NO_HANDLER', 'DbIo Export. No DbIo handlers are configured.');
define('DBIO_ERROR_EXPORT_NO_LANGUAGE', 'DbIo Export.  The language code "%s" is not configured for the store.');
define('DBIO_ERROR_NO_PHP_MBSTRING', "The DbIo requires the &quot;php-mbstring&quot; extension to be loaded; contact your webhost and ask them to install that extension.");
define('DBIO_ERROR_MISSING_DIRECTORY', "The directory (%s) was not found; no DbIo operations are possible until this is corrected.");
define('DBIO_ERROR_DIRECTORY_NOT_WRITABLE', "The directory (%s) is not writable; no DbIo operations are possible until this is corrected.");

// -----
// Messages used by the DbIoHandler class
//
define('DBIO_MESSAGE_IMPORT_MISSING_HEADER', 'Import aborted: Missing header information for input file.');
define('DBIO_FORMAT_MESSAGE_IMPORT_MISSING_KEY', 'Import aborted: Missing key column (%s).');
define('DBIO_TEXT_ERROR', 'Error: ');  //-Used to prefix processing messages with errors
define('DBIO_MESSAGE_KEY_CONFIGURATION_ERROR', 'There is an error in the key-configuration for the selected handler; the handler cannot be used.');
define('DBIO_ERROR_HANDLER_MISSING_FUNCTION', 'The current handler (%1$s) is missing the (required) "%2$s" function; the import is disallowed.');
define('DBIO_ERROR_HEADER_MISSING_KEYS', 'The current import-file is missing these (%s) required columns; the import is disallowed.');
define('DBIO_ERROR_HANDLER_NO_COMMANDS', 'The current import-file uses DbIo commands, but the handler doesn\'t support them; the import is disallowed.');
define('DBIO_ERROR_HANDLER_VERSION_MISMATCH', 'There is a version-mismatch for the selected handler (%1$s); the handler cannot be used.');
define('DBIO_ERROR_MULTIPLE_COMMAND_COLUMNS', 'Import cancelled: Multiple v_dbio_command columns found in input.');
