<?php

/**
 * Description of db_accessclass
 *
 * @author Ovais Tariq
 */
class PP_db_access
{
	protected static $_db = null;

	protected static $_table_hits;
	protected static $_table_hourly;
	protected static $_table_daily;

	protected static $_table_hourly_temp;
	protected static $_table_daily_temp;

	public function __construct()
	{
		self::_set_db();
	}

	private static function _set_db()
	{
		if( self::$_db != null ) return;

		global $wpdb;

		// setup the database object
		self::$_db = $wpdb;

		// setup the main table names
		self::$_table_hits    = self::$_db->prefix . PP_config::TABLE_NAME_HITS;
		self::$_table_hourly  = self::$_db->prefix . PP_config::TABLE_NAME_HOURLY;
		self::$_table_daily   = self::$_db->prefix . PP_config::TABLE_NAME_DAILY;

		// setup the temp table names
		self::$_table_hourly_temp = self::$_db->prefix . PP_config::TABLE_NAME_HOURLY_TEMP;
		self::$_table_daily_temp  = self::$_db->prefix . PP_config::TABLE_NAME_DAILY_TEMP;
	}
}