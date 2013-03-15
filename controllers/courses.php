<?php
class NamasteLMSCoursesController {
	// displays courses of a student, lets them enroll in a course
	function my_courses() {
		global $wpdb, $user_ID;
		
		$message = '';
		if(!empty($_POST['enroll'])) {
			// enroll in course
			$course = NamasteLMSCourseModel :: select($_POST['course_id']);
			$enroll_mode = get_post_meta($course->ID, 'namaste_enroll_mode', true);
			
			// if already enrolled, just skip this altogether
			if(!NamasteLMSStudentModel :: is_enrolled($user_ID, $course->ID)) {
				// depending on mode, status will be either 'pending' or 'enrolled'
				$status = ($enroll_mode == 'free') ? 'enrolled' : 'pending';
				
				$wpdb->query($wpdb->prepare("INSERT INTO ".NAMASTE_STUDENT_COURSES." SET
					course_id = %d, user_id = %d, status = %s, enrollment_date = CURDATE(),
					completion_date='1900-01-01', comments=''",
					$course->ID, $user_ID, $status));
					
				if($enroll_mode == 'free') $message = sprintf(__('You enrolled in "%s"', 'namaste'), $course->post_title);
				else $message = __('Thank you for your interest in enrolling this course. A manager will review your application.', 'namaste');	
			}
			else $message = __('You have already enrolled in this course','namaste');
		}
		
		// select all courses join to student courses so we can have status.
		$courses = $wpdb -> get_results($wpdb->prepare("SELECT tSC.*, 
			tC.post_title as post_title, tC.ID as post_id, tC.post_excerpt as post_excerpt
			 FROM {$wpdb->posts} tC LEFT JOIN ".NAMASTE_STUDENT_COURSES." tSC ON tC.ID = tSC.course_id
			 AND tSC.user_id = %d WHERE tC.post_status = 'publish'
			 AND tC.post_type='namaste_course' ORDER BY tC.post_title", $user_ID));
			 
		require(NAMASTE_PATH."/views/my_courses.php");	 
	}
}