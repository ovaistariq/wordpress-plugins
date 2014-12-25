<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class PP_manager extends PP_db_access
{
	/**
	 * Only record the view if it is a post or a page.  This keeps us from recording listings pages.
	 * the recorded datetime is UTC datetime
	 * @global object $post
	 * @return bool
	 */
	public function record_hit()
	{
		if( $this->is_editor_logged_in() || false == is_single() ) return;

		global $post;

		if( false == $post->ID ) return;

		$post_id = self::$_db->escape( $post->ID );

		return self::$_db->query("INSERT INTO " . self::$_table_hits . "(ts, post_id) VALUES(UTC_TIMESTAMP, $post_id)");
	}

	/**
	 * Pre-aggregates the hits every hour.
	 * @return bool 
	 */
	public function preaggregate_hourly()
	{
		// create a temp table that will hold the hits for the hour that is being processed
		$temp_sql = "
			CREATE TABLE " . self::$_table_hourly_temp . " ENGINE=MYISAM AS
				SELECT * FROM " . self::$_table_hits . " WHERE ts >= UTC_TIMESTAMP - INTERVAL 1 HOUR";
		self::$_db->query( $temp_sql );

		// process the records in the temp table and store them in the hourly summary table
		// try to insert the hits and if it fails then update the posts with new hits
		$insert_hourly_sql = "
			INSERT INTO " . self::$_table_hourly . "(`hour`, post_id, hits)
				SELECT CONCAT(LEFT(ts, 14), '00:00') AS `hour`, post_id, COUNT(post_id) AS hits
				FROM " . self::$_table_hourly_temp . "
				GROUP BY post_id
			ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits)";

		self::$_db->query( $insert_hourly_sql );

		// now that all hits have been processed drop the temp table
		self::$_db->query( "DROP TABLE " . self::$_table_hourly_temp );

		return true;
	}

	/**
	 * Pre-aggregates the hits every day
	 * @return bool 
	 */
	public function preaggregate_daily()
	{
		// create a temp table that will hold the hits for the day that is being processed
		$temp_sql = "
			CREATE TABLE " . self::$_table_daily_temp . " ENGINE=MYISAM AS
				SELECT * FROM " . self::$_table_hourly . " WHERE `hour` >= UTC_TIMESTAMP - INTERVAL 1 DAY";
		self::$_db->query( $temp_sql );

		// process the records in the temp table and store them in the daily summary table
		// try to insert the hits and if it fails then update the posts with new hits
		$insert_sql = "
			INSERT INTO " . self::$_table_daily . "(`day`, post_id, hits)
				SELECT DATE(`hour`) AS `day`, post_id, SUM(hits) AS hits
				FROM " . self::$_table_daily_temp . "
				GROUP BY post_id
			ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits)";

		self::$_db->query( $insert_sql );

		// now that all hits have been processed drop the temp table
		self::$_db->query( "DROP TABLE " . self::$_table_daily_temp );

		return true;
	}

	private function is_editor_logged_in()
   {
	  static $editor_logged_in = NULL;

	  if($editor_logged_in === NULL)
	  {
		 /**
		  *  the currently logged in user is editor or administrator
		  * # User Level 5 converts to Editor
		  * # User Level 6 converts to Editor
		  * # User Level 7 converts to Editor
		  * # User Level 8 converts to Administrator
		  * # User Level 9 converts to Administrator
		  * # User Level 10 converts to Administrator
		  */
		  global $current_user;
		  $editor_logged_in = ($current_user->data->user_level >= 5) ? true : false;
	  }

	  return $editor_logged_in;
   }
}