<?php

/*
 * Plugin Name: Blog Stories
 * Description: This plugin will allow you to show posts from other blogs.
 * Author: <a href="mailto:ovaistariq@gmail.com">Ovais Tariq</a>
 * Version: 1.0
 */

define('BLOG_STORIES_PLUGIN_DIR', trailingslashit(WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__))));
define('BLOG_STORIES_PLUGIN_URL', trailingslashit(WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__))));

require_once BLOG_STORIES_PLUGIN_DIR . 'widget.php';

$blog_stories_plugin = new Blog_stories();
$blog_stories_plugin->init();

class Blog_stories
{
    public function init() {
        $this->_hook_actions();

        $this->_hook_filters();
    }

    private function _hook_actions() {
        add_action('widgets_init', array($this, 'init_widgets'));
        add_action('wp_print_styles', array($this, 'init_stylesheets'));
    }

    private function _hook_filters() {
        // Nothing to do here for now
    }

    public function init_widgets() {
        register_widget('BlogsPostsWidget');
    }

    public function init_stylesheets() {
        wp_enqueue_style('blog-stories-widget', BLOG_STORIES_PLUGIN_URL.'widget.css');
    }
}