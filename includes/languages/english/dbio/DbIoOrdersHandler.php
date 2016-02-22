<?php
// -----
// Part of the DataBase Import/Export (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016, Vinos de Frutas Tropicales.
//

// -----
// Defines the handler's descriptive text.
//
define ('DBIO_ORDERS_DESCRIPTION', 'This report-format supports <em>export-only</em> of all fields within the &quot;orders&quot; table.  The information <em>does not</em> include the associated products or their attributes. Use the filters below to limit the orders that are included in this report.');

// -----
// Definitions that are used for the export-filters, displayed on Tools->Database I/O Manager
//
define ('DBIO_ORDERS_ORDERS_ID_RANGE_LABEL', '(optional) Choose the range of Order ID values to include.');
define ('DBIO_ORDERS_ORDERS_ID_MIN_LABEL', 'Minimum (inclusive)');
define ('DBIO_ORDERS_ORDERS_ID_MAX_LABEL', 'Maximum (inclusive)');
