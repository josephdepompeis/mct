<?php


 if ( ! defined( 'ABSPATH' ) ) exit;
 
class bp_course_mails{

   var $settings;
   var $subject;
   var $user_email;
    public static $instance;
    
    public static function init(){

        if ( is_null( self::$instance ) )
            self::$instance = new bp_course_mails();
        return self::$instance;
    }

    private function __construct(){
      $settings = get_option('lms_settings');
      
      if(isset($settings) && isset($settings['activate'])){
        $this->activate = $settings['activate'];
      }

      if(isset($settings) && isset($settings['activate'])){
        $this->forgot = $settings['forgot'];
      }
      if($settings['email_settings']['enable_html_emails'] == 'on' || $settings['email_settings']['enable_html_emails'] === 'on'){
         $this->html_emails = 1;  
      }else{
         $this->html_emails = 0; 
      }
      

      add_filter('bp_core_signup_send_validation_email_to',array($this,'user_mail'));

      add_filter('bp_core_signup_send_validation_email_subject',array($this,'bp_course_activation_mail_subject'));    
      add_filter('bp_core_signup_send_validation_email_message',array($this,'bp_course_activation_mail_message'),10,3);

      add_filter ( 'retrieve_password_title', array($this,'forgot_password_subject'), 10, 1 );
      add_filter ( 'retrieve_password_message', array($this,'forgot_password_message'), 10, 2 );

      add_filter('messages_notification_new_message_message',array($this,'bp_course_bp_mail_filter'),10,7);
      add_filter( 'wp_mail_content_type', array($this,'set_html_content_type' ));

      //DISABLE BuddyPress Emails
      add_filter( 'bp_email_use_wp_mail', '__return_true' );
   }

    function enable_html(){
      return $this->html_emails;
    }
    function bp_course_bp_mail_filter($email_content, $sender_name, $subject, $content, $message_link, $settings_link, $ud){
       $settings = get_option('lms_settings');
      if(!empty($this->html_emails)){
        $email_content = bp_course_process_mail(bp_core_get_user_displayname($ud->ID),$subject,$email_content); 
      }

      return $email_content;
    }
    
    function set_html_content_type($type) {
      if(!empty($this->html_emails))
        return 'text/html';

      return $type;
    }

   function user_mail($email){
      $this->activate_user_email = $email;
      return $email;
   }

   function bp_course_activation_mail_subject($subject){
    $this->activate_subject = $subject;

    if(isset($this->activate) && is_array($this->activate) && isset($this->activate['subject'])){
      $this->activate_subject = $this->activate['subject'];
    }
    return $subject;
  }
  
  function bp_course_activation_mail_message($message,$user_id,$link){

    if(isset($this->activate) && is_array($this->activate) && isset($this->activate['message'])){
      $message = $this->activate['message'];
      if(strpos($message,'{{activationlink}}') === false){
        $message .= $message.' '.sprintf(__('Click %s to Activate account.','vibe'),'<a href="'.$link.'">'.__('this link','vibe').'</a>'); 
      }else{
        $message = str_replace('{{activationlink}}',$link,$message);
      }
      if(!empty($this->html_emails))
        $message = bp_course_process_mail($this->activate_user_email,$this->activate_subject,$message);
    }    

    return $message;
  }

  function forgot_password_subject($subject){

    if(isset($this->forgot) && is_array($this->forgot) && !empty($this->forgot['subject'])){
      $subject = $this->forgot['subject'];
    }
    return $subject;
  }

  function forgot_password_message($old_message, $key){

    if(isset($this->forgot) && is_array($this->forgot) && !empty($this->forgot['message'])){
      $message = $this->forgot['message'];
    }else{
      $message = $old_message;
    }

    if ( strpos( $_POST['user_login'], '@' ) ){
        $user_data = get_user_by( 'email', trim( $_POST['user_login'] ) );
    }else{
        $login = trim($_POST['user_login']);
        $user_data = get_user_by('login', $login);
    }

    $user_login = $user_data->user_login;

    $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');

    $message = str_replace("{{forgotlink}}",$reset_url,(str_replace("{{username}}",$user_login,$message))); //. "\r\n";

    if(!empty($this->html_emails)){
      $message = bp_course_process_mail($user_data->user_email,$this->forgot['subject'],$message);
    }
        
    return $message;
  }
}

bp_course_mails::init();



// BP Course Mail function

function bp_course_wp_mail($to,$subject,$message,$args=''){

/*=== BuddyPRess HTML emails do not Work properly, waiting for future BuddyPress version to fix this then we can import WPLMS Emails.

  if(isset($args['student_course_announcement'])){
      //add tokens to parse in email
        $bpargs = array(
            'tokens' => array(
                'site.name' => get_bloginfo( 'name' ),
                'course.name'=>get_the_title($args['item_id']),
                'course.announcement' => $message,
            ),
        );
        
        // send args and user ID to receive email
    bp_send_email( 'student_course_announcement',$to, $bpargs );
 
    return;
  }
*/

  if(!count($to))
    return;
  
    $headers = "MIME-Version: 1.0" . "\r\n";
     $settings = get_option('lms_settings');
    if(isset($settings['email_settings']) && is_array($settings['email_settings'])){
        if(isset($settings['email_settings']['from_name'])){
          $name = $settings['email_settings']['from_name'];
        }else{
          $name =get_bloginfo('name');
        }
        if(isset($settings['email_settings']['from_email'])){
          $email = $settings['email_settings']['from_email'];
        }else{
          $email = get_option('admin_email');
        }
        if(isset($settings['email_settings']['charset'])){
          $charset = $settings['email_settings']['charset'];
        }else{
           $charset = 'utf8'; 
        }
    }
    $headers .= "From: $name<$email>". "\r\n";
    $mails = bp_course_mails::init();
    if($mails->enable_html())
      $headers .= "Content-type: text/html; charset=$charset" . "\r\n";
    
    $flag = apply_filters('bp_course_disable_html_emails',1);
    
    if($flag){
      if($mails->enable_html()){
        if(is_array($to)){
          $message = bp_course_process_mail($to,$subject,$message,$args);
          $message = apply_filters('wplms_email_templates',$message,$to,$subject,$message,$args);
          foreach($to as $t){
            $message = str_replace('{{name}}',$t,$message);  
            if(!empty($message))
              wp_mail($t,$subject,$message,$headers);    
          }
        }
        
      }
    }else{
      $message = apply_filters('wplms_email_templates',$message,$to,$subject,$message,$args);
      if(!empty($message))
        wp_mail($to,$subject,$message,$headers);
    }
}

// BP Course Mail function to be extended in future

function bp_course_process_mail($to,$subject,$message,$args=''){


  

    $template = html_entity_decode(get_option('wplms_email_template'));
    if(!isset($template) || !$template || strlen($template) < 5)
      return $message;
     

    $site_title = get_option('blogname');
    $site_description = get_option('blogdescription');
    $logo_url = vibe_get_option('logo');
    $logo = '<a href="'.get_option('home_url').'"><img src="'.$logo_url.'" alt="'.$site_title.'" style="max-width:50%;"/></a>';

    $sub_title = $subject; 

    if(is_array($to)){
      $name .= implode($to);
    }else{
      $name = $to;  
    }
    
    if(!is_array($to)){
      $user = get_user_by('email',$to);
      $name = bp_core_get_userlink($user->id);
      if(empty($name))
        $name = $user->first_name;
    }

    $datetime = date_i18n( get_option( 'date_format' ), time());
    if(isset($args['item_id'])){
      $instructor_id = get_post_field('post_author', $args['item_id']);
      $sender = bp_core_get_user_displayname($instructor_id);
      $instructing_courses=apply_filters('wplms_instructing_courses_endpoint','instructing-courses');
      $sender_links = apply_filters('wplms_emails_sender_links','<a href="'.bp_core_get_user_domain( $instructor_id ).'">'.__('Profile','vibe-customtypes').'</a>&nbsp;|&nbsp;<a href="'.get_author_posts_url($instructor_id).$instructing_courses.'/">'.__('Courses','vibe-customtypes').'</a>');
      $item = get_the_title($args['item_id']);
      $item_links  = apply_filters('wplms_emails_item_links','<a href="'.get_permalink( $args['item_id'] ).'">'.__('Link','vibe-customtypes').'</a>&nbsp;|&nbsp;<a href="'.bp_core_get_user_domain($instructor_id).'/">'.__('Instructor','vibe-customtypes').'</a>');
      $unsubscribe_link = bp_core_get_user_domain($user_id).'/settings/notifications';
    }else{
      $sender ='';
      $sender_links ='';
      $item ='';
      $item_links ='';
      $unsubscribe_link = '#';
      $template = str_replace('cellpadding="28"','cellpadding="0"',$template);
    }
   
    $copyright = vibe_get_option('copyright');
    $link_id = vibe_get_option('email_page');
    if(is_numeric($link_id)){
      $array = array(
        'to' => $to,
        'subject'=>$subject,
        'message'=>$message,
        'args'=>$args
        );
      $link = get_permalink($link_id).'?vars='.urlencode(json_encode($array));
    }else{
      $link = '#';
    }


    $template = str_replace('{{logo}}',$logo,$template);
    $template = str_replace('{{subject}}',$subject,$template);
    $template = str_replace('{{sub-title}}',$sub_title,$template);
    //$template = str_replace('{{name}}',$name,$template);
    $template = str_replace('{{datetime}}',$datetime,$template);
    $template = str_replace('{{message}}',$message,$template);
    $template = str_replace('{{sender}}',$sender,$template);
    $template = str_replace('{{sender_links}}',$sender_links,$template);
    $template = str_replace('{{item}}',$item,$template);
    $template = str_replace('{{item_links}}',$item_links,$template);
    $template = str_replace('{{site_title}}',$site_title,$template);
    $template = str_replace('{{site_description}}',$site_description,$template);
    $template = str_replace('{{copyright}}',$copyright,$template);
    $template = str_replace('{{unsubscribe_link}}',$unsubscribe_link,$template);
    $template = str_replace('{{link}}',$link,$template);
    $template = bp_course_minify_output($template);
    return $template;
}

function bp_course_minify_output($buffer){
  $search = array(
  '/\>[^\S ]+/s',
  '/[^\S ]+\</s',
  '/(\s)+/s'
  );
  $replace = array(
  '>',
  '<',
  '\\1'
  );
  if (preg_match("/\<html/i",$buffer) == 1 && preg_match("/\<\/html\>/i",$buffer) == 1) {
    $buffer = preg_replace($search, $replace, $buffer);
  }
  return $buffer;
}

function send_html( $message,    $user_id, $activate_url ) {
  if(bp_course_mails::enable_html())
    $message = bp_course_process_mail($to,$subject,$message,$args); 

  return $message;
}

/*=== BUDDYPRESS EMAILS DO NOT WORK ==== REMOVING INTEGRATION === 

function bp_course_email_tokens($args){
    switch($case){
        case 'course.name':
        return get_the_title($item_id);
        break;
        case 'course.titlelink':
          return '<a href="'.get_permalink($item_id).'">'.get_the_title($item_id).'</a>';
        break;
        case 'student.userlink':
          return bp_core_get_userlink($user_id);
        break;
        case 'course.code':
          return $code;
        break;
        case 'unit.title':
          return '<a href="'.get_permalink($secondary_item_id).'">'.get_the_title($secondary_item_id).'</a>';
        break;
        case 'unit.titlelink':
          return '<a href="'.get_permalink($secondary_item_id).'">'.get_the_title($secondary_item_id).'</a>';
        break;
        case 'course.instructorlink':
          return bp_core_get_userlink($instructor_id);
        break;
    }
}

function bp_course_all_mails(){
    $bp_course_mails = array(
        'student_course_announcement'=>array(
            'description'=> __('Student : Announcement in Course','vibe'),
            'subject' =>  sprintf(__('Announcement for Course %s','vibe'),'{{course.name}}'),
            'message' =>  '{{course.announcement}}'
        ),
        'instructor_course_announcement'=>array(
            'description'=> __('Instructor : Announcement in Course','vibe'),
            'subject' =>  sprintf(__('Announcement for Course %s','vibe'),'{{course.name}}'),
            'message' =>  '{{course.announcement}}'
        ),
        'student_course_news'=>array(
            'description'=> __('Student : News in Course','vibe'),
            'subject' =>  sprintf(__('News for Course %s','vibe'),'{{course.name}}'),
            'message' =>  '{{course.news}}'
        ),
        'instructor_course_news'=>array(
            'description'=> __('Instructor : News in Course','vibe'),
            'subject' =>  sprintf(__('News for Course %s','vibe'),'{{course.name}}'),
            'message' =>  '{{course.news}}',
        ),

        'student_course_subscribed'=>array(
            'description'=> __('Student : Student subscribes to course','vibe'),
            'subject' =>  sprintf(__('Subscribed for Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'re subscribed for course : %s','vibe'),'{{course.titlelink}}')
        ),
        'instructor_course_subscribed'=>array(
            'description'=> __('Instructor : Student subscribes to course','vibe'),
            'subject' =>  sprintf(__('Student subscribed for course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s subscribed for course : %s','vibe'),'{{student.userlink}}','{{course.titlelink}}')
        ),

        'student_course_added'=>array(
            'description'=> __('Student : Instructor adds Student to course','vibe'),
            'subject' =>  sprintf(__('Added to course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve been added to course : %s','vibe'),'{{course.titlelink}}')
        ),
        'instructor_course_added'=>array(
            'description'=> __('Instructor : Instructor adds Student to course','vibe'),
            'subject' =>  sprintf(__('Student added to course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('%d student added to course : %s , %s','vibe'),'{{student.userlink}}','{{course.titlelink}}')
        ),

        'student_course_start'=>array(
            'description'=> __('Student : Student started a course','vibe'),
            'subject' =>  sprintf(__('You started course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve started the course : %s','vibe'),'{{course.titlelink}}')
        ),
        'instructor_course_start'=>array(
            'description'=> __('Instructor : Student started a course','vibe'),
            'subject' =>  sprintf(__('Student started course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s started the course : %s','vibe'),'{{student.userlink}}','{{course.titlelink}}')
        ),

        'student_course_submit'=>array(
            'description'=> __('Student : Student finishes a course','vibe'),
            'subject' =>  sprintf(__('Course %s submitted','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve submitted the course : %s','vibe'),'{{course.titlelink}}')
        ),
        'instructor_course_submit'=>array(
            'description'=> __('Instructor : Student finishes a course','vibe'),
            'subject' =>  sprintf(__('Student submitted course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s submitted the course : %s','vibe'),'{{student.userlink}}','{{course.titlelink}}')
        ),

        'student_course_reset'=>array(
            'description'=> __('Student : Instructor resets course for a Student','vibe'),
            'subject' =>  sprintf(__('Course %s reset','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('%s Course was reset by Instructor','vibe'),'{{course.titlelink}}')
        ),
        'instructor_course_reset'=>array(
            'description'=> __('Instructor : Instructor resets course for a Student','vibe'),
            'subject' =>  sprintf(__('Course %s reset for Student','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Course %s was reset for student %s ','vibe'),'{{course.titlelink}}','{{student.userlink}}')
        ),

        'student_course_retake'=>array(
            'description'=> __('Student : Student retakes a course','vibe'),
            'subject' =>  sprintf(__('You retook the course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(___('You\'ve retaken the Course %s','vibe'),'{{course.titlelink}}')
        ),
        'instructor_course_retake'=>array(
            'description'=> __('Instructor : Student retakes a course','vibe'),
            'subject' =>  sprintf(__('Course %s retaken by the Student','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Course %s was retaken by the student %s ','vibe'),'{{course.titlelink}}','{{student.userlink}}')
        ),

        'student_course_evaluation'=>array(
            'description'=> __('Student : Course evaluated for Student','vibe'),
            'subject' =>  sprintf(__('Course %s results available','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve obtained %s  in Course : %s','vibe'),'{{course.marks}}','{{course.titlelink}}')
        ),
        'instructor_course_evaluation'=>array(
            'description'=> __('Instructor : Course evaluated for Student','vibe'),
            'subject' =>  sprintf(__('Students added to course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('%d students added to course : %s , %s','vibe'),'{{student.userlink}}','{{course.titlelink}}')
        ),

        'student_course_badge'=>array(
            'description'=> __('Student : Student obtained course badge','vibe'),
            'subject' =>  sprintf(__('You got a Badge in Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve obtained a Badge in Course : %s','vibe'),'{{course.titlelink}}')
        ),
        'instructor_course_badge'=>array(
            'description'=> __('Instructor : Student obtained course badge','vibe'),
            'subject' =>  sprintf(__('Student got a Badge in Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s got a Badge in Course %s','vibe'),'{{student.userlink}}','{{course.titlelink}}')
        ),

        'student_course_certificate'=>array(
            'description'=> __('Student : Student obtained course certificate','vibe'),
            'subject' =>  sprintf(__('You got a Certificate in Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve obtained a certificate in Course : %s','vibe'),'{{course.titlelink}}')
        ),
        'instructor_course_certificate'=>array(
            'description'=> __('Instructor : Student obtained course certificate','vibe'),
            'subject' =>  sprintf(__('Student got a Certificate in Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s got a Certificate in Course %s','vibe'),'{{student.userlink}}','{{course.titlelink}}')
        ),

        'student_course_review'=>array(
            'description'=> __('Student : Student reviewed course','vibe'),
            'subject' =>  sprintf(__('You submitted a review for Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You submitted a review Course : %s','vibe'),'{{course.titlelink}}')
        ),
        'instructor_course_review'=>array(
            'description'=> __('Instructor : Student reviewed course','vibe'),
            'subject' =>  sprintf(__('Student submitted a review for Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s submitted a review for the Course %s','vibe'),'{{student.userlink}}','{{course.titlelink}}')
        ),

        'student_course_unsubscribe'=>array(
            'description'=> __('Student : Student unsubscribed from course','vibe'),
            'subject' =>  sprintf(__('You\'re unsubscribed from course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'re unsubscribed from the Course %s','vibe'),'{{course.titlelink}}')
        ),
        'instructor_course_unsubscribe'=>array(
            'description'=> __('Instructor : Student unsubscribed from course','vibe'),
            'subject' =>  sprintf(__('Student unsubscribed from Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s unsubscribed from Course %s','vibe'),'{{student.userlink}}','{{course.titlelink}}')
        ),

        'student_course_codes'=>array(
            'description'=> __('Student : Student applied course code to course','vibe'),
            'subject' =>  sprintf(__('You applied course code in course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('You\'ve been added to course : %s','vibe'),'{{course.titlelink}}')
        ),
        'instructor_course_codes'=>array(
            'description'=> __('Instructor : Student applied course code to course','vibe'),
            'subject' =>  sprintf(__('Student applied code for Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s applied code %s for Course %s','vibe'),'{{student.userlink}}','{{course.code}}','{{course.titlelink}}')
        ),

        'student_unit_complete'=>array(
            'description'=> __('Student : Student completed a unit in course','vibe'),
            'subject' =>  sprintf(__('You completed unit %s in Course %s','vibe'),'{{unit.name}}','{{course.name}}'),
            'message' =>  sprintf(__('You completed a unit %s in Course %s','vibe'),'{{unit.titlelink}}','{{course.titlelink}}')
        ),
        'instructor_unit_complete'=>array(
            'description'=> __('Instructor : Student completed a unit in course','vibe'),
            'subject' =>  sprintf(__('Student completed unit in Course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Student %s completed unit %s in Course %s','vibe'),'{{student.userlink}}','{{course.titlelink}}')
        ),

        'student_unit_instructor_complete'=>array(
            'description'=> __('Student : Instructor marked unit complete for Student in course','vibe'),
            'subject' =>  sprintf(__('Instructor marked unit complete in course %s','vibe'),'{{course.name}}'),
            'message' =>  sprintf(__('Unit %s was marked complete by Instructor %s in Course %s','vibe'),'{{unit.titlelink}}','{{course.instructorlink}}','{{course.titlelink}}')
        ),
        'instructor_unit_instructor_complete'=>array(
            'description'=> __('Instructor : Student completed a unit in course','vibe'),
            'subject' =>  sprintf(__('Instructor marked unit %s comple for Student in Course %s','vibe'),'{{unit.name}}','{{course.name}}'),
            'message' =>  sprintf(__('Instructor %s completed the unit %s for Student %s in Course %s','vibe'),'{{instructor.userlink}}','{{unit.titlelink}}','{{student.userlink}}','{{course.titlelink}}')
        ),

    );
    return apply_filters('bp_course_all_mails',$bp_course_mails);
}

/*===== END INTEGRATION === */