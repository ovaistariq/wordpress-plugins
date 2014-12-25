<?php

/*
 * Plugin Name: OT Popular Posts
 * Description: A plugin that displays popular posts according to the number of
 *				times a post has been viewed. Posts that have been popular from
 * 				now to x amount of time can be shown, as well as posts that 
 *				have been all time popular. A template tag is also provided 
 *				that can be used to show a post's views.
 * Author: <a href="http://www.ovaistariq.net/">Ovais Tariq</a>
 * Plugin URI: http://www.ovaistariq.net/
 * Version: 1.0
 */

define('OT_POPULAR_POSTS_PLUGIN_DIR', trailingslashit(WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__))));
define('OT_POPULAR_POSTS_PLUGIN_URL', trailingslashit(WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__))));

/*
 * including required files/classes for the plugin
 */
require_once OT_POPULAR_POSTS_PLUGIN_DIR . 'lib/config.class.php';
require_once OT_POPULAR_POSTS_PLUGIN_DIR . 'lib/db_access.class.php';
require_once OT_POPULAR_POSTS_PLUGIN_DIR . 'lib/manager.class.php';
require_once OT_POPULAR_POSTS_PLUGIN_DIR . 'lib/popularposts.class.php';
require_once OT_POPULAR_POSTS_PLUGIN_DIR . 'lib/widget.class.php';
require_once OT_POPULAR_POSTS_PLUGIN_DIR . 'lib/template_functions.php';

// set up the plugin activation and deactivation hook
register_activation_hook( __FILE__, 'OT_Popular_posts_plugin::install' );
register_deactivation_hook( __FILE__, 'OT_Popular_posts_plugin::uninstall' );

// initialize the plugin
$pp_plugin = new OT_Popular_posts_plugin();
$pp_plugin->init();

class OT_Popular_posts_plugin {
	public $manager;

	public static function install() {
		global $wpdb;

		// create the required tables
		$install_sql = "";

		// create the hits table
		$table_name_hits = $wpdb->prefix . PP_config::TABLE_NAME_HITS;
		if( $wpdb->get_var( "SHOW TABLES LIKE '$table_name_hits'" ) != $table_name_hits ) {
			$install_sql .= "
				CREATE TABLE `$table_name_hits` (
				  `ts` datetime NOT NULL,
				  `post_id` bigint(20) unsigned NOT NULL,
				  KEY `ts_postid` (`ts`,`post_id`)
				) ENGINE=MyISAM;";
		}

		// create the hourly summary table
		$table_name_hourly = $wpdb->prefix . PP_config::TABLE_NAME_HOURLY;
		if( $wpdb->get_var( "SHOW TABLES LIKE '$table_name_hourly'" ) != $table_name_hourly ) {
			$install_sql .= "
				CREATE TABLE `$table_name_hourly` (
					`hour` datetime NOT NULL,
					`post_id` bigint(20) unsigned NOT NULL,
					`hits` int(11) NOT NULL,
					PRIMARY KEY (`hour`,`post_id`,`hits`),
					KEY `postid_hour_hits` (`post_id`,`hour`,`hits`)
				) ENGINE=MyISAM;";
		}

		// create the daily summary table
		$table_name_daily	= $wpdb->prefix . PP_config::TABLE_NAME_DAILY;
		if( $wpdb->get_var( "SHOW TABLES LIKE '$table_name_daily'" ) != $table_name_daily ) {
			$install_sql .= "
				CREATE TABLE `$table_name_daily` (
					`day` date NOT NULL,
					`post_id` bigint(20) unsigned NOT NULL,
					`hits` int(10) unsigned NOT NULL,
					PRIMARY KEY (`day`,`post_id`,`hits`),
					KEY `postid_hits` (`post_id`,`hits`)
				) ENGINE=MyISAM;";
		}

		if( $install_sql ) {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $install_sql );

			add_option( PP_config::VERSION_NUMBER_KEY, PP_config::VERSION_NUMBER );
		}

		// setup the cron hourly schedule
		if ( false == wp_next_scheduled( PP_config::CRON_HOURLY_PREAGG_EVENT ) )
			wp_schedule_event( time(), 'hourly', PP_config::CRON_HOURLY_PREAGG_EVENT );

		// setup the cron daily schedule
		if ( false == wp_next_scheduled( PP_config::CRON_DAILY_PREAGG_EVENT ) )
			wp_schedule_event( time(), 'daily', PP_config::CRON_DAILY_PREAGG_EVENT );
	}

	public static function uninstall() {
		wp_clear_scheduled_hook( PP_config::CRON_HOURLY_PREAGG_EVENT );
		wp_clear_scheduled_hook( PP_config::CRON_DAILY_PREAGG_EVENT );
	}

	public function __construct() {
		$this->manager = new PP_manager();
	}

	public function init() {
		$this->_hook_actions();

		$this->_hook_filters();
	}

	private function _hook_actions() {
		// record hits whenever a post is visited   	
		add_action( 'wp_head', array( $this->manager, 'record_hit' ) );

		// run the pre-aggregation event on scheduled times
		add_action( PP_config::CRON_HOURLY_PREAGG_EVENT, array( $this->manager, 'preaggregate_hourly' ) );
		add_action( PP_config::CRON_DAILY_PREAGG_EVENT, array( $this->manager, 'preaggregate_daily' ) );

		// register the widget
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );

		// include the required css
		add_action( 'admin_print_styles', array( $this, 'print_admin_styles' ) );
	}

	private function _hook_filters() {
		
	}

	public function register_widgets() {
		register_widget('PP_widget');
	}

	public function print_admin_styles() {
		wp_enqueue_style('pp-admin', OT_POPULAR_POSTS_PLUGIN_URL . 'css/admin.css', array(), '1.0');
	}
}