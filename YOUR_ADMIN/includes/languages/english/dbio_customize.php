<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
//
// Last updated: DbIo v2.2.0
//
// Copyright (c) 2017-2023, Vinos de Frutas Tropicales.
//
define('HEADING_TITLE', 'Configure DbIo%s Templates');

define('TEXT_SCOPE_PUBLIC', 'Public');
define('TEXT_SCOPE_PRIVATE', 'Private');
define('TEXT_SYSTEM_UPDATE', 'System');
define('TEXT_UNKNOWN_ADMIN', 'Unknown');

define('HEADING_TITLE_EDIT', 'You are editing a template for the <em>%s</em> handler.');
define('HEADING_TITLE_NEW', 'You are creating a new template for the <em>%s</em> handler.');
define('HEADING_TITLE_COPY', 'You are copying a template defined for the <em>%s</em> handler.');

define('HEADING_SCOPE', 'Scope');
define('HEADING_TEMPLATE_NAME', 'Template Name');
define('HEADING_DESCRIPTION', 'Description');
define('HEADING_UPDATED_BY', 'Last Updated By');
define('HEADING_LAST_UPDATE', 'Last Updated Date');
define('HEADING_ACTION', 'Action');

define('COLUMN_HEADING_SCOPE', 'Template Scope:');
define('INSTRUCTIONS_SCOPE', 'A DbIo template can be either <em>Private</em> for your use only or <em>Public</em> and available for all authorized admin users.');
define('COLUMN_HEADING_NAME', 'Template Name:');
define('INSTRUCTIONS_NAME', 'When you perform a template-based <b>export</b>, the template\'s <em>name</em> forms a part of the export\'s filename. Use only alphanumeric characters and underscores (_) for the name.  The name that you choose must be unique within the selected <em>scope</em> of your available templates.');
define('COLUMN_HEADING_DESCRIPTION', 'Template Description:');
define('INSTRUCTIONS_DESCRIPTION', 'Use the template\'s description (no HTML allowed!) to remind yourself of the template\'s purpose.  You can customize the description for each language supported by your store.');
define('COLUMN_HEADING_CHOOSE_FIELDS', 'Choose Template Fields:');
define('COLUMN_HEADING_COPY_FIELDS', 'Template Fields:');
define('INSTRUCTIONS_CHOOSE', 'You can move fields from those <em>available</em> (on the left) to those chosen (on the right) for this template.  Multiple fields can be selected at once by holding down the &quot;Ctrl&quot; key while you click additional fields with your mouse.<br><br>Once you have chosen the fields for this template, use the buttons to move the fields up or down within your customized list.  When the template is used for an <b>export</b> operation, the resulting .CSV file contains columns in the order that you specified.');
define('INSTRUCTIONS_CHOOSE_COPY', 'When you <em>copy</em> a template, the copied template initially contains the fields previously configured.  Once the template is copied, you can edit that copy to adjust the fields.  If you can\'t view all the fields, use your mouse and drag the drop-down list down until all are visible.');

define('NO_TEMPLATES_EXIST', 'There are currently no templates defined.  Use the &quot;New Template&quot; button to add one!');
define('TEXT_ENTER_REPORT_DESCRIPTION_HERE', 'Enter the report\'s description here.');

define('BUTTON_EDIT', 'Edit');
define('BUTTON_EDIT_TITLE', 'Click here to edit this template');
define('BUTTON_COPY', 'Copy');
define('BUTTON_COPY_TITLE', 'Click here to copy this template');
define('BUTTON_REMOVE', 'Remove');
define('BUTTON_REMOVE_TITLE', 'Click here to permanently remove this template');
define('BUTTON_NEW', 'New Template');
define('BUTTON_NEW_TITLE', 'Click here to create a new DbIo template for the current handler');

define('BUTTON_INSERT', 'Insert');
define('BUTTON_INSERT_TITLE', 'Click here to create a new DbIo template');
define('BUTTON_UPDATE', 'Update');
define('BUTTON_UPDATE_TITLE', 'Click here to update this DbIo template');
define('BUTTON_RETURN', 'DbIo Manager');
define('BUTTON_RETURN_TITLE', 'Click here to return to the DbIo Manager\'s main page');
define('BUTTON_CANCEL', 'Cancel');
define('BUTTON_CANCEL_TITLE', 'Click here to cancel the current action');

define('INSTRUCTIONS_MAIN',
    'Use this page to customize an export template for the DbIo\'s <em>%1$s</em> handler.  You can choose a subset of the fields supported by this handler and also customize the order in which those fields are exported into their associated .csv-file columns.' .
    '<br><br>' .
    'A template\'s <em>scope</em> can be either <b>' . TEXT_SCOPE_PUBLIC . '</b>, available for all admin users, or <b>' . TEXT_SCOPE_PRIVATE . '</b>, available only for your use.  Its <em>name</em> will form a portion of an exported csv-file\'s name if the template is used to customize an export action, e.g. <code>dbio.%1$s.template_name.datetime_string</code>.  The <em>description</em> that you provide is displayed on the main <strong>Database I/O Manager</strong> screen when the template is selected, giving you some confirmation of the template\'s features.'
);

define('ERROR_UNKNOWN_HANDLER', 'An unknown handler name was specified, please try again.');
define('ERROR_TEMPLATE_NAME_EXISTS', 'A &quot;%3$s&quot; template named \'%2$s\' already exists for the <em>%1$s</em> handler.  Please choose a different name.');
define('ERROR_TEMPLATE_NAME_INVALID_CHARS', 'The template name you entered contains invalid characters.  Please re-enter the name, using <b>only</b> alphanumeric characters and underscores.');
define('ERROR_TEMPLATE_NAME_TOO_LONG', 'The template name you entered has too many characters.  Please re-enter the name using %u or fewer characters.');
define('ERROR_TEMPLATE_NO_FIELDS', 'Please choose at least one customized field for this DbIo template.');
define('SUCCESS_TEMPLATE_ADDED', 'The DbIo template named <em>%s</em> was successfully added.');
define('SUCCESS_TEMPLATE_UPDATED', 'The DbIo template named <em>%s</em> was successfully updated.');
define('SUCCESS_TEMPLATE_REMOVED', 'The DbIo template named <em>%s</em> was successfully removed.');

define('JS_MESSAGE_CONFIRM_REMOVE', 'Are you sure you want to permanently remove this template?');
define('JS_MESSAGE_NAME_CANT_BE_EMPTY', 'The Template Name field cannot be empty.');
define('JS_MESSAGE_NAME_TOO_LONG', 'The Template Name field must not be longer than %u characters.');
define('JS_MESSAGE_AT_LEAST_ONE_FIELD', 'A template-customization must include at least one field.');
define('JS_MESSAGE_ERRORS_EXIST', 'You need to make some changes to the form\\\'s input:');
define('JS_MESSAGE_TRY_AGAIN', 'Please make those corrections and try again.');
