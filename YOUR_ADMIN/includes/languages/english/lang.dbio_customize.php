<?php

declare(strict_types=1);

// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
//
// Last updated: DbIo v2.0.2
//
// Copyright (c) 2017-2025, Vinos de Frutas Tropicales.
//
$define = [
    'HEADING_TITLE' => 'Configure DbIo%s Templates',

    'TEXT_SCOPE_PUBLIC' => 'Public',
    'TEXT_SCOPE_PRIVATE' => 'Private',
    'TEXT_SYSTEM_UPDATE' => 'System',
    'TEXT_UNKNOWN_ADMIN' => 'Unknown',

    'HEADING_TITLE_EDIT' => 'You are editing a template for the <em>%s</em> handler.',
    'HEADING_TITLE_NEW' => 'You are creating a new template for the <em>%s</em> handler.',
    'HEADING_TITLE_COPY' => 'You are copying a template defined for the <em>%s</em> handler.',

    'HEADING_SCOPE' => 'Scope',
    'HEADING_TEMPLATE_NAME' => 'Template Name',
    'HEADING_DESCRIPTION' => 'Description',
    'HEADING_UPDATED_BY' => 'Last Updated By',
    'HEADING_LAST_UPDATE' => 'Last Updated Date',
    'HEADING_ACTION' => 'Action',

    'COLUMN_HEADING_SCOPE' => 'Template Scope:',
    'INSTRUCTIONS_SCOPE' => 'A DbIo template can be either <em>Private</em> for your use only or <em>Public</em> and available for all authorized admin users.',
    'COLUMN_HEADING_NAME' => 'Template Name:',
    'INSTRUCTIONS_NAME' => 'When you perform a template-based <b>export</b>, the template\'s <em>name</em> forms a part of the export\'s filename. Use only alphanumeric characters and underscores (_) for the name.  The name that you choose must be unique within the selected <em>scope</em> of your available templates.',
    'COLUMN_HEADING_DESCRIPTION' => 'Template Description:',
    'INSTRUCTIONS_DESCRIPTION' => 'Use the template\'s description (no HTML allowed!) to remind yourself of the template\'s purpose.  You can customize the description for each language supported by your store.',
    'COLUMN_HEADING_CHOOSE_FIELDS' => 'Choose Template Fields:',
    'COLUMN_HEADING_COPY_FIELDS' => 'Template Fields:',
    'INSTRUCTIONS_CHOOSE' => 'You can move fields from those <em>available</em> (on the left) to those chosen (on the right) for this template.  Multiple fields can be selected at once by holding down the &quot;Ctrl&quot; key while you click additional fields with your mouse.<br><br>Once you have chosen the fields for this template, use the buttons to move the fields up or down within your customized list.  When the template is used for an <b>export</b> operation, the resulting .CSV file contains columns in the order that you specified.',
    'INSTRUCTIONS_CHOOSE_COPY' => 'When you <em>copy</em> a template, the copied template initially contains the fields previously configured.  Once the template is copied, you can edit that copy to adjust the fields.  If you can\'t view all the fields, use your mouse and drag the drop-down list down until all are visible.',

    'NO_TEMPLATES_EXIST' => 'There are currently no templates defined.  Use the &quot;New Template&quot; button to add one!',
    'TEXT_ENTER_REPORT_DESCRIPTION_HERE' => 'Enter the report\'s description here.',

    'BUTTON_EDIT' => 'Edit',
    'BUTTON_EDIT_TITLE' => 'Click here to edit this template',
    'BUTTON_COPY' => 'Copy',
    'BUTTON_COPY_TITLE' => 'Click here to copy this template',
    'BUTTON_REMOVE' => 'Remove',
    'BUTTON_REMOVE_TITLE' => 'Click here to permanently remove this template',
    'BUTTON_NEW' => 'New Template',
    'BUTTON_NEW_TITLE' => 'Click here to create a new DbIo template for the current handler',

    'BUTTON_INSERT' => 'Insert',
    'BUTTON_INSERT_TITLE' => 'Click here to create a new DbIo template',
    'BUTTON_UPDATE' => 'Update',
    'BUTTON_UPDATE_TITLE' => 'Click here to update this DbIo template',
    'BUTTON_RETURN' => 'DbIo Manager',
    'BUTTON_RETURN_TITLE' => 'Click here to return to the DbIo Manager\'s main page',
    'BUTTON_CANCEL' => 'Cancel',
    'BUTTON_CANCEL_TITLE' => 'Click here to cancel the current action',

    'INSTRUCTIONS_MAIN' => 'Use this page to customize an export template for the DbIo\'s <em>%1$s</em> handler.  You can choose a subset of the fields supported by this handler and also customize the order in which those fields are exported into their associated .csv-file columns. For additional information, refer to this <a href="https://github.com/lat9/dbio/wiki/Manage-DbIo-Templates" target="_blank" rel="noreferrer noopener">Wiki article</a>.' .
    '<br><br>' .
    'A template\'s <em>scope</em> can be either <b>%%TEXT_SCOPE_PUBLIC%%</b>, available for all admin users, or <b>%%TEXT_SCOPE_PRIVATE%%</b>, available only for your use.  Its <em>name</em> will form a portion of an exported csv-file\'s name if the template is used to customize an export action, e.g. <code>dbio.%1$s.template_name.datetime_string</code>.  The <em>description</em> that you provide is displayed on the main <strong>Database I/O Manager</strong> screen when the template is selected, giving you some confirmation of the template\'s features.',

    'ERROR_UNKNOWN_HANDLER' => 'An unknown handler name was specified, please try again.',
    'ERROR_TEMPLATE_NAME_EXISTS' => 'A &quot;%3$s&quot; template named \'%2$s\' already exists for the <em>%1$s</em> handler.  Please choose a different name.',
    'ERROR_TEMPLATE_NAME_INVALID_CHARS' => 'The template name you entered contains invalid characters.  Please re-enter the name, using <b>only</b> alphanumeric characters and underscores.',
    'ERROR_TEMPLATE_NAME_TOO_LONG' => 'The template name you entered has too many characters.  Please re-enter the name using %u or fewer characters.',
    'ERROR_TEMPLATE_NO_FIELDS' => 'Please choose at least one customized field for this DbIo template.',
    'SUCCESS_TEMPLATE_ADDED' => 'The DbIo template named <em>%s</em> was successfully added.',
    'SUCCESS_TEMPLATE_UPDATED' => 'The DbIo template named <em>%s</em> was successfully updated.',
    'SUCCESS_TEMPLATE_REMOVED' => 'The DbIo template named <em>%s</em> was successfully removed.',

    'JS_MESSAGE_CONFIRM_REMOVE' => 'Are you sure you want to permanently remove this template?',
    'JS_MESSAGE_NAME_CANT_BE_EMPTY' => 'The Template Name field cannot be empty.',
    'JS_MESSAGE_NAME_TOO_LONG' => 'The Template Name field must not be longer than %u characters.',
    'JS_MESSAGE_AT_LEAST_ONE_FIELD' => 'A template-customization must include at least one field.',
    'JS_MESSAGE_ERRORS_EXIST' => 'You need to make some changes to the form\\\'s input:',
    'JS_MESSAGE_TRY_AGAIN' => 'Please make those corrections and try again.',
];

return $define;
