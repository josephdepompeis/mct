<?php
/**
 * The template for displaying Course Admin section
 *
 * Override this template by copying it to yourtheme/course/single/admin.php
 *
 * @author 		VibeThemes
 * @package 	vibe-course-module/templates
 * @version     1.8.2
 */

$user_id=get_current_user_id();
$action = bp_current_action();

if(empty($action)){
  (!empty($_GET['action'])?$action=$_GET['action']:$action='');
}

?>
<div class="item-list-tabs no-ajax " id="subnav" role="navigation">
	<ul>
		<li class="course_sub_action <?php if(!empty($action) && $action == 'admin'){ echo 'current'; } ?>">
			<a id="course_members" href="<?php echo get_permalink(get_the_ID()).apply_filters('wplms_course_admin_slugs','?action=admin'); ?>"><?php _e('Members','vibe'); ?></a>
		</li>	
		<li class="course_sub_action <?php if(isset($_GET['submissions']) || $action == 'submissions') echo 'current'; ?>">
			<a id="course_submissions" href="<?php echo get_permalink(get_the_ID()).apply_filters('wplms_course_admin_slugs','?action=admin&submissions'); ?>"><?php _e('Submissions','vibe'); ?></a>
		</li>
		<li class="course_sub_action <?php if(isset($_GET['stats'])  || $action == 'stats') echo 'current'; ?>">
			<a id="course_stats" href="<?php echo get_permalink(get_the_ID()).apply_filters('wplms_course_admin_slugs','?action=admin&stats'); ?>"><?php _e('Stats','vibe'); ?></a>
		</li>
	</ul>
</div>
<div id="message" class="info vnotice">
  <?php do_action('bp_course_custom_notice_instructors'); ?>
</div>
<?php

if(isset($_GET['submissions']) || $action == 'submissions'){

locate_template( array( 'course/single/submissions.php'  ), true );

}else if(isset($_GET['stats'])  || $action == 'stats'){

	locate_template( array( 'course/single/stats.php'  ), true );
}else{


	global $post;
	if(function_exists('bp_course_get_students_count')){
		$students=bp_course_get_students_count(get_the_ID());
	}else{
		$students=get_post_meta(get_the_ID(),'vibe_students',true);	
	}

	
?>	
	<h4 class="total_students"><?php _e('Total number of Students in course','vibe'); ?><span><?php echo $students; ?></span></h4>
	<h3><?php _e('Students Currently taking this course','vibe'); ?></h3>
	<?php

	$students_undertaking=apply_filters('bp_course_admin_before_course_students_list',bp_course_get_students_undertaking(),get_the_ID());
	
	echo '<ul class="course_students">';
	if(count($students_undertaking)>0){
		foreach($students_undertaking as $student){

			if (function_exists('bp_get_profile_field_data')) {
			    $bp_name = bp_core_get_userlink( $student );

			    $bp_location='';

			    $ifield = vibe_get_option('student_field');
				if(!isset($field) || $field =='')$field='Location';
			    if(bp_is_active('xprofile'))
			    $bp_location = bp_get_profile_field_data('field='.$field.'&user_id='.$student);
			    
			    if ($bp_name) {
			    	echo '<li id="s'.$student.'"><input type="checkbox" class="member" value="'.$student.'"/>';
			    	echo get_avatar($student);
			    	echo '<h6>'. $bp_name . '</h6><span>';
				    if ($bp_location) {
				    	echo $bp_location;
				    }
				    
				    if(function_exists('bp_course_user_time_left')){
				    	echo ' ( '; bp_course_user_time_left(array('course'=>get_the_ID(),'user'=>$student));
				    	echo ' ) ';
				    }

				    echo '</span>';
				    do_action('wplms_user_course_admin_member',$student,get_the_ID());
				    // PENDING AJAX SUBMISSIONS
				    echo '<ul> 
				    		<li><a class="tip reset_course_user" data-course="'.get_the_ID().'" data-user="'.$student.'" title="'.__('Reset Course for User','vibe').'"><i class="icon-reload"></i></a></li>
				    		<li><a class="tip course_stats_user" data-course="'.get_the_ID().'" data-user="'.$student.'" title="'.__('See Course stats for User','vibe').'"><i class="icon-bars"></i></a></li>';
				    $permalinks = Vibe_CustomTypes_Permalinks::init();		
				    if(empty($permalinks) || empty($permalinks->permalinks) || empty($permalinks->permalinks['activity_slug'])){
				    	$activity_slug = '/activity';
				    }else{
				    	$activity_slug = $permalinks->permalinks['activity_slug'];
				    }
		    		echo '<li><a href="'.get_permalink().str_replace('/','',$activity_slug).'?student='.$student.'" class="tip" title="'.__('See User Activity in Course','vibe').'"><i class="icon-atom"></i></a></li>
				    		<li><a class="tip remove_user_course" data-course="'.get_the_ID().'" data-user="'.$student.'" title="'.__('Remove User from this Course','vibe').'"><i class="icon-x"></i></a></li>
				    		'.do_action('wplms_course_admin_members_functions',$student,get_the_ID()).'
				    	  </ul>';
				    echo '</li>';
			    }
			}
		}
		wp_nonce_field('vibe_security','security'); // Just random text to verify
		}else{
			
			echo '<div id="message" class="notice"><p>'.__('No Students found.','vibe').'</p></div>';
		}
		echo '</ul>';
		
		echo '<div class="course_bulk_actions">
				<strong>'.__('BULK ACTIONS','vibe').'</strong> ';

		do_action('wplms_course_admin_bulk_actions');
		wp_nonce_field('security'.get_the_ID(),'bulk_action');
		
		echo '</div>';
		if(count($students_undertaking)>0){
			echo bp_course_paginate_students_undertaking();	
		}
	
  }
?>