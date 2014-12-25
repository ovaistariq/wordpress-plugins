<?php

/*
 * Plugin Name: Popular Stories Plugin
 * Description: A plugin that manages hits recieved by stories.
 * Author: <a href="mailto:ovaistariq@gmail.com">Ovais Tariq</a>
 * Version: 1.0
 */

define('POPULAR_POSTS_PLUGIN_DIR', trailingslashit(WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__))));
define('POPULAR_POSTS_PLUGIN_DIR', trailingslashit(WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__))));

/*
 * including required files/classes for the plugin
 */
require_once POPULAR_POSTS_PLUGIN_DIR . 'lib/popularposts.class.php';
require_once POPULAR_POSTS_PLUGIN_DIR . 'lib/config.class.php';

// set up the plugin activation and deactivation hook
register_activation_hook(__FILE__,'Popular_posts_plugin::install');
register_deactivation_hook(__FILE__, 'Popular_posts_plugin::uninstall');

// expose global function for displaying popular posts
if(!function_exists('pp_get_popular_posts')) {
    /*
     * @param int $category_id - the category from which popular posts are to shown
     * @param int $interval - the time period in minutes within which to show the popular posts from
     * @param int $count - the number of posts to show
     */
    function pp_get_popular_posts($category_id, $interval, $count) {
        global $popular_posts_plugin;

        return $popular_posts_plugin->get_popular_posts($category_id, $interval, $count);
    }
}

// initialize the plugin
$popular_posts_plugin = new Popular_posts_plugin();
$popular_posts_plugin->init();

class Popular_posts_plugin {
    public $handler;

    public static function install() {
        global $wpdb;

        // create the required tables
        $table_name_hits            = $wpdb->prefix . PP_config::TABLE_NAME_HITS;
        $table_name_hits_shadow = $wpdb->prefix . PP_config::TABLE_NAME_HITS_COPY;
        $table_name_summary     = $wpdb->prefix . PP_config::TABLE_NAME_SUMMARY;
        $table_name_archive     = $wpdb->prefix . PP_config::TABLE_NAME_ARCHIVE;

        $install_sql = "";

        if($wpdb->get_var("SHOW TABLES LIKE '$table_name_hits'") != $table_name_hits) {
            $install_sql .= "CREATE TABLE `{$table_name_hits}` (
                                      `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                      `post_id` bigint(20) unsigned NOT NULL
                                    ) ENGINE=MyISAM;";
        }

        if($wpdb->get_var("SHOW TABLES LIKE '$table_name_hits_shadow'") != $table_name_hits_shadow) {
            $install_sql .= "CREATE TABLE `{$table_name_hits_shadow}` LIKE `{$table_name_hits}`;";
        }

        if($wpdb->get_var("SHOW TABLES LIKE '$table_name_summary'") != $table_name_summary) {
            $install_sql .= "CREATE TABLE `{$table_name_summary}` (
                                      `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                      `hits` bigint(21) NOT NULL DEFAULT '0',
                                      `post_id` bigint(20) unsigned NOT NULL,
                                      PRIMARY KEY  (`ts`,`post_id`,`hits`)
                                    ) ENGINE=MyISAM;";
        }

        if($wpdb->get_var("SHOW TABLES LIKE '$table_name_archive'") != $table_name_archive) {
            $install_sql .= "CREATE TABLE `{$table_name_archive}` (
                                      `ts` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                      `post_id` BIGINT(20) UNSIGNED NOT NULL,
                                      KEY `pp_postid_ts` (`post_id`,`ts`)
                                    ) ENGINE=MYISAM PARTITION BY HASH(post_id) PARTITIONS 10;";
        }

        if($install_sql) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($install_sql);

            add_option(PP_config::VERSION_NUMBER_KEY, PP_config::VERSION_NUMBER);
        }

        // setup the cron schedule
        if (!wp_next_scheduled(PP_config::CRON_PREAGG_EVENT))
            wp_schedule_event(time(), PP_config::CRON_INTERVAL, PP_config::CRON_PREAGG_EVENT);
    }

    public static function uninstall() {
        wp_clear_scheduled_hook(PP_config::CRON_PREAGG_EVENT);
    }

    public function __construct() {
        $this->handler = new Popular_posts();
    }

    public function init() {
        $this->_hook_actions();

        $this->_hook_filters();
    }

    private function _hook_actions() {
        // record hits whenever a post is visited       
        add_action('wp_head', array($this->handler, 'record_hit'));

        // run the pre-aggregation event on scheduled times
        add_action(PP_config::CRON_PREAGG_EVENT, array($this->handler, 'do_preaggregate'));
    }

    private function _hook_filters() {
        // add schedule to the cron schedules array
        add_filter('cron_schedules', array($this, 'cron_add_schedule'));
    }

    public function cron_add_schedule($schedules) {
        // Adds the scheduled time to the existing schedules.
        $schedules[PP_config::CRON_INTERVAL] = array(
            'interval' => PP_config::CRON_INTERVAL_SECONDS,
            'display' => __(PP_config::CRON_INTERVAL_DESC)
        );
        
        return $schedules;
    }

    public function get_popular_posts($category_id, $interval, $count) {
        if($this->handler instanceof Popular_posts) {
            return $this->handler->get_popular_posts($category_id, $interval, $count);
        }
    }
}
