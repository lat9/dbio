<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2017, Vinos de Frutas Tropicales.
//

// -----
// Defines the handler's descriptive text.
//
define ('DBIO_ORDERS_DESCRIPTION', 'This report-format supports <em>export-only</em> of all fields within the &quot;orders&quot; table.  The information <em>does not</em> include the associated products or their attributes. You can use the filters provided to limit this report\'s output  based on the order\'s status, a range of order-id values or a range of dates.');

// -----
// Definitions that are used for the export-filters, displayed on Tools->Database I/O Manager
//
define ('DBIO_ORDERS_ORDERS_STATUS_LABEL', 'Select the orders\' statuses to be included in the export:');
define ('DBIO_ORDERS_ORDERS_ID_RANGE_LABEL', 'Choose the range of Order ID values to export.  Leave both fields blank to select <b>all</b> order ID values.');
define ('DBIO_ORDERS_ORDERS_ID_MIN_LABEL', 'Minimum (inclusive):');
define ('DBIO_ORDERS_ORDERS_ID_MAX_LABEL', 'Maximum (inclusive):');

define ('DBIO_ORDERS_ORDERS_DATE_RANGE_LABEL', 'Choose the range of order-placed dates to export.  Enter the dates in YYYY-MM-DD format.  Leave both fields blank to select <b>all</b> order dates.');
define ('DBIO_ORDERS_ORDERS_DATE_MIN_LABEL', 'Start:');
define ('DBIO_ORDERS_ORDERS_DATE_MAX_LABEL', 'End:');
