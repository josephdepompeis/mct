<?php

function wplms_get_random_color($i=NULL){
$color_array = array(
		'#7266ba',
 		'#23b7e5',
 		'#f05050',
 		'#fad733',
 		'#27c24c',
 		'#fa7252'
	);
if(isset($i)){
	if(isset($color_array[$i]))
	return $color_array[$i];
}
$k = array_rand($color_array);
return $color_array[$k];
}

function wplms_dashboard_template() {

	if(!is_user_logged_in())
		wp_redirect(site_url());

	$template ='templates/dashboard';
	global $bp;
    if($bp->current_component == 'dashboard'){ 
		wp_enqueue_style( 'wplms-dashboard-css',  WPLMS_DASHBOARD_URL.'/css/wplms-dashboard.css',array(),'1.0');
		wp_enqueue_script( 'wplms-dashboard-js', WPLMS_DASHBOARD_URL.'/js/wplms-dashboard.js',array('jquery','jquery-ui-sortable'),'1.0');
		if ( is_active_widget( false, false, 'wplms_instructor_dash_stats', true ) || is_active_widget( false, false, 'wplms_dash_stats', true )) {
			wp_enqueue_script( 'wplms-sparkline', WPLMS_DASHBOARD_URL.'/js/jquery.sparkline.min.js',array('jquery'),true);
		}
		if ( is_active_widget( false, false, 'wplms_instructor_stats', true ) || is_active_widget( false, false, 'wplms_instructor_commission_stats', true )
			|| is_active_widget( false, false, 'wplms_student_stats', true )) {
			wp_enqueue_script( 'wplms-raphael',WPLMS_DASHBOARD_URL.'/js/raphael-min.js',array('jquery'),true);
      		wp_enqueue_script( 'wplms-morris',WPLMS_DASHBOARD_URL.'/js/morris.min.js',array('jquery'),true);
		}
		$translation_array = array(
			'earnings' => __('Earnings','wplms-dashboard'),
			'payout'=>__('Payout','wplms-dashboard'),
			'students'=>__('# Students','wplms-dashboard'),
			'saved'=>__('SAVED','wplms-dashboard'),
			'saving'=>__('SAVING ...','wplms-dashboard'),
			'select_recipients' => __('Select recipients...','wplms-dashboard'),
			'stats_calculated'=>__('Stats Calculated, reloading page ...','wplms-dashboard')
			);
		wp_localize_script( 'wplms-dashboard-js', 'wplms_dashboard_strings', $translation_array );
	}
	

	$located_template = apply_filters( 'bp_located_template', locate_template( $template , false ), $template );	
	if ( $located_template && $located_template !='' )	{
		bp_get_template_part( apply_filters( 'bp_load_template', $located_template ) );
	}else{
	    bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/dashboard' ) );
	}
}


add_action('widgets_init','wplms_dashboard_setup_sidebars');
function wplms_dashboard_setup_sidebars(){
if(function_exists('register_sidebar')){
	register_sidebar( array(
		'name' => __('Student Sidebar','wplms-dashboard'),
		'id' => 'student_sidebar',
		'before_widget' => '<div id="%1$s" class="%2$s">',
		'after_widget' => '</div>',
		'before_title' => '<h4 class="dash_widget_title">',
		'after_title' => '</h4>',
        'description'   => __('This is the dashboard sidebar for Students','wplms-dashboard')
	) );
	register_sidebar( array(
		'name' => __('Instructor Sidebar','wplms-dashboard'),
		'id' => 'instructor_sidebar',
		'before_widget' => '<div id="%1$s" class="%2$s">',
		'after_widget' => '</div>',
		'before_title' => '<h4 class="dash_widget_title">',
		'after_title' => '</h4>',
        'description'   => __('This is the dashboard sidebar for Instructors','wplms-dashboard')
	) );
    }
}


add_action('init','wplms_register_news');
function wplms_register_news(){

  if(function_exists('vibe_get_option')){
    
    $show_news = vibe_get_option('show_news');
    if(empty($show_news))
      return;

    add_action( 'init','wplms_register_course_news',20);
  }
}


function wplms_register_course_news(){

    if( !defined('WPLMS_NEWS_SLUG')){
      define('WPLMS_NEWS_SLUG','news');
    }

    register_post_type( 'news',
    array(
      'labels' => array(
        'name' => __('Course News','wplms-dashboard'),
        'menu_name' => __('Course News','wplms-dashboard'),
        'singular_name' => __('News','wplms-dashboard'),
        'add_new_item' => __('Add News','wplms-dashboard'),
        'all_items' => __('Course News','wplms-dashboard')
      ),
      'public' => true,
      'publicly_queryable' => true,
      'show_ui' => true,
      'capapbility_type' => 'post',
      'has_archive' => true,
      'show_in_admin_bar' => true,
      'show_in_menu'=>'lms',
      'show_in_nav_menus' => true,
      'taxonomies' => array( 'news-tag'),
      'supports' => array( 'title','editor','thumbnail','author','post-formats','comments','excerpt','revisions','custom-fields'),
      'hierarchical' => true,
      'rewrite' => array( 'slug' => WPLMS_NEWS_SLUG, 'hierarchical' => true, 'with_front' => false )
    )
  );

  register_taxonomy( 'news-tag', array( 'news'),
  array(
    'labels' => array(
      'name' => __('News Tag','wplms-dashboard'),
      'menu_name' => __('News Tag','wplms-dashboard'),
      'singular_name' => __('News Tag','wplms-dashboard'),
      'add_new_item' => __('Add New Tag','wplms-dashboard'),
      'all_items' => __('All News Tags','wplms-dashboard')
    ),
    'public' => true,
    'hierarchical' => false,
    'show_ui' => true,
    'show_admin_column' => 'true',
    'show_in_nav_menus' => true,
    'rewrite' => array( 'slug' => 'news-tag', 'hierarchical' => true, 'with_front' => false ),
  )
);
  $prefix = 'vibe_';
  $news_metabox = array(  
  array( // Text Input
    'label' => __('Share with students in Course','wplms-dashboard'), // <label>
    'desc'  => __('Student having access to this courses will get the news','wplms-dashboard'), // description
    'id'  => $prefix.'news_course', // field id and name
    'type'  => 'selectcpt', // type of field
    'post_type'=>'course'
  ),
  array( // Single checkbox
'label' => __('Post Sub-Title','wplms-dashboard'), // <label>
'desc'  => __('Post Sub- Title.','wplms-dashboard'), // description
'id'  => $prefix.'subtitle', // field id and name
'type'  => 'textarea', // type of field
    'std'   => ''
            ), 
  );
  if(class_exists('custom_add_meta_box'))
  $news_box = new custom_add_meta_box( 'page-settings', __('News Settings','wplms-dashboard'), $news_metabox, 'news', true );
}

