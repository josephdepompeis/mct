<?php
/**
 * Action functions for Course Module
 *
 * @author      VibeThemes
 * @category    Admin
 * @package     Vibe Course Module
 * @version     2.0
 */

 if ( ! defined( 'ABSPATH' ) ) exit;

class BP_Course_Action{

    public static $instance;

    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new BP_Course_Action();

        return self::$instance;
    }

    private function __construct(){
    	
    	$this->nav = get_option('vibe_course_permalinks');

		add_action('bp_activity_register_activity_actions',array($this,'bp_course_register_actions'));
		add_filter( 'woocommerce_get_price_html', array($this,'course_subscription_filter'),100,2 );
		add_action('woocommerce_after_add_to_cart_button',array($this,'bp_course_subscription_product'));
		add_action( 'woocommerce_order_status_completed',array($this, 'bp_course_convert_customer_to_student' ));
		add_action('woocommerce_order_status_completed',array($this,'bp_course_enable_access'));
		add_action('woocommerce_order_status_cancelled',array($this,'bp_course_disable_access'),10,1);
		add_action('woocommerce_order_status_refunded',array($this,'bp_course_disable_access'),10,1);
		add_action('bp_members_directory_member_types',array($this,'bp_course_instructor_member_types'));
		add_filter('bp_course_admin_before_course_students_list',array($this,'bp_course_admin_search_course_students'),10,2);
		add_filter('wplms_course_credits',array($this,'wplms_show_new_course_student_status'),20,2);	
		add_action('wplms_before_start_course',array($this,'wplms_before_start_course_status'));
		add_action('wplms_user_course_stats',array($this,'add_course_review_button'),10,2);
		add_action('bp_course_header_actions',array($this,'bp_course_schema'));
		add_action('wplms_unit_header',array($this,'wplms_custom_unit_header'),10,2);

		add_action('wplms_course_submission_quiz_tab_content',array($this,'bp_course_get_course_quiz_submissions'),10,1);
		add_action('wplms_course_submission_course_tab_content',array($this,'bp_course_get_course_submissions'),10,1);

		//apply for course
		add_action('wplms_course_submission_applications_tab_content',array($this,'get_course_applications'),10,1);

		add_action('wp_head',array($this,'remove_woocommerce_endactions'));
		add_action('wp_footer',array($this,'offline_enqueue_footer'));
	}


	function offline_enqueue_footer(){
		global $post;
		if(!empty($post) && $post->post_type == 'course'){
			$vibe_course_unit_content = get_post_meta($post->ID,'vibe_course_unit_content',true);
			if(!empty($vibe_course_unit_content) && $vibe_course_unit_content == 'S'){
				wp_enqueue_style('wp-mediaelement');
				wp_enqueue_script('wp-mediaelement');
			}
		}
	}
	function remove_woocommerce_endactions(){
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);
	}

	function bp_course_register_actions(){
		global $bp; 
		$bp_course_action_desc=array(
			'remove_from_course' => __( 'Removed a student from Course', 'vibe' ),
			'submit_course' => __( 'Student submitted a Course', 'vibe' ),
			'start_course' => __( 'Student started a Course', 'vibe' ),
			'submit_quiz' => __( 'Student submitted a Quiz', 'vibe' ),
			'start_quiz' => __( 'Student started a Course', 'vibe' ),
			'unit_complete' => __( 'Student submitted a Course', 'vibe' ),
			'reset_course' => __( 'Course reset for Student', 'vibe' ),
			'bulk_action' => __( 'Bulk action by instructor', 'vibe' ),
			'course_evaluated' => __( 'Course Evaluated for student', 'vibe' ),
			'student_badge'=> __( 'Student got a Badge', 'vibe' ),
			'student_certificate' => __( 'Student got a certificate', 'vibe' ),
			'quiz_evaluated' => __( 'Quiz Evaluated for student', 'vibe' ),
			'subscribe_course' => __( 'Student subscribed for course', 'vibe' ),
			);
		foreach($bp_course_action_desc as $key => $value){
			bp_activity_set_action($bp->activity->id,$key,$value);	
		}
	}


	function course_subscription_filter($price,$product){

		$subscription=get_post_meta($product->id,'vibe_subscription',true);

			if(vibe_validate($subscription)){
				$duration = intval(get_post_meta($product->id,'vibe_duration',true));

				$product_duration_parameter = apply_filters('vibe_product_duration_parameter',86400,$product->id);
				$t=$duration * $product_duration_parameter;
				if($duration == 1){
					$price = $price .'<span class="subs"> '.__('per','vibe').' '.tofriendlytime($t,1).'</span>';
				}else{
					$price = $price .'<span class="subs"> '.__('per','vibe').' '.tofriendlytime($t,1).'</span>';
				}
			}
			return $price;
	}





	function bp_course_subscription_product(){
		global $product;
		$check_susbscription=get_post_meta($product->id,'vibe_subscription',true);
		if(vibe_validate($check_susbscription)){
			$duration=intval(get_post_meta($product->id,'vibe_duration',true));	
			
			$product_duration_parameter = apply_filters('vibe_product_duration_parameter',86400,$product->id);
			$t=tofriendlytime($duration*$product_duration_parameter);
			echo '<div id="duration"><strong>'.__('SUBSCRIPTION FOR','vibe').' '.$t.'</strong></div>';
		}
	}

	function bp_course_convert_customer_to_student( $order_id ) {
	    $order = new WC_Order( $order_id );
	    if ( $order->user_id > 0 ) {
	        $user = new WP_User( $order->user_id );
	        $user->remove_role( 'customer' ); 
	        $user->add_role( 'student' );
	    }
	}



	function bp_course_enable_access($order_id){

		$order = new WC_Order( $order_id );

		$items = $order->get_items();
		$user_id=$order->user_id;
		$order_total = $order->get_total();
		$commission_array=array();

		foreach($items as $item_id=>$item){

		$instructors=array();
		
		$courses=get_post_meta($item['product_id'],'vibe_courses',true);

		$product_id = apply_filters('bp_course_product_id',$item['product_id'],$item);
		$subscribed=get_post_meta($product_id,'vibe_subscription',true);

		if(isset($courses) && is_array($courses)){

			if(vibe_validate($subscribed) ){

				$duration = get_post_meta($product_id,'vibe_duration',true);
				$product_duration_parameter = apply_filters('vibe_product_duration_parameter',86400,$product_id);
				$total_duration = $duration*$product_duration_parameter;
				foreach($courses as $course){
					bp_course_add_user_to_course($user_id,$course,$total_duration,1);
			        $instructors[$course]=apply_filters('wplms_course_instructors',get_post_field('post_author',$course),$course);
			        do_action('wplms_course_product_puchased',$course,$user_id,$total_duration,1,$product_id);
				}
			}else{	
				if(isset($courses) && is_array($courses)){
				foreach($courses as $course){
						bp_course_add_user_to_course($user_id,$course,'',1);
		        		$instructors[$course]=apply_filters('wplms_course_instructors',get_post_field('post_author',$course,'raw'),$course);
			        	do_action('wplms_course_product_puchased',$course,$user_id,0,0,$product_id);
					}
				}
			}//End Else

				$line_total=$item['line_total'];

			//Commission Calculation
			$commission_array[$item_id]=array(
				'instructor'=>$instructors,
				'course'=>$courses,
				'total'=>$line_total,
			);

		  }//End If courses
		}// End Item for loop
		
		if(function_exists('vibe_get_option'))
	      $instructor_commission = vibe_get_option('instructor_commission');
	    
	    if($instructor_commission == 0)
	      		return;
	      	
	    if(!isset($instructor_commission) || !$instructor_commission)
	      $instructor_commission = 70;

	    $commissions = get_option('instructor_commissions');

		foreach($commission_array as $item_id=>$commission_item){

				foreach($commission_item['course'] as $course_id){ 
				
				if(count($commission_item['instructor'][$course_id]) > 1){     // Multiple instructors
					
					$calculated_commission_base=round(($commission_item['total']*($instructor_commission/100)/count($commission_item['instructor'][$course_id])),0); // Default Slit equal propertion

					foreach($commission_item['instructor'][$course_id] as $instructor){
						if(empty($commissions[$course_id][$instructor])){
							$calculated_commission_base = round(($commission_item['total']*$instructor_commission/100),2);
						}else{
							$calculated_commission_base = round(($commission_item['total']*$commissions[$course_id][$instructor]/100),2);
						}
						$calculated_commission_base = apply_filters('wplms_calculated_commission_base',$calculated_commission_base,$instructor);
						woocommerce_update_order_item_meta( $item_id, 'commission'.$instructor,$calculated_commission_base);
					}
				}else{
					if(is_array($commission_item['instructor'][$course_id]))                                    // Single Instructor
						$instructor=$commission_item['instructor'][$course_id][0];
					else
						$instructor=$commission_item['instructor'][$course_id]; 
					
					if(isset($commissions[$course_id][$instructor]) && is_numeric($commissions[$course_id][$instructor]))
						$calculated_commission_base = round(($commission_item['total']*$commissions[$course_id][$instructor]/100),2);
					else
						$calculated_commission_base = round(($commission_item['total']*$instructor_commission/100),2);

					$calculated_commission_base = apply_filters('wplms_calculated_commission_base',$calculated_commission_base,$instructor);
					woocommerce_update_order_item_meta( $item_id, 'commission'.$instructor,$calculated_commission_base);
				}   
			}

		} // End Commissions_array  
	}

	function bp_course_disable_access($order_id){
		$order = new WC_Order( $order_id );

		$items = $order->get_items();
		$user_id=$order->user_id;
		foreach($items as $item){
			$product_id = $item['product_id'];
			$subscribed=get_post_meta($product_id,'vibe_subscription',true);
			$courses=vibe_sanitize(get_post_meta($product_id,'vibe_courses',false));

				if(isset($courses) && is_array($courses)){
				foreach($courses as $course){
					delete_post_meta($course,$user_id);
					delete_user_meta($user_id,$course);
					$group_id=get_post_meta($course,'vibe_group',true);

					if(isset($group_id) && function_exists('groups_remove_member'))
						groups_remove_member($user_id,$group_id);
					else
						$group_id = '';

					$instructors = apply_filters('wplms_course_instructors',get_post_field('post_author',$course,'raw'),$course);
					if(is_array($instructors)){
						foreach($instructors as $instructor){
							woocommerce_update_order_item_meta( $item_id, 'commission'.$instructor,0);//Nulls the commission
						}
					}
				    	do_action('wplms_course_unsubscribe',$course_id,$user_id,$group_id);
					}
				}
			} 
	}



	function bp_course_instructor_member_types(){
		?>
			<li id="members-instructors"><a href="#"><?php printf( __( 'All Instructors <span>%s</span>', 'vibe' ), bp_get_total_instructor_count() ); ?></a></li>
		<?php
	}


	function wplms_custom_unit_header($unit_id,$course_id){
		if(get_post_type($unit_id) == 'quiz'){
			in_quiz_timer(array('quiz_id'=>$unit_id));
		}else{
			if(get_post_type($unit_id) == 'unit'){
				the_unit_tags($unit_id);
				$id = $unit_id;
			}else{
				$id = $course_id;
			}
	        the_unit_instructor($id);  
		}
	}
	function bp_course_admin_search_course_students($students,$course_id){

		$course_statuses = apply_filters('wplms_course_status_filters',array(
			0 => __('Expired Course','vibe'),
			1 => __('Start Course','vibe'),
			2 => __('Continue Course','vibe'),
			3 => __('Under Evaluation','vibe'),
			4 => __('Course Finished','vibe')
			));
		echo '<form method="get" action="">
				<input type="hidden" value="admin" name="action">
				'.(isset($_GET['item_page'])?'<input type="hidden" name="item_page" value="'.$_GET['item_page'].'">':'').'
				<select name="status"><option value="">'.__('Filter by Status','vibe').'</option>';
				foreach($course_statuses as $key =>$value){
					echo '<option value="'.$key.'" '.selected($_GET['status'],$key).'>'.$value.'</option>';
				}
		echo  '</select>';
		do_action('wplms_course_admin_form',$students,$course_id);
		echo '<input type="text" name="search" value="'.$_GET['search'].'" placeholder="'.__('Enter student name/email','vibe').'" class="input" />
				<input type="submit" value="'.__('Search','vibe').'" />
			 </form>';
	    if(isset($_GET['search']) && $_GET['search']){
	    	$args = array(
				'search'         => $_GET['search'],
				'search_columns' => array( 'login', 'email','nicename'),
				'fields' => array('ID'),
				'meta_query' => array(
					array(
						'key' => $course_id,
						'compare' => 'EXISTS'
						)
					),
			);
	    	$user_query = new WP_User_Query( $args );
	    	$users = $user_query->get_results();

			if(count($users)){
				$students=array();
				foreach($users as $user){
					if(is_object($user) && isset($user->ID))
						$students[]=$user->ID;
				}
			}
	    }
		return $students;
	}

	
	function wplms_show_new_course_student_status($credits,$course_id){

	  if(is_user_logged_in() && !is_singular('course')){
	    $user_id=get_current_user_id();
	    $check=get_user_meta($user_id,$course_id,true);
	    if(isset($check) && $check){
	      if($check < time()){
	        return '<strong>'.__('EXPIRED','vibe').'<span class="subs">'.__('COURSE','vibe').'</span></strong>';
	      }

	      $check_course= bp_course_get_user_course_status($user_id,$course_id);
	      $new_check_course = get_user_meta($user_id,'course_status'.$course_id,true);
	      if(isset($new_check_course) && is_numeric($new_check_course) && $new_check_course){
	  	      switch($check_course){
		        case 1:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.__('START','vibe').'<span class="subs">'.__('COURSE','vibe').'</span></strong></a>';
		        break;
		        case 2:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.__('CONTINUE','vibe').'<span class="subs">'.__('COURSE','vibe').'</span></strong></a>';
		        break;
		        case 3:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.__('UNDER','vibe').'<span class="subs">'.__('EVALUATION','vibe').'</span></strong></a>';
		        break;
		        case 4:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.__('FINISHED','vibe').'<span class="subs">'.__('COURSE','vibe').'</span></strong></a>';
		        break;
		        default:
		        $credits =apply_filters('wplms_course_status_display','<a href="'.get_permalink($course_id).'"><strong>'.__('COURSE','vibe').'<span class="subs">'.__('ENABLED','vibe').'</span></strong></a>',$course_id);
		        break;
		      }
	      }else{
	      		switch($check_course){
		        case 0:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.__('START','vibe').'<span class="subs">'.__('COURSE','vibe').'</span></strong></a>';
		        break;
		        case 1:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.__('CONTINUE','vibe').'<span class="subs">'.__('COURSE','vibe').'</span></strong></a>';
		        break;
		        case 2:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.__('UNDER','vibe').'<span class="subs">'.__('EVALUATION','vibe').'</span></strong></a>';
		        break;
		        default:
		        $credits ='<a href="'.get_permalink($course_id).'"><strong>'.__('FINISHED','vibe').'<span class="subs">'.__('COURSE','vibe').'</span></strong></a>';
		        break;
		      }	
	      }
	    }
	  }

	  return $credits;
	}


	function wplms_before_start_course_status(){
	  $user_id = get_current_user_id();  
	  
	  if ( isset($_POST['start_course']) && wp_verify_nonce($_POST['start_course'],'start_course'.$user_id) ){
	      $course_id=$_POST['course_id'];
	      $coursetaken=1;
	      $cflag=0;
	      $precourse=get_post_meta($course_id,'vibe_pre_course',true);

	      if(!empty($precourse)){
	      		$pre_course_check_status = apply_filters('wplms_pre_course_check_status_filter',2);
	          	if(is_numeric($precourse)){
	          		$preid=bp_course_get_user_course_status($user_id,$precourse);
		          	if(!empty($preid) && $preid >  $pre_course_check_status){ 
			            // COURSE STATUSES : Since version 1.8.4
			            // 1 : START COURSE
			            // 2 : CONTINUE COURSE
			            // 3 : FINISH COURSE : COURSE UNDER EVALUATION
			            // 4 : COURSE EVALUATED
			              $cflag=1;
			          }
	          	}else if(is_array($precourse)){
		          	foreach($precourse as $pc){
		          		$preid=bp_course_get_user_course_status($user_id,$pc);
			          	if(!empty($preid) && $preid > $pre_course_check_status){ 
				              $cflag=1;
				        }else{
				        	//Break from loop
				        	break;
				        }
		          	}
	          	}
	      }else{
	          $cflag=1;
	      }

	      if($cflag){
	          
	          $course_duration_parameter = apply_filters('vibe_course_duration_parameter',86400,$course_id);
	          $expire=time()+$course_duration_parameter; // One Unit logged in Limit for the course
	          setcookie('course',$course_id,$expire,'/');
	          bp_course_update_user_course_status($user_id,$course_id,1);//Since version 1.8.4
	          do_action('wplms_start_course',$course_id,$user_id);
	      }else{
	          
	          header('Location: ' . get_permalink($course_id) . '?error=precourse');
	          
	      }

	    

	  }else if ( isset($_POST['continue_course']) && wp_verify_nonce($_POST['continue_course'],'continue_course'.$user_id) ){
	    $course_id=$_POST['course_id'];
	    $coursetaken=get_user_meta($user_id,$course_id,true);
	      setcookie('course',$course_id,$expire,'/');
	  }else{
	    if(isset($_COOKIE['course'])){
	      $course_id=$_COOKIE['course'];
	      $coursetaken=1;
	    }else
	      wp_die( __('This Course can not be taken. Contact Administrator.','vibe'), 'Contact Admin', array(500,true) );
	  }

	}



	function add_course_review_button($user_id,$course_id){
		global $wpdb;
		$check = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID=%d AND user_id=%d",$course_id,$user_id));
		if(empty($check))
		echo '<form action="'.get_permalink($course_id).'" method="post">
	 	<button type="submit" style="float: right;padding: 5px 7px 0 7px;border:none;background:none;" name="review_course" class="review_course tip" title="'.__('REVIEW COURSE ','vibe').'"/><i class="icon-comment tip" style="color:#666;font-size: 24px;"></i></button>'.wp_nonce_field($course_id,'review').'</form>';
	}


	function bp_course_schema(){
		global $post;
		$key = 'course_microdata'.$post->ID;

		if(!isset($_GET['clear'])){
			$meta = get_transient( $key );
		}

		if(empty($meta) || false === $meta){
			ob_start();
			?> 
			<div itemscope itemtype="http://schema.org/Product">
		    	<meta itemprop="brand" content="<?php echo get_bloginfo( 'name', 'display' ); ?>" /> 
				<meta itemprop="name" content="<?php echo $post->post_title; ?>"/> 
				<meta itemprop="description" content="<?php echo strip_tags(get_the_excerpt()); ?>"/> 
				<?php $url = wp_get_attachment_url( get_post_thumbnail_id($post->ID,'full') );?>
				<meta itemprop="image" content="<?php echo $url; ?>"/> 
				<?php
					$rating = get_post_meta(get_the_ID(),'average_rating',true);
					$count = get_post_meta(get_the_ID(),'rating_count',true);
					if(!empty($rating)){
				?>
				<span itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating"> 
					<meta itemprop="ratingValue" content="<?php echo $rating; ?>"/> <meta itemprop="reviewCount" content="<?php echo $count; ?>"/> 
				</span>
				<?php
					}
				if(function_exists('get_woocommerce_currency')){
					$product_id = get_post_meta(get_the_ID(),'vibe_product',true);
					if(!empty($product_id)){
						$sale = get_post_meta( $product_id, '_sale_price', true);
						$price = get_post_meta( $product_id, '_regular_price', true);
						$currency = get_woocommerce_currency();
					?>
					<span itemprop="offers" itemscope itemtype="http://schema.org/Offer">
						<meta itemprop="price" content="<?php echo (empty($sale)?$price:$sale); ?>" />
						<meta itemprop="priceCurrency" content="<?php echo (empty($currency)?'USD':$currency); ?>" />
					</span>
					<?php
					}
				}
				?>
			</div>
			<?php
			$meta = ob_get_clean();
			set_transient( $key , $meta, 24 * HOUR_IN_SECONDS );
		}
		echo $meta;
	}



	function bp_course_get_course_submissions($course_id){
		?>
			<div class="submissions_form">
				<select id="fetch_course_status">
					<option value="3"><?php echo _x('Pending evaluation','Course status','vibe') ?></option>
					<option value="4"><?php echo _x('Evaluation complete','Course status','vibe') ?></option>
				</select>
				<?php wp_nonce_field('pending_course_submissions','pending_course_submissions'); ?>
				<a id="fetch_course_submissions" class="button"><?php echo _x('Get','get quiz submissions button','vibe'); ?></a>
			</div>
			<script>
				jQuery(document).ready(function($){
					$('#fetch_course_submissions').on('click',function(){
						var parent = $(this).parent();
						$('.course_students').remove();
						$('.message').remove();
						$.ajax({
	                      	type: "POST",
	                      	url: ajaxurl,
	                      	data: { action: 'fetch_course_submissions', 
	                              	security: $('#pending_course_submissions').val(),
	                              	course_id:<?php echo $course_id; ?>,
	                              	status:$('#fetch_course_status').val(),
	                            	},
	                      	cache: false,
	                      	success: function (html) {
	                      		parent.after(html);
	                      		$('#course').trigger('loaded');
	                      	}
	                    });
					});
				});
			</script>
		<?php		
	}

	function bp_course_get_course_quiz_submissions($course_id){

		$quizes = bp_course_get_curriculum_quizes($course_id);
		$quiz_ids = implode(',',$quizes);
		global $wpdb;
		$count_array = array();
  		$submissions = $wpdb->get_results($wpdb->prepare("SELECT count(*) as count,u.meta_key as quiz_id FROM {$wpdb->postmeta} as p LEFT JOIN {$wpdb->usermeta} as u ON p.meta_key = u.user_id WHERE p.meta_value LIKE '0' AND u.meta_key = p.post_id AND u.meta_value < %d AND p.post_id IN ($quiz_ids) GROUP BY u.meta_key",time()));
  		if(!empty($submissions)){
  			foreach($submissions as $submission){
	  			$count_array[$submission->quiz_id]=$submission->count;
	  		}	
  		}
  		
  		
		if(!empty($quizes)){
			?>
			<div class="submissions_form">
				<select id="fetch_quiz">
				<?php
				foreach($quizes as $quiz_id){
					?>
					<option value="<?php echo $quiz_id; ?>"><?php echo get_the_title($quiz_id); ?> (<?php echo (empty($count_array[$quiz_id])?0:$count_array[$quiz_id]);?>)</option>
					<?php	
				}
				?>
				</select>
				<select id="fetch_status">
					<option value="0"><?php echo _x('Pending evaluation','Quiz status','vibe') ?></option>
					<option value="1"><?php echo _x('Evaluation complete','Quiz status','vibe') ?></option>
				</select>
				<?php wp_nonce_field('quiz_submissions','quiz_submissions'); ?>
				<a id="fetch_quiz_submissions" class="button"><?php echo _x('Get','get quiz submissions button','vibe'); ?></a>
			</div>
			<script>
				jQuery(document).ready(function($){
					$('#fetch_quiz_submissions').on('click',function(){
						var parent = $(this).parent();
						$('.quiz_students').remove();
						$('.message').remove();
						$.ajax({
	                      	type: "POST",
	                      	url: ajaxurl,
	                      	data: { action: 'fetch_quiz_submissions', 
	                              	security: $('#quiz_submissions').val(),
	                              	quiz_id:$('#fetch_quiz').val(),
	                              	status:$('#fetch_status').val(),
	                            	},
	                      	cache: false,
	                      	success: function (html) {
	                      		parent.after(html);
	                      		$('#quiz').trigger('loaded');
	                      	}
	                    });
					});
				});
			</script>
			<?php
		}else{
			?>
			<div class="message">
				<p><?php echo _x('No Quiz found !','No quizzes in course, error on course submissions','vibe'); ?></p>
			</div>
			<?php
		}
	}

	function get_course_applications($course_id){
		global $wpdb;

		$users = $wpdb->get_results("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'apply_course' AND meta_value = $course_id");

		if(count($users)){
			echo '<ul>';
			foreach($users as $user){
            ?>
				<li class="user clear" data-id="<?php echo $user->user_id; ?>" data-course="<?php echo $course_id; ?>" data-security="<?php echo wp_create_nonce('security'.$course_id.$user->user_id); ?>">
					<?php echo get_avatar($user->user_id).bp_core_get_userlink( $user->user_id );?>
					<span class="reject"><?php echo _x('Reject','reject user application for course','vibe'); ?></span>
					<span class="approve"><?php echo _x('Approve','approve user application for course','vibe'); ?></span>
				</li>
            <?php
        	}
        	echo '</ul>';
        }else{
            ?>
            <div class="message">
                <p><?php echo _x('No applications found !','No applications found in course, error on course submissions','vibe'); ?></p>
            </div>
            <?php
        }
	}
}


BP_Course_Action::init();


function bp_course_get_nav_permalinks(){
	$action = BP_Course_Action::init();
	return $action->nav;
}