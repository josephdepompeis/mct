<?php
/**
 * FILTER functions for WPLMS
 *
 * @author      VibeThemes
 * @category    Admin
 * @package     Initialization
 * @version     2.0
 */

if ( !defined( 'ABSPATH' ) ) exit;

class WPLMS_Filters{

    public static $instance;
    
    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new WPLMS_Filters();

        return self::$instance;
    }

    private function __construct(){

		add_filter( 'woocommerce_output_related_products_args', array($this,'wplms_related_products_args') );
  		
		add_filter( 'bbp_after_get_the_content_parse_args', array($this,'bbp_enable_visual_editor' ));
		add_filter('wplms_sidebar',array($this,'wplms_sidebar_select'),10,2);

		/*==== WORDPRESS SEO COMPATIBILITY ======*/

		add_filter('wpseo_title',array($this,'remove_wpseo_from_buddypress'));
		add_filter('wpseo_pre_analysis_post_content',array($this,'vibe_page_builder_content'),10,2);
		
		add_filter( 'bp_core_fetch_avatar_no_grav', '__return_true' );
		
		add_filter( 'bp_core_default_avatar_user', array($this,'vibe_custom_avatar' ));
		add_filter('wplms_activity_loop',array($this,'wplms_student_activity'));

		add_filter('get_avatar',array($this,'change_avatar_css'));
		add_filter('widget_text', 'do_shortcode');
		add_filter( 'registration_redirect' , array($this,'vibe_registration_redirect') );

		add_filter( 'bp_before_xprofile_cover_image_settings_parse_args', array($this,'wplms_xprofile_cover_image'), 10, 1 );

		//Transparent Header
		add_filter('vibe_option_custom_sections',array($this,'default_background_image_option'));
		add_filter('wplms_post_metabox',array($this,'specific_title_background'));
		add_filter('wplms_course_metabox',array($this,'specific_title_background'));
		add_filter('wplms_unit_metabox',array($this,'specific_title_background'));
		add_filter('wplms_assignment_metabox',array($this,'specific_title_background'));
		add_filter('wplms_testimonial_metabox',array($this,'specific_title_background'));
		add_filter('wplms_quiz_metabox',array($this,'specific_title_background'));
		add_filter('wplms_question_metabox',array($this,'specific_title_background'));
		add_filter('wplms_news_metabox',array($this,'specific_title_background'));
		add_filter('wplms_page_metabox',array($this,'specific_title_background'));

		add_filter('woocommerce_get_endpoint_url',array($this,'wplms_edit_address_fix'),10,4);
		// Add columns Reference WooCommerce
		add_filter( 'manage_edit-course-cat_columns', array( $this, 'course_cat_columns' ) );
		add_filter( 'manage_course-cat_custom_column', array( $this, 'course_cat_column' ), 10, 3 );
		add_filter('get_terms_orderby',array($this,'course_cat_orderby'),10,3);
		add_filter('wplms_course_filters_course_cat',array($this,'course_cat_nav_orderby'),10);

		//Show visual composer design options
		add_filter('vc_settings_page_show_design_tabs',array($this,'display_design_options'));

		//Enable Shortcodes in BuddyPress profiles
		add_filter('bp_get_the_profile_field_value', 'do_shortcode');
		add_filter('bp_get_profile_field_data', 'do_shortcode');


		/* ===== INSTRUCTOR PRIVACY ====== */
		add_filter('wplms_frontend_cpt_query',array($this,'wplms_instructor_privacy_filter'));
		add_filter('wplms_backend_cpt_query',array($this,'wplms_instructor_privacy_filter2')); // Modified to protect Product association
		add_action('pre_get_posts', array($this,'wplms_instructor_privacy_filter_attachments'));
		add_filter('bp_course_single_item_view',array($this,'restrict_draft_courses'),10);
    }

    /*
    * IMPLEMENTING INSTRUCTOR PRIVACY
    */
   
   	function wplms_instructor_privacy_filter($args=array()){
	    $instructor_privacy = vibe_get_option('instructor_content_privacy');
	    if(isset($instructor_privacy) && $instructor_privacy && !current_user_can('manage_options')){
	        global $current_user;
	        get_currentuserinfo();
	        $args['author'] = $current_user->ID;
	    }
	    return $args;
	}


	function wplms_instructor_privacy_filter2($args=array()){
	    $instructor_privacy = vibe_get_option('instructor_content_privacy');
	    if(isset($instructor_privacy) && $instructor_privacy && !current_user_can('manage_options')){
	        global $current_user;
	        get_currentuserinfo();
	        if($args['post_type'] != 'product')
	          $args['author'] = $current_user->ID;
	    }
	    return $args;
	}


	function wplms_instructor_privacy_filter_attachments($wp_query){

	  $instructor_privacy = vibe_get_option('instructor_content_privacy');
	  if(empty($instructor_privacy) || current_user_can('manage_options'))
	  return;

	  if ( $wp_query->query['post_type'] != 'attachment' || !current_user_can('edit_posts')) {
	  return;
	  }

	  $user_id = get_current_user_id();
	  $wp_query->set( 'author', $user_id );
	}

	function restrict_draft_courses($flag){
		if(is_user_logged_in()){
			if(current_user_can('edit_posts') && in_array($post->post_status,array('draft','pending')) ){
				global $post;
				$user_id = get_current_user_id();
				$instructors = array($post->post_author);
				$instructors = apply_filters('wplms_course_instructors',$instructors,$post->ID);
				if(!in_array($user_id,$instructors)){
					return 1;
				}
			}
		}

		return $flag;
	}
    /*
    * Show Design options in Visual composer
    */
    function display_design_options($x){
    	return true;
    }
    /*
    * DEFAULT ORDERBY IN COURSE CATEGORIES 
    */
    function course_cat_orderby($orderby,$args,$taxonomies){
    	if ( is_admin() || ('course-cat' != $taxonomies[0] || !empty($orderby)))
        	return $orderby;

        $orderby = 'term_group';
    	$args['order'] = 'DESC';

    	return $orderby;
    }

    function course_cat_nav_orderby($args){
    	if(empty($args['orderby'])){
           $args['orderby'] = 'term_group';
           $args['order'] = 'DESC';
       	}
    	return $args;
    }
    
    public function course_cat_columns( $columns ) {
		$new_columns          = array();
		$new_columns['cb']    = $columns['cb'];
		$new_columns['thumb'] = __( 'Image', 'vibe' );
		$new_columns['order'] = __( 'Order', 'vibe' );
		unset( $columns['cb'] );

		return array_merge( $new_columns, $columns );
	}

    public function course_cat_column( $columns, $column, $id ) {

		if ( 'thumb' == $column ) {

			$thumbnail_id = get_term_meta( $id, 'course_cat_thumbnail_id', true );

			if ( $thumbnail_id ) {
				$image = wp_get_attachment_thumb_url( $thumbnail_id );
			} else {
				$image = vibe_get_option('default_avatar');
				if(empty($image)){
					$image = VIBE_URL.'/assets/images/avatar.jpg';
				}
			}
			$image = str_replace( ' ', '%20', $image );

			$columns .= '<img src="' . esc_url( $image ) . '" alt="' . esc_attr__( 'Thumbnail', 'vibe' ) . '" class="wp-post-image" height="48" width="48" />';

		}

		if('order' == $column){
			$course_cat_order = get_term_meta($id,'course_cat_order',true);
			if(empty($course_cat_order))
				$course_cat_order = 0;
			$columns .= $course_cat_order;
		}
		return $columns;
	}

    function wplms_edit_address_fix($url, $endpoint, $value, $permalink){
    	if(in_Array($endpoint,array('edit-address','orders','downloads','payment-methods','edit-account','customer-logout'))){
    		if(bp_is_member()){
	    		$myaccount_id = get_option('woocommerce_myaccount_page_id');
				return trim(get_permalink($myaccount_id),'/').$url;	
			}
    	}
		return $url;
	}


    function default_background_image_option($sections){
    	$header_style =  vibe_get_customizer('header_style');
    	if($header_style == 'transparent'){
    		$sections[1]['fields'][] = array(
						'id' => 'title_bg',
						'type' => 'upload',
						'title' => __('Upload Title Background', 'vibe'), 
						'sub_desc' => __('Upload a background image for title', 'vibe'),
						'desc' => __('Upload title image.', 'vibe'),
                        'std' => VIBE_URL.'/assets/images/title_bg.jpg'
						);
    	}

    	return $sections;
    }
    function specific_title_background($metabox){
    	$header_style =  vibe_get_customizer('header_style');
    	if($header_style == 'transparent'){
	    	$metabox[]=array( // Text Input
						'label'	=> __('Title Background Image','vibe-customtypes'), // <label>
						'desc'	=> __('Add title background image','vibe-customtypes'), // description
						'id'	=> 'vibe_title_bg', // field id and name
						'type'	=> 'image', // type of field
					);
	    }
    	return $metabox;
    }

    function wplms_xprofile_cover_image( $settings = array() ) {
	    $settings['width']  = 1600;
	    $settings['height'] = 600;
	 
	    return $settings;
	}

    function remove_wpseo_from_buddypress($title){
    	global $bp,$post;
    	if(empty($this->bp_pages)){
    		$this->bp_pages = get_option('bp-pages');	
    	}
    		
    	if((function_exists('bp_is_directory') && bp_is_directory()) || ( !empty($this->bp_pages) && in_array($post->ID,$this->bp_pages))){
    		$title = sprintf(_x('%s Directory - %s','Directory Title format','vibe'),ucfirst(bp_current_component()),get_bloginfo('name'));
    	}
    	if (function_exists('bp_is_user') && bp_is_user()){
    		//$title = sprintf(_x('%1s group - %2s','Member Name',ucfirst(bp_get_displayed_user_fullname()),get_bloginfo('name')));
    		$title = ucfirst(bp_get_displayed_user_fullname()).' - '.get_bloginfo('name');
    	}
    	if (function_exists('bp_is_group') && bp_is_group()){
    		//$title = sprintf(_x('%1s group - %2s','Group Name',ucfirst(bp_get_current_group_name()),get_bloginfo('name')));
    		$title = ucfirst(bp_get_current_group_name()).' - '.get_bloginfo('name');
    	}
    	return $title;
    }

    function get_directory_page_id($component){
    	if(empty($this->bp_pages)){
    		$this->bp_pages = get_option('bp-pages');	
    	}
    	
		if(isset($this->bp_pages[$component])){
			return $this->bp_pages[$component];
		}

    }

   
    function wplms_related_products_args( $args ) {
	  $args['posts_per_page'] = 3; 
	  $args['columns'] = 3;
	  return $args;
	}

	function bbp_enable_visual_editor( $args = array() ) {
	    $args['tinymce'] = true;
	    return $args;
	}

	function wplms_sidebar_select($sidebar,$id = NULL){
	  if(isset($id)){
	    $selected_sidebar=get_post_meta($id,'vibe_sidebar',true);  
	    if(isset($selected_sidebar) && $selected_sidebar){

	        /*=== FOR BACKWARD COMPATIBILITY ===*/
	        if($selected_sidebar == 'mainsidebar' && $sidebar != 'mainsidebar'){
	               $selected_sidebar = $sidebar;
	        }else
	          $sidebar=$selected_sidebar; 
	        /*=== END BACKWARD COMPATIBILITY ===*/
	    }
	  }
	  return $sidebar;
	}


	function vibe_page_builder_content($post_content,$post){

	  	if(get_post_type($post->ID) != 'page')
	    	return $post_content;

	  	$builder_enable = get_post_meta( $post->ID, '_enable_builder', true );
	  	if(!empty($builder_enable)){

		    $builder_layout = get_post_meta( $post->ID, '_builder_settings', true );
		    $add_content = get_post_meta( $post->ID, '_add_content', true );
		  
		        if ( isset($builder_layout) &&  isset($builder_layout['layout_shortcode']) && '' != $builder_layout['layout_shortcode'] && $add_content == 'no') { 
		          $content = $builder_layout['layout_shortcode'];
		        }
		        
		        if ( $builder_layout && '' != $builder_layout['layout_shortcode'] && $add_content == 'yes_top') {
		            $content = $post_content.$builder_layout['layout_shortcode'];
		        }
		        
		        if ( $builder_layout && '' != $builder_layout['layout_shortcode'] && $add_content == 'yes_below') {
		            $content = $builder_layout['layout_shortcode'].$post_content;
		        }
		    $post_content = $content;    
	  	}      
	    return $post_content;
	}

	function vibe_custom_avatar($avatar){
	  	global $bp;
	   	$avatar=vibe_get_option('default_avatar');
	   	if(!isset($avatar) || !$avatar || strlen($avatar)<5)
	    	$avatar = VIBE_URL.'/assets/images/avatar.jpg';
	   	return $avatar;
	}

	function wplms_student_activity($appended){
	  	$student_activity = vibe_get_option('student_activity');
	  	if(!current_user_can('edit_posts') && isset($student_activity) && $student_activity){
	    	$appended .='&user_id='.get_current_user_id();
	  	}
	    
	    return $appended;
	}
	

	function change_avatar_css($class) {
	  	$class = str_replace("class='avatar", "class='retina_avatar zoom animate", $class) ;
	  	return $class;
	}

	function vibe_registration_redirect() {
    	$pageid=vibe_get_option('activation_redirect');
    	return get_permalink($pageid);
	}

}

WPLMS_Filters::init();

function vibe_get_directory_page($component){
	$wf = WPLMS_Filters::init();
	return $wf->get_directory_page_id($component);
}
