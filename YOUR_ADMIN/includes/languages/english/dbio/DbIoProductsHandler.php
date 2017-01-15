<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2017, Vinos de Frutas Tropicales.
//

// -----
// Defines the handler's descriptive text.
//
define ('DBIO_PRODUCTS_DESCRIPTION', 'This report-format supports import/export of <b>all</b> fields within the &quot;products&quot; and &quot;products_description&quot; tables, the basic product information. You can use the filters provided to limit this report\'s output  based on a product\'s status, manufacturer or category-tree.');

// -----
// Definitions that are used for the export-filters, displayed on Tools->Database I/O Manager
//
define ('DBIO_PRODUCTS_FILTERS_LABEL', 'Filter the output to selected manufacturers or categories. Select/deselect multiple options by using Ctrl key + click; leave all options deselected to output all manufacturers and categories.');
define ('DBIO_PRODUCTS_MANUFACTURERS_LABEL', 'Limit Manufacturers:');
define ('DBIO_PRODUCTS_CATEGORIES_LABEL', 'Limit Categories:');
define ('DBIO_PRODUCTS_STATUS_LABEL', 'Product Status:');
define ('DBIO_PRODUCTS_TEXT_STATUS_ENABLED', 'Enabled Only');
define ('DBIO_PRODUCTS_TEXT_STATUS_DISABLED', 'Disabled Only');
define ('DBIO_PRODUCTS_TEXT_STATUS_ALL', 'All Statuses');
