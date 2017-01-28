<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2017, Vinos de Frutas Tropicales.
//
define ('HEADING_TITLE', 'Manage DbIo Templates');
define ('INSTRUCTIONS', 'Here are the instructions for this tool.');

define ('TEXT_SCOPE_PUBLIC', 'Public');
define ('TEXT_SCOPE_PRIVATE', 'Private');
define ('TEXT_SYSTEM_UPDATE', 'System');
define ('TEXT_UNKNOWN_ADMIN', 'Unknown');

define ('TEXT_CHOOSE_HANDLER', 'Choose the handler to customize:');

define ('HEADING_CUSTOMIZING_FOR_HANDLER', 'Customizing a template for the <em>%s</em> handler.');

define ('HEADING_SCOPE', 'Scope');
define ('HEADING_TEMPLATE_NAME', 'Template Name');
define ('HEADING_DESCRIPTION', 'Description');
define ('HEADING_UPDATED_BY', 'Last Updated By');
define ('HEADING_LAST_UPDATE', 'Last Updated Date');
define ('HEADING_ACTION', 'Action');

define ('COLUMN_HEADING_SCOPE', 'Template Scope:');
define ('INSTRUCTIONS_SCOPE', 'A DbIo template can be either <em>Private</em> for your use only or <em>Public</em> and available for all authorized admin users.');
define ('COLUMN_HEADING_NAME', 'Template Name:');
define ('INSTRUCTIONS_NAME', 'When you choose perform a template-based <b>export</b>, the template\'s <em>name</em> forms a part of the export\'s filename. Use only alphanumeric characters and underscores (_) for the name.');
define ('COLUMN_HEADING_DESCRIPTION', 'Template Description:');
define ('INSTRUCTIONS_DESCRIPTION', 'Use the template\'s description (no HTML allowed!) to remind yourself of the template\'s purpose.  You can customize the description for each language supported by your store.');
define ('COLUMN_HEADING_CHOOSE_FIELDS', 'Choose Template Fields:');
define ('INSTRUCTIONS_CHOOSE', 'Instructions for choosing ...');


define ('DBIO_ACTION_PLEASE_CHOOSE', 'Please Choose');
define ('DBIO_ACTION_EDIT', 'Edit');
define ('DBIO_ACTION_INSERT', 'Insert');
define ('DBIO_ACTION_COPY', 'Copy');
define ('DBIO_ACTION_REMOVE', 'Remove');

define ('NO_TEMPLATES_EXIST', 'There are currently no templates defined.  Use the &quot;Insert&quot; button to add one!');
define ('BUTTON_NEW', 'New Template');
define ('BUTTON_NEW_TITLE', 'Click here to create a new DbIo template for the current handler');
define ('BUTTON_EDIT', 'Edit');
define ('BUTTON_EDIT_TITLE', 'Click here to edit this DbIo template');
define ('BUTTON_INSERT', 'Insert');
define ('BUTTON_INSERT_TITLE', 'Click here to create a new DbIo template');
define ('BUTTON_UPDATE', 'Update');
define ('BUTTON_UPDATE_TITLE', 'Click here to update this DbIo template');

define ('ERROR_UNKNOWN_HANDLER', 'An unknown handler name was specified, please try again.');
define ('ERROR_TEMPLATE_NAME_EXISTS', 'A &quot;%3$s&quot; template named \'%2$s\' already exists for the <em>%1$s</em> handler.  Please choose a different name.');
define ('ERROR_TEMPLATE_NAME_INVALID_CHARS', 'The template name you entered contains invalid characters.  Please re-enter the name, using <b>only</b> alphanumeric characters and underscores.');
define ('ERROR_TEMPLATE_NAME_TOO_LONG', 'The template name you entered has too many characters.  Please re-enter the name using %u or fewer characters.');
define ('ERROR_TEMPLATE_NO_FIELDS', 'Please choose at least one customized field for this DbIo template.');
define ('SUCCESS_TEMPLATE_ADDED', 'The DbIo template named <em>%s</em> was successfully added.');

define ('JS_MESSAGE_NAME_CANT_BE_EMPTY', 'The Template Name field cannot be empty.');
define ('JS_MESSAGE_NAME_TOO_LONG', 'The Template Name field must not be longer than %u characters.');
define ('JS_MESSAGE_AT_LEAST_ONE_FIELD', 'A template-customization must include at least one field.');
define ('JS_MESSAGE_ERRORS_EXIST', 'You need to make some changes to the form\\\'s input:');
define ('JS_MESSAGE_TRY_AGAIN', 'Please make those corrections and try again.');