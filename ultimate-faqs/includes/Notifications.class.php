<?php

/**
 * Class to handle sending notifications when an FAQ is submitted
 */

if ( !defined( 'ABSPATH' ) )
	exit;

if ( !class_exists( 'ewdufaqNotifications' ) ) {
class ewdufaqNotifications {

	public function __construct() {
		
		// Public compatibility facade; Helper owns paid mail hooks and implementation.
	}

	/**
	 * Send an email to the site admin when an FAQ is submitted, if selected
	 *
	 * @since 2.0.0
	 */
	public function admin_notification_email( $faq )  {
		return class_exists( 'ewdufaqEWDPHSubmissionNotifications', false ) ? ewdufaqEWDPHSubmissionNotifications::admin_notification_email( $this, $faq ) : false;
	}

	/**
	 * Send an email to the FAQ author using UWPM when an FAQ is submitted, if selected
	 *
	 * @since 2.0.0
	 */
	public function user_submission_email( $faq )  {
		return class_exists( 'ewdufaqEWDPHSubmissionNotifications', false ) ? ewdufaqEWDPHSubmissionNotifications::user_submission_email( $this, $faq ) : false;
	}

	/**
	 * Send an email to the FAQ author once their question has been answered
	 *
	 * @since 2.4.0
	 */
	public function user_question_updated( $faq )  {
		return class_exists( 'ewdufaqEWDPHSubmissionNotifications', false ) ? ewdufaqEWDPHSubmissionNotifications::user_question_updated( $this, $faq ) : false;
	}
}
} // endif;

