<?php

/**
 * Class to display a single FAQ on the front end.
 *
 * @since 2.0.0
 */
class ewdufaqViewFAQ extends ewdufaqView {

	// FAQ being displayed
	public $faq;

	// attributes from View.FAQs
	public $display_all_answers;
	public $no_comments;
	public $current_url;

	// used by FAQ search
	public $is_search;
	public $search_string;

	// unique ID for FAQ, in case multiple shortcodes used on same page
	public $unique_id;

	// FAQ view-specific content
	public $faq_title;
	public $faq_answer;
	public $faq_preview;
	public $date;

	// permalink to use when ouputting
	public $permalink;

	public function __construct( $args ) {

		parent::__construct( $args );

		$this->set_variables();
	} 

	/**
	 * Render the view and enqueue required stylesheets
	 * @since 2.0.0
	 */
	public function render() {

		// Add any dependent stylesheets or javascript
		$this->enqueue_assets();

		$this->set_display_variables();

		// Add css classes to the slider
		$this->classes = $this->get_classes();

		ob_start();
		
		if ( ! empty( $this->single_post ) ) { $this->add_custom_styling(); }

		$template = $this->find_template( 'faq' );
		if ( $template ) {
			include( $template );
		}
		$output = ob_get_clean();

		do_action( 'ewd_ufaq_faq_rendered', $this->faq->post->ID, $this );

		return apply_filters( 'ewd_ufaq_faq_output', $output, $this );
	}

	/**
	 * Print the FAQ title
	 *
	 * @since 2.0.0
	 */
	public function print_faq_title() {
		
		$template = $this->find_template( 'faq-title' );
		
		if ( $template ) {
			include( $template );
		}
	}

	/**
	 * Print the FAQ preview, if any
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_faq_preview() {
		global $ewd_ufaq_controller;
		
		if ( ! strlen( $this->faq->post->post_excerpt ) ) { return; }
		
		$template = $this->find_template( 'faq-preview' );
		
		if ( $template ) {
			include( $template );
		}
	}

	/**
	 * Print the custom fields, if enabled
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_element( $element ) {
		
		if ( $element == 'author_date' ) {

			$this->maybe_print_author_date(); 
		}
		elseif ( $element == 'body' ) {

			$this->print_faq_answer(); 
		}
		elseif ( $element == 'custom_fields' ) {

			$this->maybe_print_custom_fields(); 
		}
		elseif ( $element == 'categories' ) {

			$this->maybe_print_categories(); 
		}
		elseif ( $element == 'tags' ) {

			$this->maybe_print_tags(); 
		}
		elseif ( $element == 'ratings' ) {

			$this->maybe_print_ratings(); 
		}
		elseif ( $element == 'social_media' ) {

			$this->maybe_print_social_media(); 
		}
		elseif ( $element == 'permalink' ) {

			$this->maybe_print_permalink(); 
		}
		elseif ( $element == 'comments' ) {

			$this->maybe_print_comments(); 
		}
		elseif ( $element == 'back_to_top' ) {

			$this->maybe_print_back_to_top(); 
		}
	}

	/**
	 * Print the author/date container
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_author_date() {
		global $ewd_ufaq_controller;
		
		if ( ! $ewd_ufaq_controller->settings->get_setting( 'display-author' ) and ! $ewd_ufaq_controller->settings->get_setting( 'display-date' ) ) { return; }
		
		$template = $this->find_template( 'faq-author-date' );
		
		if ( $template ) {
			include( $template );
		}
	}

	/**
	 * Print the author if one is assigned
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_author() {
		global $ewd_ufaq_controller;
		
		if ( ! $ewd_ufaq_controller->settings->get_setting( 'display-author' ) or ! $this->faq->faq_author ) { return; }
		
		$template = $this->find_template( 'faq-author' );
		
		if ( $template ) {
			include( $template );
		}
	}

	/**
	 * Print the author/date container
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_date() {
		global $ewd_ufaq_controller;
		
		if ( ! $ewd_ufaq_controller->settings->get_setting( 'display-date' ) or ! $this->date ) { return; }
		
		$template = $this->find_template( 'faq-date' );
		
		if ( $template ) {
			include( $template );
		}
	}

	/**
	 * Print the FAQ answer
	 *
	 * @since 2.0.0
	 */
	public function print_faq_answer() {
		
		$template = $this->find_template( 'faq-answer' );
		
		if ( $template ) {
			include( $template );
		}
	}

	/**
	 * Print the custom fields, if enabled
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_custom_fields()  {
		echo apply_filters( 'ewd_ufaq_render_paid_element', '', 'maybe_print_custom_fields', $this );
	}

	/**
	 * Print the categories, if enabled
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_categories() {
		global $ewd_ufaq_controller;
		
		if ( $ewd_ufaq_controller->settings->get_setting( 'hide-categories' ) or ! $this->faq->categories ) { return; }
		
		$template = $this->find_template( 'faq-categories' );
		
		if ( $template ) {
			include( $template );
		}
	}

	/**
	 * Print the tags, if enabled
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_tags() {
		global $ewd_ufaq_controller;
		
		if ( $ewd_ufaq_controller->settings->get_setting( 'hide-tags' ) or ! $this->faq->tags ) { return; }
		
		$template = $this->find_template( 'faq-tags' );
		
		if ( $template ) {
			include( $template );
		}
	}

	/**
	 * Print the ratings, if enabled
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_ratings()  {
		echo apply_filters( 'ewd_ufaq_render_paid_element', '', 'maybe_print_ratings', $this );
	}

	/**
	 * Print the social media links, if enabled
	 *
	 * Extra checks included to make sure that this section doesn't
	 * display with an array that has only a blank element
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_social_media()  {
		echo apply_filters( 'ewd_ufaq_render_paid_element', '', 'maybe_print_social_media', $this );
	}

	/**
	 * Print the permalink, if enabled
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_permalink() {
		global $ewd_ufaq_controller;
		
		if ( $ewd_ufaq_controller->settings->get_setting( 'include-permalink' ) == 'none' ) { return; }
		
		$template = $this->find_template( 'faq-permalink' );
		
		if ( $template ) {
			include( $template );
		}
	}

	/**
	 * Print the comments, if enabled
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_comments() {
		global $ewd_ufaq_controller;
		
		if ( $this->no_comments ) { return; }
		
		$comments = get_comments( array( 'post_id' => $this->faq->post->ID ) );

		wp_list_comments( array(), $comments );

		comment_form( array(), $this->faq->post->ID );
	}

	/**
	 * Print the 'Back to Top' link, if enabled
	 *
	 * @since 2.0.0
	 */
	public function maybe_print_back_to_top() {
		global $ewd_ufaq_controller;
		
		if ( ! $ewd_ufaq_controller->settings->get_setting( 'display-back-to-top' ) ) { return; }
		
		$template = $this->find_template( 'faq-back-to-top' );
		
		if ( $template ) {
			include( $template );
		}
	}

	/**
	 * Print the selected social media buttons
	 *
	 * @since 2.0.0
	 */
	public function print_social_media_buttons()  {
		echo apply_filters( 'ewd_ufaq_render_paid_element', '', 'print_social_media_buttons', $this );
	}

	/**
	 * Print a link to share this FAQ on Facebook
	 *
	 * @since 2.0.0
	 */
	public function print_facebook_button()  {
		echo apply_filters( 'ewd_ufaq_render_paid_element', '', 'print_facebook_button', $this );
	}

	/**
	 * Print a link to share this FAQ on Twitter
	 *
	 * @since 2.0.0
	 */
	public function print_twitter_button()  {
		echo apply_filters( 'ewd_ufaq_render_paid_element', '', 'print_twitter_button', $this );
	}

	/**
	 * Print a link to share this FAQ on LinkedIn
	 *
	 * @since 2.0.0
	 */
	public function print_linkedin_button()  {
		echo apply_filters( 'ewd_ufaq_render_paid_element', '', 'print_linkedin_button', $this );
	}

	/**
	 * Print a link to share this FAQ on Pinterest
	 *
	 * @since 2.0.0
	 */
	public function print_pinterest_button()  {
		echo apply_filters( 'ewd_ufaq_render_paid_element', '', 'print_pinterest_button', $this );
	}

	/**
	 * Print a link to share this FAQ via email
	 *
	 * @since 2.0.0
	 */
	public function print_email_button()  {
		echo apply_filters( 'ewd_ufaq_render_paid_element', '', 'print_email_button', $this );
	}

	/**
	 * Returns the unique ID for this FAQ
	 *
	 * @since 2.0.0
	 */
	public function get_id() {

  		return 'ewd-ufaq-post-' . $this->unique_id;
  	}

  	/**
	 * Returns the json_decoded elements order
	 *
	 * @since 2.0.0
	 */
	public function get_order_elements() {
		global $ewd_ufaq_controller;

		$elements = array( 'categories' => 'Categories', 'body' => 'Body', 'author_date' => 'Author/Date', 'tags' => 'Tags', 'permalink' => 'Permalink', 'comments' => 'Comments', 'back_to_top' => 'Back to Top' );
		return apply_filters( 'ewd_ufaq_faq_elements_order', $elements, $this );
	}

  	/**
	 * Returns the class for the color block, if any
	 *
	 * @since 2.0.0
	 */
	public function get_color_block_shape()  {
		return class_exists( 'ewdufaqEWDPHPresentation', false ) ? ewdufaqEWDPHPresentation::get_color_block_shape( $this ) : '';
	}

	/**
	 * Returns the selected toggle symbol
	 *
	 * @since 2.0.0
	 */
  	public function get_toggle_symbol() {
  		global $ewd_ufaq_controller;

  		return $this->display_all_answers ? $ewd_ufaq_controller->settings->get_setting( 'styling-toggle-symbol' ) : strtolower( $ewd_ufaq_controller->settings->get_setting( 'styling-toggle-symbol' ) );
  	}

  	/**
	 * Returns the permalink for the main anchor tag (either the FAQ permalink or #)
	 *
	 * @since 2.0.25
	 */
  	public function get_anchor_permalink() {
  		global $ewd_ufaq_controller;

  		return $ewd_ufaq_controller->settings->get_setting( 'disable-faq-toggle' ) ? get_permalink( $this->faq->post->ID ) : '#';
  	}

  	/**
	 * Returns an array of the custom fields that exist for this site
	 *
	 * @since 2.0.0
	 */
	public function get_custom_fields()  {
		return class_exists( 'ewdufaqEWDPHPresentation', false ) ? ewdufaqEWDPHPresentation::get_custom_fields( $this ) : array();
	}

	/**
	 * Returns the value for a given custom field
	 *
	 * @since 2.0.0
	 */
	public function get_custom_field_value( $custom_field )  {
		return class_exists( 'ewdufaqEWDPHPresentation', false ) ? ewdufaqEWDPHPresentation::get_custom_field_value( $this, $custom_field ) : '';
	}

	/**
	 * Returns the category label, based on the label or number of category terms
	 *
	 * @since 2.0.0
	 */
  	public function get_categories_label() {
  		global $ewd_ufaq_controller;

		return apply_filters( 'ewd_ufaq_custom_label', count( $this->faq->categories ) === 1 ? __( 'Category:', 'ultimate-faqs' ) : __( 'Categories:', 'ultimate-faqs' ), 'label-categories', $this );
  	}

  	/**
	 * Returns a link with the name of a category
	 *
	 * @since 2.0.0
	 */
  	public function get_category_value( $category ) {
  		global $ewd_ufaq_controller;

  		$category_url = $ewd_ufaq_controller->settings->get_setting( 'pretty-permalinks' ) ? $this->current_url . 'faq-category/' . urlencode( $category->slug ) . '/' : add_query_arg( 'include_category', urlencode( $category->slug ), $this->current_url );

  		return empty( $ewd_ufaq_controller->single_page_print ) ? '<a href="' . esc_attr( $category_url ) . '">' . esc_html( $category->name ) . '</a>' : esc_html( $category->name );
  	}

  	/**
	 * Returns the tag label, based on the label or number of tag terms
	 *
	 * @since 2.0.0
	 */
  	public function get_tags_label() {
  		global $ewd_ufaq_controller;

		return apply_filters( 'ewd_ufaq_custom_label', count( $this->faq->tags ) === 1 ? __( 'Tag:', 'ultimate-faqs' ) : __( 'Tags:', 'ultimate-faqs' ), 'label-tags', $this );
  	}

  	/**
	 * Returns a link with the name of a tag
	 *
	 * @since 2.0.0
	 */
  	public function get_tag_value( $tag ) {
  		global $ewd_ufaq_controller;

  		$tag_url = $ewd_ufaq_controller->settings->get_setting( 'pretty-permalinks' ) ? $this->current_url . 'faq-tag/' . urlencode( $tag->slug ) . '/' : add_query_arg( 'include_tag', urlencode( $tag->slug ), $this->current_url );

  		return empty( $ewd_ufaq_controller->single_page_print ) ? '<a href="' . esc_attr( $tag_url ) . '">' . esc_html( $tag->name ) . '</a>' : esc_html( $tag->name );
  	}

	/**
	 * Returns an image with a custom thumbs up rating image, or the default one if unavailable
	 *
	 * @since 2.0.0
	 */
	public function get_thumbs_up_image()  {
		return class_exists( 'ewdufaqEWDPHPresentation', false ) ? ewdufaqEWDPHPresentation::get_thumbs_up_image( $this ) : '';
	}

  	/**
	 * Returns the positive vote count
	 *
	 * @since 2.0.0
	 */
	public function get_up_votes()  {
		return class_exists( 'ewdufaqEWDPHPresentation', false ) ? ewdufaqEWDPHPresentation::get_up_votes( $this ) : 0;
	}

  	/**
	 * Returns an image with a custom thumbs down rating image, or the default one if unavailable
	 *
	 * @since 2.0.0
	 */
	public function get_thumbs_down_image()  {
		return class_exists( 'ewdufaqEWDPHPresentation', false ) ? ewdufaqEWDPHPresentation::get_thumbs_down_image( $this ) : '';
	}

  	/**
	 * Returns the negative vote count
	 *
	 * @since 2.0.0
	 */
	public function get_down_votes()  {
		return class_exists( 'ewdufaqEWDPHPresentation', false ) ? ewdufaqEWDPHPresentation::get_down_votes( $this ) : 0;
	}

	/**
	 * Returns a link to this FAQ for Twitter
	 *
	 * @since 2.0.0
	 */
	public function get_social_twitter_link()  {
		return class_exists( 'ewdufaqEWDPHPresentation', false ) ? ewdufaqEWDPHPresentation::get_social_twitter_link( $this ) : '';
	}

	/**
	 * Returns a link to this FAQ for LinkedIn
	 *
	 * @since 2.0.0
	 */
	public function get_social_linkedin_link()  {
		return class_exists( 'ewdufaqEWDPHPresentation', false ) ? ewdufaqEWDPHPresentation::get_social_linkedin_link( $this ) : '';
	}

	/**
	 * Returns a link to this FAQ for Pinterest
	 *
	 * @since 2.0.0
	 */
	public function get_social_pinterest_link()  {
		return class_exists( 'ewdufaqEWDPHPresentation', false ) ? ewdufaqEWDPHPresentation::get_social_pinterest_link( $this ) : '';
	}

	/**
	 * Returns a link to this FAQ that can be emailed
	 *
	 * @since 2.0.0
	 */
	public function get_social_email_mailto_link()  {
		return class_exists( 'ewdufaqEWDPHPresentation', false ) ? ewdufaqEWDPHPresentation::get_social_email_mailto_link( $this ) : '';
	}

	/**
	 * Get the initial submit faq css classes
	 * @since 2.0.0
	 */
	public function get_classes( $classes = array() ) {
		global $ewd_ufaq_controller;

		$classes = array_merge(
			$classes,
			array(
				'ewd-ufaq-faq-div',
				'ewd-ufaq-faq-column-count-one',
				'ewd-ufaq-faq-responsive-columns-',
				'ewd-ufaq-faq-display-style-default',
			)
		);

		if ( empty( $ewd_ufaq_controller->settings->get_setting( 'disable-faq-toggle' ) ) ) {
			$classes[] = 'ewd-ufaq-can-be-toggled';
		}

		return apply_filters( 'ewd_ufaq_faq_classes', $classes, $this );
	}


	/**
	 * Set any neccessary variables when the FAQ is created
	 * @since 2.0.0
	 */
	public function set_variables() {
		global $ewd_ufaq_controller;
		
		$this->no_comments = ! empty( $this->no_comments ) ? $this->no_comments : ! $ewd_ufaq_controller->settings->get_setting( 'comments-on' );
		$this->display_all_answers = ! empty( $this->display_all_answers ) ? $this->display_all_answers : $ewd_ufaq_controller->settings->get_setting( 'display-all-answers' );

		$this->current_url = ! empty( $this->current_url ) ? $this->current_url : get_permalink();

		$this->add_faq_permalink();

		if ( ! empty( $this->single_post ) ) { $this->display_all_answers = true; }
	}

	/**
	 * Set any neccessary variables before displaying the FAQ
	 * @since 2.0.0
	 */
	public function set_display_variables() {
		global $post;
		global $ewd_ufaq_controller;
		
		// Added in to get another f$*!ing page builder to work correctly
		$post = get_post( $this->faq->post->ID );

		add_filter( 'siteorigin_panels_filter_content_enabled', array( $this, 'disable_site_origin_page_builder' ) );

		$this->unique_id = $this->faq->post->ID . '-' . ewd_random_string();
		$this->faq_title = apply_filters( 'the_title', $this->faq->post->post_title, $this->faq->post->ID );
		$this->faq_answer = apply_filters( 'the_content', $this->faq->post->post_content );
		$this->faq_preview = apply_filters( 'the_content', $this->faq->post->post_excerpt );
		$this->date = get_the_date( '', $this->faq->post->ID );
		
		do_action( 'ewd_ufaq_faq_display_variables', $this );
		
		remove_filter( 'siteorigin_panels_filter_content_enabled', array( $this, 'disable_site_origin_page_builder' ) );

		wp_reset_postdata();
	}

	/**
	 * Highlight a search term in $html across element boundaries (e.g., through <a> tags),
	 * wrapping only text nodes and splitting the highlight where needed.
	 *
	 * @since 2.0.0
	 */
	public function highlight_across_tags( $html, $term, $class = 'ewd-ufaq-highlight-search-term' )  {
		return class_exists( 'ewdufaqEWDPHPresentation', false ) ? ewdufaqEWDPHPresentation::highlight_across_tags( $this, $html, $term, $class ) : $html;
	}

	/**
	 * Extended KSES allowlist for FAQ answers.
	 *
	 * Starts with WordPress's normal post allowlist, then adds a few useful tags
	 * and carefully chosen attributes for richer content.
	 */
	public function get_faq_allowed_tags() {
		$allowed = wp_kses_allowed_html( 'post' );
	
		// Media
		$allowed['video'] = array(
			'src'      => true,
			'controls' => true,
			'autoplay' => true,
			'loop'     => true,
			'muted'    => true,
			'poster'   => true,
			'preload'  => true,
			'width'    => true,
			'height'   => true,
			'class'    => true,
			'id'       => true,
			'style'    => true,
			'playsinline' => true,
		);
		$allowed['source'] = array(
			'src'  => true,
			'type' => true,
			'media' => true,
		);
		$allowed['track'] = array(
			'kind'    => true,
			'src'     => true,
			'srclang' => true,
			'label'   => true,
			'default' => true,
		);
		$allowed['audio'] = array(
			'src'      => true,
			'controls' => true,
			'autoplay' => true,
			'loop'     => true,
			'muted'    => true,
			'preload'  => true,
			'class'    => true,
			'id'       => true,
			'style'    => true,
		);
	
		// Embedded content
		$allowed['iframe'] = array(
			'src'             => true,
			'width'           => true,
			'height'          => true,
			'title'           => true,
			'class'           => true,
			'id'              => true,
			'style'           => true,
			'loading'         => true,
			'allow'           => true,
			'allowfullscreen' => true,
			'referrerpolicy'  => true,
			'sandbox'         => true,
		);
	
		// Forms
		$allowed['form'] = array(
			'action'         => true,
			'method'         => true,
			'enctype'        => true,
			'accept-charset' => true,
			'target'         => true,
			'name'           => true,
			'class'          => true,
			'id'             => true,
		);
		$allowed['input'] = array(
			'type'        => true,
			'name'        => true,
			'value'       => true,
			'placeholder' => true,
			'checked'     => true,
			'selected'    => true,
			'disabled'    => true,
			'readonly'    => true,
			'required'    => true,
			'min'         => true,
			'max'         => true,
			'step'        => true,
			'maxlength'   => true,
			'size'        => true,
			'pattern'     => true,
			'multiple'    => true,
			'accept'      => true,
			'src'         => true,
			'alt'         => true,
			'class'       => true,
			'id'          => true,
		);
		$allowed['textarea'] = array(
			'name'        => true,
			'rows'        => true,
			'cols'        => true,
			'placeholder' => true,
			'disabled'    => true,
			'readonly'    => true,
			'required'    => true,
			'class'       => true,
			'id'          => true,
		);
		$allowed['select'] = array(
			'name'     => true,
			'multiple' => true,
			'disabled' => true,
			'required' => true,
			'class'    => true,
			'id'       => true,
		);
		$allowed['option'] = array(
			'value'    => true,
			'selected' => true,
			'disabled' => true,
			'label'    => true,
		);
		$allowed['optgroup'] = array(
			'label'    => true,
			'disabled' => true,
		);
		$allowed['button'] = array(
			'type'     => true,
			'name'     => true,
			'value'    => true,
			'disabled' => true,
			'class'    => true,
			'id'       => true,
		);
		$allowed['label'] = array(
			'for'   => true,
			'class' => true,
			'id'    => true,
		);
		$allowed['fieldset'] = array(
			'class' => true,
			'id'    => true,
		);
		$allowed['legend'] = array(
			'class' => true,
			'id'    => true,
		);
	
		//Semantic/layout tags
		$allowed['figure'] = array(
			'class' => true,
			'id'    => true,
			'style' => true,
		);
		$allowed['figcaption'] = array(
			'class' => true,
			'id'    => true,
			'style' => true,
		);
		$allowed['details'] = array(
			'open'  => true,
			'class' => true,
			'id'    => true,
		);
		$allowed['summary'] = array(
			'class' => true,
			'id'    => true,
		);
		$allowed['mark'] = array(
			'class' => true,
		);
		$allowed['time'] = array(
			'datetime' => true,
			'class'    => true,
		);
	
		return $allowed;
	}

	/**
	 * Builds the display permalink for the current FAQ, based on selected settings
	 * @since 2.0.0
	 */
	public function add_faq_permalink() {
		global $ewd_ufaq_controller;

		if ( $ewd_ufaq_controller->settings->get_setting( 'permalink-type' ) == 'individual_page' or ! empty( $this->is_search ) ) {

			$this->permalink =  get_permalink( $this->faq->post->ID );

			return;
		}

		if ( $ewd_ufaq_controller->settings->get_setting( 'pretty-permalinks' ) ) {

			$this->permalink =  get_permalink() . 'single-faq/' . $this->faq->post->post_name . '/';

			return;
		} 

		$this->permalink = add_query_arg( 'Display_FAQ', $this->faq->post->ID, ( ! empty( $this->current_url ) ? $this->current_url : get_permalink() ) );
	}

	/**
	 * Ridiculous workaround for Site Origin Page Builder. Can remove once (hopefully)
	 * it is less prevalent
	 * @since 2.0.0
	 */
	public function disable_site_origin_page_builder( $bool ) {

		return false;
	}

	/**
	 * Enqueue the necessary CSS and JS files
	 * @since 2.0.0
	 */
	public function enqueue_assets() {
		global $ewd_ufaq_controller;
		if ( ! $ewd_ufaq_controller->get_front_end_php_data( 'ewd-ufaq-js', 'ewd_ufaq_php_data' ) ) {
			$args = array( 'nonce' => wp_create_nonce( 'ewd-ufaq-js' ), 'faq_accordion' => $ewd_ufaq_controller->settings->get_setting( 'faq-accordion' ), 'category_accordion' => $ewd_ufaq_controller->settings->get_setting( 'faq-category-accordion' ), 'faq_scroll' => $ewd_ufaq_controller->settings->get_setting( 'scroll-to-top' ) );
			$ewd_ufaq_controller->add_front_end_php_data( 'ewd-ufaq-js', 'ewd_ufaq_php_data', apply_filters( 'ewd_ufaq_js_localize_data', $args, $this ) );
		}
		
		wp_enqueue_style( 'ewd-ufaq-css' );

		wp_enqueue_script( 'ewd-ufaq-js' );
		do_action( 'ewd_ufaq_view_assets', $this );

	}
	public function get_body_classes() {
		$classes = array( 'ewd-ufaq-faq-body' );
		if ( ! $this->display_all_answers ) { $classes[] = 'ewd-ufaq-hidden'; }
		return apply_filters( 'ewd_ufaq_faq_body_classes', $classes, $this );
	}
}
