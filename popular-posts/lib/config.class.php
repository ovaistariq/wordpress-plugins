<?php

class PP_config {
	// mysql table name
	const TABLE_NAME_HITS			= "popular_posts_hits";
	const TABLE_NAME_HITS_TEMP		= "popular_posts_temp";
	const TABLE_NAME_HITS_COPY		= "popular_posts_hits_copy";
	const TABLE_NAME_SUMMARY		= "popular_posts_summary";
	const TABLE_NAME_ARCHIVE		= "popular_posts_archive";

	// plugin version name and its key
	const VERSION_NUMBER			= "1.0";
	const VERSION_NUMBER_KEY	= "pp_db_version";

	// cron scheduling related
	const CRON_INTERVAL			 = "hourly";
	const CRON_INTERVAL_SECONDS = 3600;
	const CRON_INTERVAL_DESC	 = "Once Every Hour";
	const CRON_PREAGG_EVENT		 = "pp_preaggregate";
}