<?php
// main model containing general config and UI functions
class NamasteLMS {
   static function install() {
   	global $wpdb;	
   	$wpdb -> show_errors();
   	
   	self::init();
	  
	  // enrollments to courses
   	if($wpdb->get_var("SHOW TABLES LIKE '".NAMASTE_STUDENT_COURSES."'") != NAMASTE_STUDENT_COURSES) {        
			$sql = "CREATE TABLE `" . NAMASTE_STUDENT_COURSES . "` (
				  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`course_id` INT UNSIGNED NOT NULL DEFAULT 0,
					`user_id` INT UNSIGNED NOT NULL DEFAULT 0,
					`status` VARCHAR(255) NOT NULL DEFAULT '',
					`enrollment_date` DATE NOT NULL DEFAULT '2000-01-01',			
					`completion_date` DATE NOT NULL DEFAULT '2000-01-01',
					`comments` TEXT NOT NULL
				) DEFAULT CHARSET=utf8;";
			
			$wpdb->query($sql);
	  }
	  
	  // assignments - let's not use custom post type for this
	  if($wpdb->get_var("SHOW TABLES LIKE '".NAMASTE_HOMEWORKS."'") != NAMASTE_HOMEWORKS) {        
			$sql = "CREATE TABLE `" . NAMASTE_HOMEWORKS . "` (
				  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`course_id` INT UNSIGNED NOT NULL DEFAULT 0,
					`lesson_id` INT UNSIGNED NOT NULL DEFAULT 0,
					`title` VARCHAR(255) NOT NULL DEFAULT '',
					`description` TEXT NOT NULL,
					`accept_files` TINYINT NOT NULL DEFAULT 0 /* zip only */
				) DEFAULT CHARSET=utf8;";
			
			$wpdb->query($sql);
	  }
	  
	  // student - assignments relation
		if($wpdb->get_var("SHOW TABLES LIKE '".NAMASTE_STUDENT_HOMEWORKS."'") != NAMASTE_STUDENT_HOMEWORKS) {        
			$sql = "CREATE TABLE `" . NAMASTE_STUDENT_HOMEWORKS . "` (
				  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`homework_id` INT UNSIGNED NOT NULL DEFAULT 0,
					`student_id` INT UNSIGNED NOT NULL DEFAULT 0,
					`status` VARCHAR(255) NOT NULL DEFAULT '',
					`date_submitted` DATE NOT NULL DEFAULT '2000-01-01',
					`content` TEXT NOT NULL,
					`file` VARCHAR(255) NOT NULL DEFAULT ''
				) DEFAULT CHARSET=utf8;";
			
			$wpdb->query($sql);
	  }
			  
	  // assignment notes (usually used as feedback from the teacher to the student. Student can't reply)
		if($wpdb->get_var("SHOW TABLES LIKE '".NAMASTE_HOMEWORK_NOTES."'") != NAMASTE_HOMEWORK_NOTES) {        
			$sql = "CREATE TABLE `" . NAMASTE_HOMEWORK_NOTES . "` (
				  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`homework_id` INT UNSIGNED NOT NULL DEFAULT 0,
					`student_id` INT UNSIGNED NOT NULL DEFAULT 0,
					`teacher_id` INT UNSIGNED NOT NULL DEFAULT 0,
					`note` TEXT NOT NULL,
					`datetime` DATETIME NOT NULL DEFAULT '2000-01-01'
				) DEFAULT CHARSET=utf8;";
			
			$wpdb->query($sql);
	  }  
	  
	  // student to lessons relation - only save record if student has completed a lesson
		if($wpdb->get_var("SHOW TABLES LIKE '".NAMASTE_STUDENT_LESSONS."'") != NAMASTE_STUDENT_LESSONS) {        
			$sql = "CREATE TABLE `" . NAMASTE_STUDENT_LESSONS . "` (
				  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
					`lesson_id` INT UNSIGNED NOT NULL DEFAULT 0,
					`student_id` INT UNSIGNED NOT NUL DEFAULT 0L,
					`status` INT UNSIGNED NOT NULL DEFAULT 0,
					`completion_date` TEXT NOT NULL
				) DEFAULT CHARSET=utf8;";
			
			$wpdb->query($sql);
	  }  
	  
	  // add student role if not exists
    $res = add_role('student', 'Student', array(
          'read' => true, // True allows that capability
          'namaste' => true));   
    if(!$res) {
    	// role already exists, check the capability
    	$role = get_role('student');
    	if(!$role->has_cap('namaste')) $role->add_cap('namaste');
    }          	
    
    // add manage cap to the admin / superadmin by default
    $role = get_role('administrator');
    if(!$role->has_cap('namaste_manage')) $role->add_cap('namaste_manage');
    
    // fush rewrite rules
    NamasteLMSCourseModel::register_course_type();
    NamasteLMSLessonModel::register_lesson_type();
    flush_rewrite_rules();
    
	  update_option( 'namaste_version', "0.3.3");	  
   }
   
   // main menu
   static function menu() {
		$namaste_cap = current_user_can('namaste_manage')?'namaste_manage':'namaste';   	
   	
   	$menu=add_menu_page(__('Namaste! LMS', 'namaste'), __('Namaste! LMS', 'namaste'), "namaste_manage", "namaste_options", 
   		array(__CLASS__, "options"));
   		add_submenu_page('namaste_options', __("Assignments", 'namaste'), __("Assignments", 'namaste'), 'namaste_manage', 'namaste_homeworks', array('NamasteLMSHomeworkModel', "manage"));
   		add_submenu_page('namaste_options', __("Students", 'namaste'), __("Students", 'namaste'), 'namaste_manage', 'namaste_students', array('NamasteLMSStudentModel', "manage"));
   		add_submenu_page('namaste_options', __("Namaste! Settings", 'namaste'), __("Settings", 'namaste'), 'namaste_manage', 'namaste_options', array(__CLASS__, "options"));    
   		
   		// not visible in menu
   		add_submenu_page( NULL, __("Student Lessons", 'namaste'), __("Student Lessons", 'namaste'), $namaste_cap, 'namaste_student_lessons', array('NamasteLMSLessonModel', "student_lessons"));
   		add_submenu_page( NULL, __("Homeworks", 'namaste'), __("Homeworks", 'namaste'), $namaste_cap, 'namaste_lesson_homeworks', array('NamasteLMSHomeworkModel', "lesson_homeworks"));
   		add_submenu_page( NULL, __("Send note", 'namaste'), __("Send note", 'namaste'), 'namaste_manage', 'namaste_add_note', array('NamasteLMSNoteModel', "add_note"));
   		add_submenu_page( NULL, __("Submit solution", 'namaste'), __("Submit solution", 'namaste'), $namaste_cap, 'namaste_submit_solution', array('NamasteLMSHomeworkController', "submit_solution"));
   		add_submenu_page( NULL, __("View solutions", 'namaste'), __("View solutions", 'namaste'), $namaste_cap, 'namaste_view_solutions', array('NamasteLMSHomeworkController', "view"));
   		
   		// student menu
   		$menu=add_menu_page(__('My Courses', 'namaste'), __('My Courses', 'namaste'), "namaste", "namaste_my_courses", array('NamasteLMSCoursesController', "my_courses"));
	}
	
	// CSS and JS
	static function scripts() {
		// CSS
		wp_register_style( 'namaste-css', NAMASTE_URL.'css/main.css?v=1');
	  wp_enqueue_style( 'namaste-css' );
   
   	wp_enqueue_script('jquery');
	   
	   // Namaste's own Javascript
		wp_register_script(
				'namaste-common',
				NAMASTE_URL.'js/common.js',
				false,
				'0.1.0',
				false
		);
		wp_enqueue_script("namaste-common");
		
		// jQuery Validator
		wp_enqueue_script(
				'jquery-validator',
				'http://ajax.aspnetcdn.com/ajax/jquery.validate/1.9/jquery.validate.min.js',
				false,
				'0.1.0',
				false
		);
	}
	
	// initialization
	static function init() {
		global $wpdb;
		load_plugin_textdomain( 'namaste', false, NAMASTE_RELATIVE_PATH."/languages/" );
		if (!session_id()) @session_start();
		
		// define table names 
		define( 'NAMASTE_STUDENT_COURSES', $wpdb->prefix. "namaste_student_courses");
		define( 'NAMASTE_LESSON_COURSES', $wpdb->prefix. "namaste_lesson_courses");
		define( 'NAMASTE_HOMEWORKS', $wpdb->prefix. "namaste_homeworks");
		define( 'NAMASTE_STUDENT_HOMEWORKS', $wpdb->prefix. "namaste_student_homeworks");
		define( 'NAMASTE_HOMEWORK_NOTES', $wpdb->prefix. "namaste_homework_notes");
		define( 'NAMASTE_STUDENT_LESSONS', $wpdb->prefix. "namaste_student_lessons");
		
		define( 'NAMASTE_VERSION', get_option('namaste_version'));
	}
	
	// handle Namaste vars in the request
	static function query_vars($vars) {
		$new_vars = array();
		$vars = array_merge($new_vars, $vars);
	   return $vars;
	} 	
		
	// parse Namaste vars in the request
	static function template_redirect() {		
		global $wp, $wp_query, $wpdb;
		$redirect = false;		
		 
	  if($redirect) {
	   	if(@file_exists(TEMPLATEPATH."/".$template)) include TEMPLATEPATH."/namaste/".$template;		
			else include(NAMASTE_PATH."/views/templates/".$template);
			exit;
	  }	   
	}	
			
	// manage general options
	static function options() {		
		if(!empty($_POST['namaste_options']) and check_admin_referer('save_options', 'nonce_options')) {
			$roles = get_option('wp_user_roles');
			
			foreach($roles as $key=>$r) {
				if($key == 'administrator') continue;
				
				$role = get_role($key);

				// use Namaste!
				if(in_array($key, $_POST['use_roles'])) {					
    			if(!$role->has_cap('namaste')) $role->add_cap('namaste');
				}
				else $role->remove_cap('namaste');
				
				// manage Namaste!
				if(@in_array($key, $_POST['manage_roles'])) {					
    			if(!$role->has_cap('namaste_manage')) $role->add_cap('namaste_manage');
				}
				else $role->remove_cap('namaste_manage');
			} 
		}
		
		if(!empty($_POST['namaste_exam_options']) and check_admin_referer('save_exam_options', 'nonce_exam_options')) {
				update_option('namaste_use_exams', $_POST['use_exams']);
		}
		
		// select all roles in the system
		$roles = get_option('wp_user_roles');
		
		// what exams to use
		$use_exams = get_option('namaste_use_exams');
		
		// see if watu/watuPRO are available and activate
		$current_plugins = get_option('active_plugins');
		$watu_active = $watupro_active = false;
		if(in_array('watu/watu.php', $current_plugins)) $watu_active = true;
		if(in_array('watupro/watupro.php', $current_plugins)) $watupro_active = true;
			
		require(NAMASTE_PATH."/views/options.php");
	}	
	
	static function help() {
		require(NAMASTE_PATH."/views/help.php");
	}	
	
	static function register_widgets() {
		// register_widget('NamasteWidget');
	}
}