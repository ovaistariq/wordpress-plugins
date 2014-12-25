<?php

/**
 * Description of popularpostsclass
 *
 * @author ovaistariq
 */
class Popular_posts {
	const INTERVAL_TYPE = "MINUTE";
	const DEFAULT_INTERVAL = 2880;
	const DEFAULT_LIMIT = 10;

	private static $_db = null;

	private static $_table_hits		= null;
	private static $_table_hits_temp	= null;
	private static $_table_hits_copy	= null;
	private static $_table_summary	= null;
	private static $_table_archive	= null;

	public function __construct() {
		self::_set_db();
	}

	private static function _set_db() {
		if(!self::$_db)
		{
			global $wpdb;

			self::$_db = $wpdb;

			self::$_table_hits		= self::$_db->prefix . PP_config::TABLE_NAME_HITS;
			self::$_table_hits_temp	= self::$_db->prefix . PP_config::TABLE_NAME_HITS_TEMP;
			self::$_table_hits_copy	= self::$_db->prefix . PP_config::TABLE_NAME_HITS_COPY;
			self::$_table_summary	= self::$_db->prefix . PP_config::TABLE_NAME_SUMMARY;
			self::$_table_archive	= self::$_db->prefix . PP_config::TABLE_NAME_ARCHIVE;
		}
	}

	public function do_preaggregate() {
		// interchange the shadow table and the original table by 
		// renaming the original table to shadow and the shadow table to original table
		$rename_sql = "RENAME TABLE " . self::$_table_hits . " TO " . self::$_table_hits_temp . ", " .
												  self::$_table_hits_copy . " TO " . self::$_table_hits . ", " .
												  self::$_table_hits_temp . " TO " . self::$_table_hits_copy;

		self::$_db->query($rename_sql);

		// now process the shadow table and collect the summary and update the summary table
		// try to insert the hits and if it fails then update the posts with new hits
		$insert_hits_sql =
			"INSERT INTO " . self::$_table_summary . "(hits, ts, post_id)
						SELECT COUNT(post_id) AS new_hits, MIN(ts) AS ts, post_id
						FROM " . self::$_table_hits_copy . " GROUP BY post_id
					ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits)";

		self::$_db->query($insert_hits_sql);

		// now that all hits have been processed, insert the hits into the archive table
		$insert_in_archive_sql =
			"INSERT INTO " . self::$_table_archive . "(ts, post_id)
				SELECT ts, post_id FROM " . self::$_table_hits_copy;
		
		self::$_db->query($insert_in_archive_sql);

		// now that all hits have been processed drop the shadow table
		self::$_db->query("TRUNCATE TABLE " . self::$_table_hits_copy);

		return true;
	}

	public function record_hit() {
		// Only record the view if it is a post or a page.  This keeps us from recording listings pages.
		if (is_single() && !$this->is_editor_logged_in()) {
			global $post;
			
			if(!empty($post->ID) && $post->ID != null) {
				$post_id = self::$_db->escape($post->ID);

				$sql = "INSERT INTO " . self::$_table_hits . "(post_id) VALUES($post_id)";

				return self::$_db->query($sql);
			}
		}

		return false;
	}

	public function get_popular_posts($category_id = false, $interval = self::DEFAULT_INTERVAL, $limit = self::DEFAULT_LIMIT) {
		$category_id = ($category_id) ? self::$_db->escape($category_id) : false;
		
		$interval = ($interval) ? self::$_db->escape($interval) : self::DEFAULT_INTERVAL;

		$limit = ($limit) ? self::$_db->escape($limit) : self::DEFAULT_LIMIT;

		$inner_sql = "SELECT MIN(ts) AS ts, post_id, SUM(hits) AS hits
			FROM " . self::$_table_summary . "
			WHERE ts > (CURRENT_TIMESTAMP() - INTERVAL $interval " . self::INTERVAL_TYPE . ")
			GROUP BY post_id
			ORDER BY hits DESC, ts DESC";

		$sql = "
			SELECT pp.ts, pp.post_id, pp.hits, p.*
			FROM (
					$inner_sql
					) AS pp
				INNER JOIN " . self::$_db->posts . " AS p ON pp.post_id = p.ID";

		if($category_id) {
			$sql .= "
				INNER JOIN " . self::$_db->term_relationships . " AS tr ON tr.object_id  = pp.post_id
				INNER JOIN " . self::$_db->term_taxonomy . " AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
		}

		if($category_id) {
			$sql .= "
				AND tt.taxonomy = 'category' AND tt.term_id = $category_id";
		}

		$sql .= "
			LIMIT $limit";

		$posts = self::$_db->get_results($sql);

		return $posts;
	}

	private function is_editor_logged_in() {
	  	static $editor_logged_in = NULL;

	  	if($editor_logged_in === NULL) {
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