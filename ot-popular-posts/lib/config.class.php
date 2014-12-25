<?php

class PP_config
{
	// main table names
	const TABLE_NAME_HITS    = 'pp_hits';
	const TABLE_NAME_HOURLY  = 'pp_hourly_summary';
	const TABLE_NAME_DAILY   = 'pp_daily_summary';

	// temp table names
	const TABLE_NAME_HOURLY_TEMP = 'pp_hourly_temp';
	const TABLE_NAME_DAILY_TEMP  = 'pp_daily_temp';

	// plugin version name and its key
	const VERSION_NUMBER 		= '1.0';
	const VERSION_NUMBER_KEY 	= 'pp_db_version';

	// cron scheduling related
	const CRON_HOURLY_PREAGG_EVENT = 'pp_preaggregate_hourly';
	const CRON_DAILY_PREAGG_EVENT  = 'pp_preaggregate_daily';
}