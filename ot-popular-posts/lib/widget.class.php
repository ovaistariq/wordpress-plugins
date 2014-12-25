<?php

/**
 * Description of widgetclass
 *
 * @author Ovais Tariq
 */
class PP_widget extends WP_Widget
{
	const DEFAULT_TITLE         = 'Most Popular Posts';
	const DEFAULT_CATEGORY      = PP_Popular_posts::DEFAULT_CATEGORY;
	const DEFAULT_INTERVAL_NUM  = PP_Popular_posts::DEFAULT_INTERVAL;
	const DEFAULT_INTERVAL_TYPE = PP_Popular_posts::DEFAULT_INTERVAL_TYPE;
	const DEFAULT_LIMIT         = PP_Popular_posts::DEFAULT_NUM_POSTS;

	public function __construct()
	{
		$widget_options = array(
			'classname'   => 'pp-widget',
			'description' => 'Displays the most popular posts. The popular posts can be filtered by category and interval.'
		);
		
		parent::__construct( 'pp_widget', __( 'OT Popular Posts' ), $widget_options );
	}

	public function update($new_instance, $old_instance)
	{
		$instance = $old_instance;
		
		// setup the title
		$instance['title'] =
			( $new_instance['title'] ) ? esc_html( $new_instance['title'] ) : self::DEFAULT_TITLE;
		
		// setup the user selected categories
		if( $new_instance['category'] )
		{
			$category = array_map( 'intval', (array)$new_instance['category'] );

			if( count( $category ) > 0 && false !== ($key = array_search( self::DEFAULT_CATEGORY, $category ) ) )
				unset( $category[$key] );

			$instance['category'] = $category;
		}
		else
		{
			$instance['category'] = (array)self::DEFAULT_CATEGORY;
		}
		
		// setup the interval duration
		$instance['interval_num'] =
			( $new_instance['interval_num'] ) ? (int)$new_instance['interval_num'] : self::DEFAULT_INTERVAL_NUM;

		// setup the interval type
		$instance['interval_type'] =
			( $new_instance['interval_type'] ) ? esc_attr( $new_instance['interval_type'] ) : self::DEFAULT_INTERVAL_TYPE;

		// setup the limit
		$instance['limit'] = ( $new_instance['limit'] ) ? (int)$new_instance['limit'] : self::DEFAULT_LIMIT;

		return $instance;
	}

	public function form($instance)
	{
		$defaults = array(
			'title'         => self::DEFAULT_TITLE,
			'category'      => (array)self::DEFAULT_CATEGORY,
			'interval_num'  => self::DEFAULT_INTERVAL_NUM,
			'interval_type' => self::DEFAULT_INTERVAL_TYPE,
			'limit'         => self::DEFAULT_LIMIT
		);

		$instance = wp_parse_args( (array)$instance, $defaults );
		
		extract( $instance );

		$categories     = get_categories( array( 'child_of' => 0, 'orderby' => 'name', 'order' => 'asc' ) );
		$interval_types = PP_Popular_posts::$interval_types;

		?>

		<div class="pp-widget-form">

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ) ?>">
				<?php _e( 'Title of widget:' ); ?>
			</label>
			<br />
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" size="33" type="text"
					 name="<?php echo $this->get_field_name( 'title' ); ?>"
					 value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'category' ) ?>">
				<?php _e( 'Show popular posts from the following categories:' ); ?>
			</label>
			<br />

			<select id="<?php echo $this->get_field_id( 'category' ) ?>" multiple class="pp-category"
					  size="5" name="<?php echo $this->get_field_name( 'category' ) ?>[]">
				<option value="<?php echo self::DEFAULT_CATEGORY; ?>"
					<?php echo in_array( self::DEFAULT_CATEGORY, $category ) ? 'selected' : ''; ?>>
					All
				</option>
				<?php foreach( $categories as $cat ) : ?>
				<option value="<?php echo $cat->cat_ID; ?>"
					<?php echo in_array( $cat->cat_ID, $category ) ? 'selected' : ''; ?>>
					<?php echo $cat->cat_name; ?>
				</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'interval_num' ) ?>">
					  <?php _e( 'Show posts that have been popular during the following duration (leave the following field blank to show all time popular posts):' ); ?>
			</label>
			<br />
			<input id="<?php echo $this->get_field_id( 'interval_num' ) ?>"
					 name="<?php echo $this->get_field_name( 'interval_num' ) ?>"
					 type="text" value="<?php echo $interval_num; ?>" />

			<select id="<?php echo $this->get_field_id( 'interval_type' ) ?>"
					  name="<?php echo $this->get_field_name( 'interval_type' ) ?>">
				<?php foreach( $interval_types as $key => $value ) : ?>
				<option value="<?php echo $key; ?>" <?php selected( $interval_type, $key ); ?>>
					<?php echo $value; ?>
				</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'limit' ) ?>">
					  <?php _e( 'Number of posts to show:' ); ?>
			</label>
			<br />
			<input id="<?php echo $this->get_field_id( 'limit' ) ?>"
					 name="<?php echo $this->get_field_name( 'limit' ) ?>"
					 type="text" value="<?php echo $limit; ?>" />
		</p>

		</div>

	<?php
	}

	public function widget($args, $instance)
	{
		// filter out the popular posts based on widget config
		
		$defaults = array(
			'title'         => self::DEFAULT_TITLE,
			'category'      => (array)self::DEFAULT_CATEGORY,
			'interval_num'  => self::DEFAULT_INTERVAL_NUM,
			'interval_type' => self::DEFAULT_INTERVAL_TYPE,
			'limit'         => self::DEFAULT_LIMIT
		);

		$instance = wp_parse_args( (array)$instance, $defaults );

		extract( $instance );

		$search = new PP_Popular_posts();
		$posts = $search->get_posts( $category, $interval_num, $interval_type, $limit );

		if( false == is_array( $posts ) || count( $posts ) < 1 ) return;

		// display the widget
		extract( $args, EXTR_SKIP );

		echo $before_widget;

		echo $before_title . $title . $after_title;

	?>

		<ul>
			<?php 
				foreach( (array)$posts as $post ):
					$post_title = esc_html( $post->post_title );
			?>

			<li>
				<a title="<?php echo $post_title; ?>"
					href="<?php echo get_permalink( $post->ID ); ?>"><?php echo $post_title ?></a>
				<span>(<?php echo $post->hits; ?> views)</span>
			</li>

			<?php endforeach; ?>
		</ul>

	<?php

		echo $after_widget;
	}
}