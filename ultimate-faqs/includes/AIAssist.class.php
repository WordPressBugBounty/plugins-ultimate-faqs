<?php
/**
 * Class to create the 'AI Assist' button and pop-up
 */

if ( !defined( 'ABSPATH' ) )
	exit;

if ( !class_exists( 'ewdufaqAIAssist' ) ) {
class ewdufaqAIAssist {

	public function __construct() {

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

		// Print the modals
		add_action( 'admin_footer-edit.php', array( $this, 'print_button_and_modal' ) );

		add_action( 'wp_ajax_ewd_ufaq_ai_retrieve_faqs', array( $this, 'retrieve_faqs' ) );
		add_action( 'wp_ajax_ewd_ufaq_ai_publish_faqs', array( $this, 'publish_faqs' ) );
	}

	public function admin_enqueue( $hook ) {
		global $post_type;

		if ( $hook != 'edit.php' or ! isset( $post_type ) or $post_type != EWD_UFAQ_FAQ_POST_TYPE ) { return; }

    	wp_enqueue_style( 'ewd-ufaq-admin-ai-faq-css', EWD_UFAQ_PLUGIN_URL . '/assets/css/ewd-ufaq-admin-ai-faq.css', array(), EWD_UFAQ_VERSION );

    	wp_enqueue_script( 'ewd-ufaq-admin-ai-faq-js', EWD_UFAQ_PLUGIN_URL . '/assets/js/ewd-ufaq-admin-ai-faq.js', array( 'jquery' ), EWD_UFAQ_VERSION );
	}

	/**
	 * Adds 'AI Assist' button and creates the 'AI FAQ Creation' pop-up
	 * 
	 * @since 2.3.0
	 */
	public function print_button_and_modal() {
		global $post_type;
		global $ewd_ufaq_controller;

		if ( ! isset( $post_type ) or $post_type != EWD_UFAQ_FAQ_POST_TYPE ) { return; }

		$args = array(
			'posts_per_page'	=> -1,
			//'post_type'			=> array( 'post', 'page', 'attachment' ),
			'post_type'			=> array( 'post', 'page' ),
			'post_status'		=> array( 'publish', 'pending', 'draft', 'future', 'private' ),
			'order'				=> 'ASC',
			'orderby'			=> 'title'
		);

		$content_options = array();

		$posts = get_posts( $args );

		foreach ( $posts as $post ) {

			if ( empty( $content_options[ $post->post_type ] ) ) { $content_options[ $post->post_type ] = array(); }

			$content_options[ $post->post_type ][] = $post;
		}

		$content_options = apply_filters( 'ewd_ufaq_ai_faqs_content_options', $content_options ); 

		?>

		<button id='ewd-ufaq-ai-faqs-open' class='ewd-ufaq-hidden button button-secondary'>
			<?php _e( 'AI Assist', 'ultimate-faqs' ); ?>
		</button>

		<div id='ewd-ufaq-ai-faqs-modal' class='ewd-ufaq-hidden'>

			<div class='ewd-ufaq-ai-faqs-modal-inside'>

				<div class='ewd-ufaq-ai-faqs-modal-close'>
					<div class='ewd-ufaq-ai-faqs-modal-close-inside'><span class="dashicons dashicons-no-alt"></span></div>
				</div>

				<h4>
					<?php _e( 'AI FAQ Creation', 'ultimate-faqs' ); ?>
				</h4>

				<p class="ewd-ufaq-ai-faqs-modal-warning">
					<span class="dashicons dashicons-info-outline"></span><?php _e( 'This feature sends a remote request to our server to generate FAQs using Open AI\'s API', 'ultimate-faqs' ); ?>
				</p>

				<div id='ewd-ufaq-ai-faqs-params'>

					<div class='ewd-ufaq-ai-faqs-field ewd-ufaq-ai-no-premium'>

						<label for='ewd-ufaq-ai-faqs-count'>
							<?php _e( 'Number of FAQs', 'ultimate-faqs' ); ?>
						</label>

						<select id='ewd-ufaq-ai-faqs-count'>

							<?php for ( $i = 1; $i <= ( ! $ewd_ufaq_controller->permissions->check_permission( 'premium' ) ? 1 : 20 ) ; $i++ ) { ?>

								<option value='<?php echo $i; ?>'>
									<?php echo $i; ?>
								</option>

							<?php } ?> 

						</select>

						<?php if ( ! $ewd_ufaq_controller->permissions->check_permission( 'premium' ) ) { ?>

							<div class='ewd-ufaq-ai-no-premium-explanation'>
								The premium version of the Ultimate FAQ plugin is required to generate more than 1 FAQ and to use more than 1 post's content. <a href="https://www.etoilewebdesign.com/license-payment/?Selected=UFAQ&Quantity=1&utm_source=ufaq_admin&utm_content=ai_modal" target="_blank"><?php _e( 'Purchase here', 'ultimate-faqs' ); ?></a>.
							</div>

						<?php } ?>

					</div>

					<div class='ewd-ufaq-ai-faqs-field'>

						<label for='ewd-ufaq-ai-faqs-pages'>
							<?php _e( 'Page Content to Condense', 'ultimate-faqs' ); ?>
						</label>

						<select id='ewd-ufaq-ai-faqs-content' <?php echo ( $ewd_ufaq_controller->permissions->check_permission( 'premium' ) ? 'multiple=\'multiple\'' : '' ); ?>>

							<?php foreach ( $content_options as $post_type => $posts ){ ?>

								<optgroup label='<?php echo esc_attr( ucwords( $post_type ) ); ?>s'>

									<?php foreach ( $posts as $post ) { ?>

										<option value='<?php echo esc_attr( $post->ID ); ?>'>
											<?php echo esc_html( $post->post_title ); ?>
										</option>

									<?php } ?>

								</optgroup>

							<?php } ?>

						</select>

					</div>

					<div class='ewd-ufaq-ai-faqs-field'>

						<label for='ewd-ufaq-ai-faqs-categories'>
							<?php _e( 'Sort into Categories', 'ultimate-faqs' ); ?>
						</label>

						<select id='ewd-ufaq-ai-faqs-categories'>
							<option value='no'><?php _e( 'No', 'ultimate-faqs' ); ?></option>
							<option value='yes'><?php _e( 'Yes', 'ultimate-faqs' ); ?></option>
						</select>

					</div>

					<div class='ewd-ufaq-ai-faqs-submit'>

						<button id='ewd-ufaq-ai-faqs-create-button' class='ewd-ufaq-ai-faqs-button'>
							<?php _e( 'Create', 'ultimate-faqs' ); ?>
						</button>

					</div>

				</div>

				<div id='ewd-ufaq-ai-faqs-results' class='ewd-ufaq-hidden'>

					<button id='ewd-ufaq-ai-faqs-back-to-params'>
						<span class="dashicons dashicons-arrow-left-alt"></span><?php _e( 'Back to create', 'ultimate-faqs' ); ?>
					</button>

					<div id='ewd-ufaq-ai-faqs-created-faqs'>
						<?php _e( 'FAQs are being generated. This may take up to a few minutes.', 'ultimate-faqs' ); ?>
					</div>

					<div class='ewd-ufaq-ai-faqs-submit'>

						<button id='ewd-ufaq-ai-faqs-publish-button'  class='ewd-ufaq-ai-faqs-button'>
							<?php _e( 'Publish', 'ultimate-faqs' ); ?>
						</button>

					</div>

				</div>

			</div>

		</div>

		<?php
	}

	/**
	 * Send content to be condensed into FAQs by Open AI to our server,
	 * receive the response, and return it to the JS function so that
	 * the FAQs can be previewed and edited
	 * 
	 * @since 2.3.0
	 */
	public function retrieve_faqs() {
		global $ewd_ufaq_controller;

		if (
			! check_ajax_referer( 'ewd-ufaq-admin-js', 'nonce' ) 
			|| 
			! current_user_can( $ewd_ufaq_controller->settings->get_setting( 'access-role' ) )
		) {
			ewdufaqHelper::admin_nopriv_ajax();
		}

		if ( ! is_array( json_decode( stripslashes_deep( $_POST['selected_posts'] ), true ) ) ) {

			wp_send_json_error(
				array(
					'error'	=> 'No content has been selected',
				)
			);
		}

		$selected_posts = array_map( 'intval', json_decode( stripslashes_deep( $_POST['selected_posts'] ), true ) );
		$faq_count = min( intval( $_POST['faq_count'] ), ( $ewd_ufaq_controller->permissions->check_permission( 'premium' ) ? 999 : 1 ) );
		$set_categories = $_POST['set_categories'] == 'yes' ? true : false;

		$selected_posts = $ewd_ufaq_controller->permissions->check_permission( 'premium' ) ? $selected_posts : array( reset( $selected_posts ) );

		$args = array(
			'posts_per_page'	=> -1,
			'post__in'			=> $selected_posts,
		);

		$content = '';

		$posts = get_posts( $args );

		foreach ( $posts as $post ) {

			$content .= wp_strip_all_tags( $post->post_content ) . ' ';
		}

		$categories = '';

		if ( $set_categories ) { 

			$args = array(
				'taxonomy'		=> EWD_UFAQ_FAQ_CATEGORY_TAXONOMY,
				'hide_empty'	=> false,
			);

			$category_terms = get_terms( $args );

			foreach ( $category_terms as $category_term ) { $categories .= $category_term->name . ','; }

			$categories = trim( $categories, ',' );
		}

		$url = add_query_arg(
			array(
				'faq_count'			=> $faq_count,
				'categories'		=> $categories,
			),
			'https://etoilewebdesign.com/open-ai/open-ai-client.php'
		);

		$args = array(
			'body'		=> $content,
			'timeout'	=> 30,
		);

		$response = wp_remote_post( $url, $args );

		if ( ! is_array( $response ) or is_wp_error( $response ) ) {

			wp_send_json_error(
				array(
					'error'	=> 'Invalid response received',
				)
			);
		}

		$body = json_decode( $response['body'], true ); 

		if ( empty( $body['choices'][0]['message']['content'] ) ) {

			wp_send_json_error(
				array(
					'error'	=> 'JSON decode failed',
				)
			);
		}

		$message_content = $this->parseCSVFromString( $body['choices'][0]['message']['content'] );

		$faq_content = array();

		$increment = $set_categories ? 3 : 2;

		foreach ( $message_content  as $created_faq ) {

			$faq = array( 
				'title' 		=> $created_faq['Question'], 
				'content' 		=> $created_faq['Answer'],
			);

			if ( $set_categories ) {

				$faq_categories = array();

				$category_names = explode( ',', $created_faq['Categories'] );

				foreach ( $category_names as $category_name ) {

					foreach ( $category_terms as $category_term ) {

						if ( $category_term->name != $category_name ) { continue; }

						$faq_categories[] = array( $category_term->term_id => $category_name );
					}
				}

				$faq['categories'] = $faq_categories;
			}

			$faq_content[] = $faq;
		}

		wp_send_json_success(
			array(
				'faqs'	=> $faq_content
			)
		);
	}

	/**
	 * Turn the AI generated FAQs into posts, after being edited and
	 * approved by the admin
	 * 
	 * @since 2.3.0
	 */
	public function publish_faqs() {
		global $ewd_ufaq_controller;

		if (
			! check_ajax_referer( 'ewd-ufaq-admin-js', 'nonce' ) 
			|| 
			! current_user_can( $ewd_ufaq_controller->settings->get_setting( 'access-role' ) )
		) {
			ewdufaqHelper::admin_nopriv_ajax();
		}

		if ( ! is_array( json_decode( stripslashes_deep( $_POST['faqs'] ), true ) ) ) {

			wp_send_json_error(
				array(
					'error'	=> 'No faqs to be created',
				)
			);
		}

		$faqs = $this->sanitize_faqs( json_decode( stripslashes_deep( $_POST['faqs'] ), true ) );

		foreach ( $faqs as $faq ) {

			$post = array(
				'post_title' 	=> $faq['title'],
				'post_content'	=> $faq['content'],
				'post_status'	=> 'publish',
				'post_type'		=> EWD_UFAQ_FAQ_POST_TYPE
			);

			$post_id = wp_insert_post( $post );

			if ( ! $post_id or empty( $faq['categories'] ) ) { continue; }

			wp_set_object_terms( $post_id, array_map('intval', $faq['categories'] ), EWD_UFAQ_FAQ_CATEGORY_TAXONOMY );
		}

		wp_send_json_success();
	}

	/**
	 * Sanitize new FAQ title, content and categories sent by admin
	 * 
	 * @since 2.3.0
	 */
	public function sanitize_faqs( $faqs ) {

		foreach ( $faqs as $index => $faq ) {

			$faqs[ $index ]['title'] = sanitize_text_field( $faq['title'] );
			$faqs[ $index ]['content'] = sanitize_textarea_field( $faq['content'] );
		}

		return $faqs;
	}

	/**
	 * Parse returned CSV content into an array
	 * 
	 * @since 2.3.0
	 */
	public function parseCSVFromString( $string ) {

		$faqs = array();

		$lines = explode( "\n", $string );

		$header = array();
		
		// Find the correct header line
		while ( sizeof( $header ) < 2 ) {
			$header = str_getcsv( array_shift( $lines ) );
		}

		foreach ( $lines as $line ) {

			if ( trim( $line ) === '' ) { continue; }

			if ( sizeof( $header ) != sizeof( str_getcsv( $line ) ) ) { continue; }

			$faqs[] = array_combine( $header, str_getcsv( $line ) );
		}

		return $faqs;
	}
}
} // endif;