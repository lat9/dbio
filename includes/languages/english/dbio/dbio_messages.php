<?php
// -----
// Part of the DataBase Import/Export (aka dbIO) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2016, Vinos de Frutas Tropicales.
//

// ----
// These definitions are used by this sequencing class as well as the report-specific handlers.
//
define ('DBIO_INVALID_CHAR_REPLACEMENT', 167); //-Use the "section symbol" (??) as the invalid-character replacement

// -----
// Messages used by the DbIo class.
//
define ('DBIO_FORMAT_TEXT_NO_DESCRIPTION', 'The selected dbIO handler has no description for the language code "%s".');
define ('DBIO_MESSAGE_NO_HANDLERS_FOUND', 'No dbIO handlers were found; no report-generation is possible.');
define ('DBIO_FORMAT_MESSAGE_NO_HANDLER', 'Missing dbIO handler class file %s.');
define ('DBIO_FORMAT_MESSAGE_NO_CLASS', 'Missing dbIO handler class named "%1$s" in handler file %2$s.');
define ('DBIO_MESSAGE_EXPORT_NOT_INITIALIZED', 'Export aborted: No handler previously specified.');
define ('DBIO_MESSAGE_IMPORT_NOT_INITIALIZED', 'Import aborted: No handler previously specified.');
define ('DBIO_FORMAT_MESSAGE_EXPORT_NO_FP', 'Export aborted. Failure creating output file %s.');
define ('DBIO_EXPORT_NOTHING_TO_DO', 'dbIO Export: No matching fields were found.');
define ('DBIO_FORMAT_MESSAGE_IMPORT_FILE_MISSING', 'Import aborted:  Missing input file (%s).');
define ('DBIO_WARNING_ENCODING_ERROR', 'dbIO Import: Could not encode the input for ' . CHARSET . '.');
define ('DBIO_ERROR_NO_HANDLER', 'dbIO Export. No dbIO helper is configured.');
define ('DBIO_ERROR_EXPORT_NO_LANGUAGE', 'dbIO Export.  The language code "%s" is not configured for the store.');

// -----
// Messages used by the DbIoHandler class
//
define ('DBIO_MESSAGE_IMPORT_MISSING_HEADER', 'Import aborted: Missing header information for input file.');
define ('DBIO_FORMAT_MESSAGE_IMPORT_MISSING_KEY', 'Import aborted: Missing key column (%s).');