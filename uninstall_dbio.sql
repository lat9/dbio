DELETE FROM configuration WHERE configuration_key LIKE 'DBIO_%';
DELETE FROM configuration_group WHERE configuration_group_title = 'Database I/O Manager Settings' LIMIT 1;
DROP TABLE IF EXISTS dbio_stats;
DELETE FROM admin_pages WHERE page_key IN ('toolsDbIo', 'configDbIo');