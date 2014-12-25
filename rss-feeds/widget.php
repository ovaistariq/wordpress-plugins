<?php

class BlogsPostsWidget extends WP_Widget
{
    const DEFAULT_FEED_URL = 'http://www.ovaistariq.net/feed/';
    const DEFAULT_LIMIT    = 5;
    const DEFAULT_TITLE    = "Ovais Tariq's Posts";

    function  __construct() {
        parent::__construct('BlogsPostsWidget', __('Blog Posts Widget'), array('description' => __('Shows post from other blogs')));
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;

        $instance['feed_url'] = $new_instance['feed_url'];
        $instance['title']    = $new_instance['title'];
        $instance['limit']    = intval($new_instance['limit']);
        
        return $instance;
    }

    function form($instance) {
        global $wpdb;

        $defaults = array(
            'feed_url' => self::DEFAULT_FEED_URL,
            'limit'    => self::DEFAULT_LIMIT,
            'title'    => __(self::DEFAULT_TITLE)
        );
        
        $instance = wp_parse_args( (array) $instance, $defaults );

        $feed_url = $instance['feed_url'];
        $title    = $instance['title'];
        $limit    = intval($instance['limit']);

    ?>
    <p>
         <label for="<?php echo $this->get_field_id('title');?>">Title: </label>
         <input id="<?php echo $this->get_field_id('title')?>" name="<?php echo $this->get_field_name('title');?>"
                  type="text" value="<?php echo $title;?>"/><br/>

         <label for="<?php echo $this->get_field_id('feed_url');?>">Feed url: </label>
         <input id="<?php echo $this->get_field_id('feed_url')?>" name="<?php echo $this->get_field_name('feed_url');?>"
                  type="text" value="<?php echo $feed_url;?>"/><br/>

         <label for="<?php echo $this->get_field_id('limit');?>">No. of posts: </label>
         <input id="<?php echo $this->get_field_id('limit')?>" name="<?php echo $this->get_field_name('limit');?>"
                  type="text" value="<?php echo $limit;?>" size="4" />
    </p>
<?php
    }

    function widget($args,$instance) {
        extract($args);

        $feed_url = $instance['feed_url'];
        $title    = $instance['title'];
        $limit    = intval($instance['limit']);

        include_once(ABSPATH . WPINC . '/rss.php');
        if($feed = fetch_rss($feed_url)):
            
            $feed->items = array_slice($feed->items, 0, $limit);
?>
             <h3 class="modern"><?php echo $title;?></h3>
             <ul id="blog_feed_container" class="sidebar-right-child">

                <?php foreach($feed->items as $item): ?>

                 <li class="clear feed-item">
                        <div class="left feed-author">
                            <a href="<?php echo $item['dc']['creator_link']; ?>" >
                                <img src="<?php echo $item['dc']['creator_photo']; ?>" alt="<?php echo $item['dc']['creator']; ?>">
                            </a>
                        </div>
                     
                        <h4 class="feed-title">
                            <a href="<?php echo $item['link']; ?>"><?php echo $item['title']; ?></a>
                        </h4>

                        <span class="feed-author-name"><?php echo $item['dc']['creator']; ?></span>
                        
                        <div class="clear"></div>
                 </li>
                 
                <?php endforeach; ?>

             </ul>
<?php
        endif;
    }
}