<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2015-2017, Vinos de Frutas Tropicales.
//

$define = [
    // Defines the handler's descriptive text.
    'DBIO_PRODUCTS_DESCRIPTION' => 'This report-format supports import/export of <b>all</b> fields within the &quot;products&quot; and &quot;products_description&quot; tables, the basic product information. You can use the filters provided to limit this report\'s output  based on a product\'s status, manufacturer or category-tree.',
    // Definitions that are used for the export-filters, displayed on Tools->Database I/O Manager
    'DBIO_PRODUCTS_FILTERS_LABEL' => 'Filter the output to selected manufacturers or categories. Select/deselect multiple options by using Ctrl key + click; leave all options deselected to output all manufacturers and categories.',
    'DBIO_PRODUCTS_MANUFACTURERS_LABEL' => 'Limit Manufacturers:',
    'DBIO_PRODUCTS_CATEGORIES_LABEL' => 'Limit Categories:',
    'DBIO_PRODUCTS_STATUS_LABEL' => 'Product Status:',
    'DBIO_PRODUCTS_TEXT_STATUS_ENABLED' => 'Enabled Only',
    'DBIO_PRODUCTS_TEXT_STATUS_DISABLED' => 'Disabled Only',
    'DBIO_PRODUCTS_TEXT_STATUS_ALL' => 'All Statuses',
];

return $define;
