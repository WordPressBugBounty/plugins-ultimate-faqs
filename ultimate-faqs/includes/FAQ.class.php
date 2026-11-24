<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'ewdufaqFAQ' ) ) {
/**
 * Class to handle an FAQ for Ultimate FAQs
 *
 * @since 2.0.0
 */
class ewdufaqFAQ {

	// from  WP post
	public $post;
	public $ID;
	public $question; // post_title
	public $date; 
	public $answer; // post_content
	public $post_status;

	// post terms
	public $categories = array();
	public $tags = array();

	// post meta
	public $faq_author;
	public $faq_author_email;
	public $views;
	public $up_votes;
	public $down_votes;
	public $user_submitted;

	// custom fields
	public $custom_fields = array();

	/**
	 * Whether or not this request has been processed. Used to prevent
	 * duplicate forms on one page from processing an FAQ form more than
	 * once.
	 * @since 2.0.0
	 */
	public $faq_processed = false;

	/**
	 * Whether or not this request was successfully saved to the database.
	 * @since 2.0.0
	 */
	public $faq_inserted = false;

	/**
	 * Errors encountered while validating a submitted FAQ.
	 * @since 2.0.0
	 */
	public $validation_errors = array();

	public function __construct() {}

	/**
	 * Load the FAQ information from a WP_Post object or an ID
	 *
	 * @uses load_wp_post()
	 * @since 2.0.0
	 */
	public function load_post( $post ) {

		if ( is_int( $post ) || is_string( $post ) ) {
			$post = get_post( $post );
		}

		if ( $post instanceof WP_Post && $post->post_type == EWD_UFAQ_FAQ_POST_TYPE ) {
			$this->load_wp_post( $post );
			return true;
		} else {
			return false;
		}

	}

	/**
	 * Load data from WP post object and retrieve metadata
	 *
	 * @uses load_post_metadata()
	 * @since 2.0.0
	 */
	public function load_wp_post( $post ) {

		// Store post for access to other data if needed by extensions
		$this->post = $post;

		$this->ID = $post->ID;
		$this->question = $post->post_title;
		$this->date = $post->post_date;
		$this->answer = $post->post_content;
		$this->post_status = $post->post_status;

		$this->categories = get_the_terms( $post, EWD_UFAQ_FAQ_CATEGORY_TAXONOMY );
		if ( ! is_array( $this->categories ) ) { $this->categories = array(); }

		$this->tags = get_the_terms( $post, EWD_UFAQ_FAQ_TAG_TAXONOMY );
		if ( ! is_array( $this->tags ) ) { $this->tags = array(); }

		$this->load_custom_fields();
		$this->load_post_metadata();

		do_action( 'ewd_ufaq_faq_load_post_data', $this, $post );
	}

	/**
	 * Store custom field information for post
	 * @since 2.0.0
	 */
	public function load_custom_fields() {
		global $ewd_ufaq_controller;

		$custom_fields = ewd_ufaq_decode_infinite_table_setting( $ewd_ufaq_controller->settings->get_setting( 'faq-fields' ) );

		foreach ( $custom_fields as $custom_field ) {

			if ( ! $custom_field->id ) { continue; }

			$this->custom_fields[ $custom_field->id ] = get_post_meta( $this->ID, 'Custom_Field_' . $custom_field->id, true );
		}
	}

	/**
	 * Store metadata for post
	 * @since 2.0.0
	 */
	public function load_post_metadata() {

		$faq_author = get_post_meta( $this->ID, 'EWD_UFAQ_Post_Author', true );

		$user = wp_get_current_user();
		$this->faq_author = $faq_author ? $faq_author : $user->display_name;
		$this->faq_author_email = get_post_meta( $this->ID, 'EWD_UFAQ_Post_Author_Email', true );

		$this->views = get_post_meta( $this->ID, 'ufaq_view_count', true ) ? get_post_meta( $this->ID, 'ufaq_view_count', true ) : 0;

		$this->up_votes = get_post_meta( $this->ID, 'FAQ_Up_Votes', true );
		$this->down_votes = get_post_meta( $this->ID, 'FAQ_Down_Votes', true );

		$this->user_submitted = get_post_meta( $this->ID, 'FAQ_User_Submitted', true );
	}

	/**
	 * Insert a new FAQ submission into the database
	 *
	 * Validates the data, adds it to the database and executes notifications
	 * @since 2.0.0
	 */
	public function insert_faq()  {
		return class_exists( 'ewdufaqEWDPHSubmission', false ) ? ewdufaqEWDPHSubmission::insert_faq( $this ) : false;
	}

	/**
	 * Validate submission data. Expects to find data in $_POST.
	 * @since 2.0.0
	 */
	public function validate_submission()  {
		return class_exists( 'ewdufaqEWDPHSubmission', false ) ? ewdufaqEWDPHSubmission::validate_submission( $this ) : false;
	}

	/**
	 * Returns the decrypted version of the captcha code
	 * @since 2.0.0
	 */
	public function decrypt_modified_code( $user_code )  {
		return class_exists( 'ewdufaqEWDPHSubmission', false ) ? ewdufaqEWDPHSubmission::decrypt_modified_code( $this, $user_code ) : false;
	}

	/**
	 * Check if submission is valid
	 *
	 * @since 2.0.0
	 */
	public function is_valid_submission()  {
		return class_exists( 'ewdufaqEWDPHSubmission', false ) ? ewdufaqEWDPHSubmission::is_valid_submission( $this ) : false;
	}

	/**
	 * Insert post data for a new FAQ or update a FAQ
	 * @since 2.0.0
	 */
	public function insert_post_data()  {
		return class_exists( 'ewdufaqEWDPHSubmission', false ) ? ewdufaqEWDPHSubmission::insert_post_data( $this ) : false;
	}

	/**
	 * Insert the post metadata for a new FAQ or when updating a FAQ
	 * @since 2.0.0
	 */
	public function insert_post_meta()  {
		return class_exists( 'ewdufaqEWDPHSubmission', false ) ? ewdufaqEWDPHSubmission::insert_post_meta( $this ) : false;
	}

	/**
	 * Update the categories for an FAQ
	 * @since 2.0.0
	 */
	public function insert_post_categories()  {
		return class_exists( 'ewdufaqEWDPHSubmission', false ) ? ewdufaqEWDPHSubmission::insert_post_categories( $this ) : false;
	}

	/**
	 * Clears the $_POST values used by the plugin on successful submission
	 * @since 2.0.0
	 */
	public function clear_post_submission()  {
		return class_exists( 'ewdufaqEWDPHSubmission', false ) ? ewdufaqEWDPHSubmission::clear_post_submission( $this ) : null;
	}

}
} // endif;
