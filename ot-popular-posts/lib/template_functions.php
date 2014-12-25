<?php

if( false == function_exists( 'the_post_views' ) )
{
	function the_post_views($post_or_id = false)
	{
		echo get_post_views( $post_or_id );
	}
}

if( false == function_exists( 'get_post_views' ) )
{
	function get_post_views($post_or_id = false)
	{
		static $has_loop_postviews = false;
		static $post_views         = array();

		// get the postid, if the postid or post object is not passed, fetch it from the global post
		$post_id = false;
		if( false == $post_or_id )
		{
			global $post;
			$post_id = $post->ID;
		}
		elseif( is_object( $post_or_id ) )
		{
			$post_id = $post_or_id->ID;
		}
		else
		{
			$post_id = $post_or_id;
		}

		// if the postid is invalid return
		if( false == $post_id ) return false;

		// if we already have the post views for the postid return it
		if( array_key_exists( $post_id, $post_views ) ) return $post_views[$post_id];

		// initialize the class that does the popular posts stuff
		$popular_posts = new PP_Popular_posts();

		// if the function is called inside the loop, populate the post views of all the posts
		// in the loop so that we dont have to query the db again
		if( in_the_loop() )
		{
			// if we don't already have the post views of all the posts in the loop
			// then save them in a static array
			if( false == $has_loop_postids )
			{
				global $wp_query;

				$post_ids = array();
				foreach( $wp_query->posts as $post_obj ) $post_ids[] = $post_obj->ID;

				$post_views = $post_views + (array)$popular_posts->get_post_views( $post_ids );
			}
		}
		else
		{
			// cache the post view for later use
			$post_views = $post_views + (array)$popular_posts->get_post_views( $post_id );
		}

		return $post_views[$post_id];
	}
}