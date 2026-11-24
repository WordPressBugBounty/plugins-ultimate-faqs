<?php

/**
 * Base class for any view requested on the front end.
 *
 * @since 2.0.0
 */
class ewdufaqView extends ewdufaqBase {

	/**
	 * Post type to render
	 */
	public $post_type = null;

	/**
	 * Map types of content to the template which will render them
	 */
	public $content_map = array(
		'title'							 => 'content/title',
	);

	// Locations that should be searched for templates
	public $template_dirs;

	// Default labels, used a fallbacks if no admin inputted label exists
	public $label_defaults = array();

	// The classes that should be added to the main form div
	public $classes;

	/**
	 * Initialize the class
	 * @since 2.0.0
	 */
	public function __construct( $args ) {

		// Parse the values passed
		$this->parse_args( $args );
		
		// Filter the content map so addons can customize what and how content
		// is output. Filters are specific to each view, so for this base view
		// you would use the filter 'us_content_map_ewdufaqView'
		$this->content_map = apply_filters( 'ewd_ufaq_content_map_' . get_class( $this ), $this->content_map );

	}

	/**
	 * Render the view and enqueue required stylesheets
	 *
	 * @note This function should always be overridden by an extending class
	 * @since 2.0.0
	 */
	public function render() {

		$this->set_error(
			array( 
				'type'		=> 'render() called on wrong class'
			)
		);
	}

	/**
	 * Load a template file for views
	 *
	 * First, it looks in the current theme's /ewd-ufaq-templates/ directory. Then it
	 * will check a parent theme's /ewd-ufaq-templates/ directory. If nothing is found
	 * there, it will retrieve the template from the plugin directory.

	 * @since 2.0.0
	 * @param string template Type of template to load (eg - reviews, review)
	 */
	function find_template( $template ) {
		global $ewd_ufaq_controller;
		$paid = array(
			'faq-custom-fields' => 'fields',
			'faq-ratings' => 'premium',
			'faq-social-media' => 'premium',
			'faq-social-media-email' => 'premium',
			'faq-social-media-facebook' => 'premium',
			'faq-social-media-linkedin' => 'premium',
			'faq-social-media-pinterest' => 'premium',
			'faq-social-media-twitter' => 'premium',
			'faqs-pagination' => 'premium',
			'faqs-list-header' => 'premium',
			'faqs-category-list-header' => 'premium',
			'faqs-search' => 'search',
			'faqs-search-submit' => 'search',
			'submit-faq' => 'submit-faq',
			'submit-faq-captcha' => 'submit-faq',
			'submit-faq-custom-field-checkbox' => 'submit-faq',
			'submit-faq-custom-field-date' => 'submit-faq',
			'submit-faq-custom-field-datetime' => 'submit-faq',
			'submit-faq-custom-field-radio' => 'submit-faq',
			'submit-faq-custom-field-select' => 'submit-faq',
			'submit-faq-custom-field-text' => 'submit-faq',
			'submit-faq-custom-field-textarea' => 'submit-faq',
			'submit-faq-field-author-email' => 'submit-faq',
			'submit-faq-field-author' => 'submit-faq',
			'submit-faq-field-faq-answer' => 'submit-faq',
			'submit-faq-field-faq-question' => 'submit-faq',
			'submit-faq-submitted-faq-message' => 'submit-faq',
		);
		if ( ! is_string( $template ) || ( 0 === strpos( $template, 'submit-faq' ) && ! isset( $paid[ $template ] ) ) ) { return false; }
		$capability = isset( $paid[ $template ] ) ? $paid[ $template ] : '';
		if ( $capability && ( ! isset( $ewd_ufaq_controller->permissions ) || ! $ewd_ufaq_controller->permissions->check_permission( $capability ) ) ) {
			return false;
		}

		$this->template_dirs = array(
			get_stylesheet_directory() . '/' . EWD_UFAQ_TEMPLATE_DIR . '/',
			get_template_directory() . '/' . EWD_UFAQ_TEMPLATE_DIR . '/',
			EWD_UFAQ_PLUGIN_DIR . '/' . EWD_UFAQ_TEMPLATE_DIR . '/'
		);
		
		$this->template_dirs = apply_filters( 'ewd_ufaq_template_directories', $this->template_dirs, $template, $this );

		foreach ( $this->template_dirs as $dir ) {
			// Never execute an obsolete paid default left by a merge-style update.
			// Child/parent and explicitly supplied integration directories still work.
			if ( $capability && rtrim( wp_normalize_path( $dir ), '/' ) === wp_normalize_path( EWD_UFAQ_PLUGIN_DIR . '/' . EWD_UFAQ_TEMPLATE_DIR ) ) { continue; }
			if ( file_exists( $dir . $template . '.php' ) ) {
				return $dir . $template . '.php';
			}
		}

		if ( $capability ) { do_action( 'ewd_ufaq_missing_paid_template', $template ); }
		return false;
	}

	/**
	 * Enqueue stylesheets
	 */
	public function enqueue_assets() {

		//enqueue assets here
	}

	public function get_option( $option_name ) {
		global $ewd_ufaq_controller;

		$value   = ! empty( $this->$option_name ) ? $this->$option_name : $ewd_ufaq_controller->settings->get_setting( $option_name );
		$default = isset( $ewd_ufaq_controller->settings->defaults[ $option_name ] ) ? $ewd_ufaq_controller->settings->defaults[ $option_name ] : null;
		return $ewd_ufaq_controller->permissions->effective_setting( $option_name, $value, $default );
	}

	public function get_label( $label_name ) {
		global $ewd_ufaq_controller;

		if ( empty( $this->label_defaults ) ) { $this->set_label_defaults(); }

		$default = isset( $this->label_defaults[ $label_name ] ) ? $this->label_defaults[ $label_name ] : '';
		return apply_filters( 'ewd_ufaq_custom_label', $default, $label_name, $this );
	}

	public function set_label_defaults() {

		$this->label_defaults = array(
			'label-posted'								=> __( 'Posted', 'ultimate-faqs' ),
			'label-by'									=> __( 'by', 'ultimate-faqs' ),
			'label-on'									=> __( 'on', 'ultimate-faqs' ),
			'label-enter-question'						=> __( 'Enter your question', 'ultimate-faqs' ),
			'label-search'								=> __( 'Search', 'ultimate-faqs' ),
			'label-permalink'							=> __( 'Permalink', 'ultimate-faqs' ),
			'label-back-to-top'							=> __( 'Back to Top', 'ultimate-faqs' ),
			'label-expand-all'							=> __( 'Expand All', 'ultimate-faqs' ),
			'label-collapse-all'						=> __( 'Collapse All', 'ultimate-faqs' ),
			'label-previous'							=> __( 'Previous', 'ultimate-faqs' ),
			'label-next'								=> __( 'Next', 'ultimate-faqs' ),
			'label-load-more'							=> __( 'Load More', 'ultimate-faqs' ),
			'label-woocommerce-tab'						=> __( 'FAQs', 'ultimate-faqs' ),
			'label-share-faq'							=> __( 'Share', 'ultimate-faqs' ),
			'label-find-faq-helpful'					=> __( 'Did you find this FAQ helpful?', 'ultimate-faqs' ),
			'label-search-placeholder'					=> __( 'Enter your question...', 'ultimate-faqs' ),
			'label-thank-you-submit'					=> __( 'Thank you for submitting an FAQ.', 'ultimate-faqs' ),
			'label-submit-question'						=> __( 'Submit a Question', 'ultimate-faqs' ),
			'label-please-fill-form-below'				=> __( 'Please fill out the form below to submit a question.', 'ultimate-faqs' ),
			'label-send-question'						=> __( 'Send Question', 'ultimate-faqs' ),
			'label-question-title'						=> __( 'Question Title', 'ultimate-faqs' ),
			'label-question-title-explanation'			=> __( 'What question is being answered?', 'ultimate-faqs' ),
			'label-proposed-answer'						=> __( 'Proposed Answer', 'ultimate-faqs' ),
			'label-proposed-answer-explanation'			=> __( 'What answer should be displayed for this question?', 'ultimate-faqs' ),
			'label-question-author'						=> __( 'FAQ Author', 'ultimate-faqs' ),
			'label-question-author-explanation'			=> __( 'What name should be displayed with your FAQ?', 'ultimate-faqs' ),
			'label-question-author-email'				=> __( 'Author Email', 'ultimate-faqs' ),
			'label-question-author-email-explanation'	=> __( 'This is only used to verify the authenticity of the FAQ. It is not displayed anywhere.', 'ultimate-faqs' ),
			'label-captcha-image-number'				=> __( 'Image Number', 'ultimate-faqs' ),
			'label-no-results-found'					=> __( 'No result FAQs contained the term \'%s\'', 'ultimate-faqs' ),
		);
	}

	public function add_custom_styling() {
		// The Basic settings Custom CSS box is a Free feature, distinct from paid
		// per-element styling controls. Keep it effective without Helper or rights.
		$css = $this->get_option( 'custom-css' );
		$css = apply_filters( 'ewd_ufaq_custom_styles', is_string( $css ) ? $css : '', $this );
		if ( is_string( $css ) && '' !== $css ) {
			echo '<style>' . str_ireplace( '</style', '', $css ) . '</style>';
		}
	}

	/**
	 * Adds schema data about the FAQs being displayed
	 *
	 * @since 2.0.0
	 */
	public function add_schema_data() {
		global $ewd_ufaq_controller;

		if ( $ewd_ufaq_controller->settings->get_setting( 'disable-microdata' ) ) { return; }

		foreach ( $this->faqs as $faq_view ) {

			$schema_object = array(
					'@type' => 'Question',
					'name'  => $faq_view->faq->question,
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $this->strip_comment_tags( $faq_view->faq->answer ),
					)
			);

			if ( ! empty( $faq_view->faq->up_votes ) ) {
				$schema_object['acceptedAnswer']['upvoteCount'] = $faq_view->faq->up_votes;
			}

			if ( ! empty( $faq_view->faq->down_votes ) ) {
				$schema_object['acceptedAnswer']['downvoteCount'] = $faq_view->faq->down_votes;
			}

			if ( ! empty( $faq_view->faq->faq_author ) ) {
				$schema_object['acceptedAnswer']['author'] = array(
					'@type' => 'Person',
					'name'  => $faq_view->faq->faq_author
				);
			}

			$ewd_ufaq_controller->schema_faq_data[] = $schema_object; 
		}
	}

	/**
	 * ??
	 *
	 * @since 2.1.11
	 */
	public function strip_comment_tags( $content ) {

		return preg_replace( '/<!--(.|\s)*?-->/', '', $content );
	}

}
