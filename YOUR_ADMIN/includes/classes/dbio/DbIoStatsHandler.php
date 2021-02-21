<?php
// -----
// Part of the DataBase I/O Manager (aka DbIo) plugin, created by Cindy Merkin (cindy@vinosdefrutastropicales.com)
// Copyright (c) 2016-2021, Vinos de Frutas Tropicales.
//
if (!defined('IS_ADMIN_FLAG')) {
  exit('Illegal access');
}

// -----
// This DbIo class handles the export of the DbIo statistics table.
//
class DbIoStatsHandler extends DbIoHandler 
{
    public static function getHandlerInformation()
    {
        DbIoHandler::loadHandlerMessageFile('Stats'); 
        return array(
            'version' => '1.6.6',
            'handler_version' => '1.0.0',
            'include_header' => true,
            'export_only' => true,
            'description' => DBIO_STATS_DESCRIPTION,
        );
    }
    
    // -----
    // Gets and returns the header-record for the current export.  Take this opportunity to determine
    // the number of records in the 'dbio_stats' table, so that the table is truncated (i.e. emptied)
    // after the last record's export.
    //
    public function exportGetHeader() 
    {
        global $db;

        $check = $db->Execute(
            "SELECT COUNT(*) AS count
               FROM " . TABLE_DBIO_STATS
        );
        $this->stats_records_count = $check->fields['count'];
        $this->debugMessage("Stats table contains {$this->stats_records_count} entries.");
        return parent::exportGetHeader();
    }
  
    // -----
    // This function is called just prior to writing each exported record.  We'll check to see if we're
    // about to export the last record in the 'dbio_stats' table and, if so, will truncate/empty the
    // table at that time.
    //
    public function exportPrepareFields(array $fields) 
    {
        global $db;

        if ($this->stats_records_count == $this->stats['record_count']) {
            $db->Execute("TRUNCATE TABLE " . TABLE_DBIO_STATS);
        }
        return parent::exportPrepareFields($fields);
    }

// ----------------------------------------------------------------------------------
//             I N T E R N A L / P R O T E C T E D   F U N C T I O N S 
// ----------------------------------------------------------------------------------
    
    // -----
    // This function, called during the overall class construction, is used to set this handler's database
    // configuration for the DbIo operations.
    //
    protected function setHandlerConfiguration() 
    {
        $this->stats['report_name'] = 'Stats';
        $this->config = self::getHandlerInformation();
        $this->config['tables'] = array(
            TABLE_DBIO_STATS => array(
                'alias' => 'ds',
            ),
        );
    }
    
}  //-END class DbIoStatsHandler
