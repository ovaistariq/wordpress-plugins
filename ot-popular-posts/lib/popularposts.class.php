<?php

/**
 * Description of popularpostsclass
 *
 * @author ovais.tariq
 */
class PP_Popular_posts extends PP_db_access
{
	const DEFAULT_CATEGORY      = 0;
	const DEFAULT_INTERVAL      = '';
	const DEFAULT_INTERVAL_TYPE = '';
	const DEFAULT_NUM_POSTS     = 10;

	public static $interval_types = array(
		''       => '',
		'hours'  => 'Hours',
		'days'   => 'Days',
		'months' => 'Months',
		'years'   => 'Years'
	);

	/**
	 * Fetch popular posts either within a certain interval or from all time
	 * @param array $category_id
	 * @param int $interval
	 * @param string $interval_type
	 * @param int $num_posts
	 */
	public function get_posts($category_id=self::DEFAULT_CATEGORY, $interval=self::DEFAULT_INTERVAL,
			  $interval_type=self::DEFAULT_INTERVAL_TYPE, $num_posts=self::DEFAULT_NUM_POSTS)
	{
		// sanitize the user input
		$params = $this->_sanitize_params( $category_id, $interval, $interval_type, $num_posts );

		extract( $params );

		// setup the number of hours and days
		$hours = 0;
		$days  = 0;
		switch ($interval_type)
		{
			case 'hours':
				if( $interval > 24 )
				{
					$days  = (int)( $interval / 24 );
					$hours = $interval % 24;
				}
				else
					$hours = $interval;

				break;

			case 'days':
				$days = $interval;
				break;

			case 'months':
				$days = 30 * $interval;
				break;

			case 'years':
				$days = 365 * $interval;
				break;
		}

		if( $hours > 0 && $days > 0 ) $sql = $this->_get_mix_sql( $days, $hours );
		elseif( $hours > 0 )          $sql = $this->_get_hourly_sql( $hours );
		elseif( $days > 0 )           $sql = $this->_get_daily_sql( $days );
		else                          $sql = $this->_get_alltime_sql();

		$hits_table = $sql;

		$sql = "
			SELECT pp.post_id, pp.hits, p.*
			FROM (
					$hits_table
					) AS pp
				INNER JOIN " . self::$_db->posts . " AS p ON pp.post_id = p.ID";

		if($category_id)
		{
			$sql .= "
				INNER JOIN " . self::$_db->term_relationships . " AS tr ON tr.object_id  = pp.post_id
				INNER JOIN " . self::$_db->term_taxonomy . " AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id";
		}

		$sql .= " WHERE 1 = 1";

		if($category_id)
			$sql .= " AND tt.taxonomy = 'category' AND tt.term_id in ( " . implode( ',', $category_id ) . ")";

		$sql .= " LIMIT $num_posts";

		return self::$_db->get_results( $sql );
	}

	/**
	 *
	 * @param array $post_ids
	 * @return array|bool array of posts on success false on failure
	 */
	public function get_post_views( $post_ids = array() )
	{
		$post_ids = (array)$post_ids;

		if( false == is_array( $post_ids ) || count( $post_ids ) < 1 ) return false;

		$post_ids = array_map( 'intval', $post_ids );
		$post_ids = implode( ', ', $post_ids );

		$sql = "
			SELECT post_id, SUM(hits) AS hits
			FROM
			(
				SELECT post_id, SUM(hits) AS hits
				FROM " . self::$_table_daily . "
				WHERE post_id IN ($post_ids)
				GROUP BY post_id

				UNION ALL

				SELECT post_id, SUM(hits) AS hits
				FROM " . self::$_table_hourly . "
				WHERE post_id IN ($post_ids)
				AND HOUR >= UTC_DATE - INTERVAL 1 DAY
				GROUP BY post_id

				UNION ALL

				SELECT post_id, COUNT(post_id) AS hits
				FROM " . self::$_table_hits . "
				WHERE post_id IN ($post_ids)
				AND ts >= CONCAT(LEFT(UTC_TIMESTAMP - INTERVAL 1 HOUR, 14), '00:00')
				GROUP BY post_id
			) AS summary
			GROUP BY post_id";

		$results = self::$_db->get_results( $sql );

		$posts = array();
		foreach( (array)$results as $post )
			$posts[$post->post_id] = $post->hits;

		return $posts;
	}

	/**
	 * Generate the sql for an interval of x hours from current time
	 * @param int $num_hours
	 * @return string
	 */
	private function _get_hourly_sql($num_hours)
	{
		return "
			SELECT post_id, SUM(hits) AS hits
			FROM

			(
			SELECT post_id, hits
			FROM " . self::$_table_hourly . "
			WHERE `hour` BETWEEN CONCAT(LEFT(UTC_TIMESTAMP - INTERVAL " . ( $num_hours - 1 ) . " HOUR, 14), '00:00')
							 AND CONCAT(LEFT(UTC_TIMESTAMP, 14), '00:00')

			UNION ALL

			SELECT post_id, 1 AS hits
			FROM " . self::$_table_hits . "
			WHERE ts BETWEEN (UTC_TIMESTAMP - INTERVAL " . ( $num_hours - 1 ) . " HOUR)
						AND CONCAT(LEFT(UTC_TIMESTAMP - INTERVAL " . ( $num_hours - 1 ) . " HOUR, 14), '00:00')
				OR ts > CONCAT(LEFT(UTC_TIMESTAMP, 14), '00:00')
			) AS hourly_summary

			GROUP BY post_id
			ORDER BY hits DESC";
	}

	/**
	 * Generate the sql for x an interval of x days from current time
	 * @param int $num_days
	 * @return string 
	 */
	private function _get_daily_sql($num_days)
	{
		return "
			SELECT post_id, SUM(hits) AS hits
			FROM

			(
			SELECT post_id, hits
			FROM " . self::$_table_daily . "
			WHERE `day` BETWEEN (UTC_DATE - INTERVAL " . ( $num_days - 1 ) . " DAY) AND UTC_DATE

			UNION ALL

			SELECT post_id, hits
			FROM " . self::$_table_hourly . "
			WHERE `hour` BETWEEN CONCAT(LEFT(UTC_TIMESTAMP - INTERVAL " . ( $num_days * 24 - 1 ) . " HOUR, 14), '00:00')
				          AND (UTC_DATE - INTERVAL " . ( $num_days - 1 ) . " DAY)
				OR `hour` BETWEEN UTC_DATE AND CONCAT(LEFT(UTC_TIMESTAMP, 14), '00:00')

			UNION ALL

			SELECT post_id, 1 AS hits
			FROM " . self::$_table_hits . "
			WHERE ts BETWEEN (UTC_TIMESTAMP - INTERVAL $num_days DAY)
		            AND CONCAT(LEFT(UTC_TIMESTAMP - INTERVAL " . ( $num_days * 24 - 1 ) . " HOUR, 14), '00:00')
				OR ts > CONCAT(LEFT(UTC_TIMESTAMP, 14), '00:00')
			) AS daily_summary

			GROUP BY post_id
			ORDER BY hits DESC";
	}

	/**
	 * Generate the sql for an interval of x days and x hours
	 * @param int $num_days
	 * @param int $num_hours
	 * @return string 
	 */
	private function _get_mix_sql($num_days, $num_hours)
	{
		return "
			SELECT post_id, SUM(hits) AS hits
			FROM

			(
			SELECT post_id, hits
			FROM " . self::$_table_daily . "
			WHERE `day` BETWEEN (UTC_DATE - INTERVAL $num_days DAY) AND UTC_DATE

			UNION ALL

			SELECT post_id, hits
			FROM " . self::$_table_hourly . "
			WHERE `hour` BETWEEN CONCAT(LEFT(UTC_TIMESTAMP - INTERVAL " . ( $num_days * 24 + $num_hours - 1 ) . " HOUR, 14), '00:00')
				          AND (UTC_DATE - INTERVAL $num_days DAY)
				OR `hour` BETWEEN UTC_DATE AND CONCAT(LEFT(UTC_TIMESTAMP, 14), '00:00')

			UNION ALL

			SELECT post_id, 1 AS hits
			FROM " . self::$_table_hits . "
			WHERE ts BETWEEN (UTC_TIMESTAMP - INTERVAL " . ( $num_days * 24 + $num_hours ) . " HOUR)
				      AND CONCAT(LEFT(UTC_TIMESTAMP - INTERVAL " . ( $num_days * 24 + $num_hours - 1 ) . " HOUR, 14), '00:00')
				OR ts > CONCAT(LEFT(UTC_TIMESTAMP, 14), '00:00')
			) AS daily_summary

			GROUP BY post_id
			ORDER BY hits DESC ";
	}

	/**
	 * Generate the sql for all time hits
	 * @return string
	 */
	private function _get_alltime_sql()
	{
		return "
			SELECT post_id, SUM(hits) AS hits
			FROM

			(
			SELECT post_id, SUM(hits) AS hits
			FROM " . self::$_table_daily . "
			GROUP BY post_id

			UNION ALL

			SELECT post_id, SUM(hits) AS hits
			FROM " . self::$_table_hourly . "
			WHERE `hour` > UTC_DATE - INTERVAL 1 DAY
			GROUP BY post_id

			UNION ALL

			SELECT post_id, COUNT(post_id) AS hits
			FROM " . self::$_table_hits . "
			WHERE ts > CONCAT(LEFT(UTC_TIMESTAMP - INTERVAL 1 HOUR, 14), '00:00')
			GROUP BY post_id
			) AS alltime_summary

			GROUP BY post_id
			ORDER BY hits DESC ";
	}

	/**
	 * Sanitize the user input
	 * @param array $category_id
	 * @param int $interval
	 * @param string $interval_type
	 * @param int $num_posts
	 * @return array
	 */
	private function _sanitize_params($category_id, $interval, $interval_type, $num_posts)
	{
		// sanitize interval length and type
		$interval      = (int)$interval;
		
		$interval_type = self::$_db->escape( trim( $interval_type ) );
		if( false == array_key_exists( $interval_type, self::$interval_types )
				  || false == $interval || false == $interval_type )
		{
			$interval      = false;
			$interval_type = false;
		}

		// sanitize category selection
		$category_id = array_map( 'intval', (array)$category_id );

		if( count( $category_id ) > 0 && false !== ($key = array_search( self::DEFAULT_CATEGORY, $category_id ) ) )
			unset( $category_id[$key] );

		if( false == is_array( $category_id ) || count( $category_id ) < 1 ) $category_id = 0;

		// sanitize posts limits
		$num_posts     = (int)$num_posts;
		if( false == $num_posts ) $num_posts = self::DEFAULT_NUM_POSTS;

		return array(
			'category_id'   => $category_id,
			'interval'      => $interval,
			'interval_type' => $interval_type,
			'num_posts'     => $num_posts
		);
	}
}