<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'ewdufaqHelper' ) ) {
/**
 * Class to to provide helper functions
 *
 * @since 2.1.1
 */
class ewdufaqHelper {

  // Hold the class instance.
  private static $instance = null;

  // Links for the help button
  private static $documentation_link = 'https://doc.etoilewebdesign.com/plugins/ultimate-faqs/user/';
  private static $tutorials_link = 'https://www.youtube.com/playlist?list=PLEndQUuhlvSrNdfu5FKa1uGHsaKZxgdWt';
  private static $support_center_link = 'https://www.etoilewebdesign.com/support-center/?Plugin=UFAQ&Type=FAQs';

  // Values for when to trigger the help button to display
  private static $post_types = array( EWD_UFAQ_FAQ_POST_TYPE );
  private static $taxonomies = array( EWD_UFAQ_FAQ_CATEGORY_TAXONOMY, EWD_UFAQ_FAQ_TAG_TAXONOMY );
  private static $additional_pages = array( 'ewd-ufaq-dashboard', 'ewd-ufaq-ordering-table', 'ewd-ufaq-import', 'ewd-ufaq-export', 'ewd-ufaq-settings' );

  /**
   * The constructor is private
   * to prevent initiation with outer code.
   * 
   **/
  private function __construct() {}

  /**
   * The object is created from within the class itself
   * only if the class has no instance.
   */
  public static function getInstance() {

    if ( self::$instance == null ) {

      self::$instance = new ewdufaqHelper();
    }
 
    return self::$instance;
  }

  
  /**
   * Check whether AI Admin Assistance (AIAA) is active/loaded.
   *
   * We prefer a constant check (most reliable), with fallbacks.
   *
   * @since 2.4.8
   */
  private static function aiaa_is_active() {
  
    if ( defined( 'AIT_AIAA_VERSION' ) ) { return true; }
  
    if ( class_exists( 'AIT_AIAA_Settings' ) ) { return true; }
  
    return false;
  }
  
  /**
   * AIAA integration
   *
   * @since 2.4.8
   */
  public static function aiaa_add_filter() {
  
    if ( self::aiaa_is_active() ) {
  
      add_filter( 'ait_aiaa_third_party_information', array( __CLASS__, 'add_help_to_aiaa' ), 20, 2 );
    }
  }
  
  /**
   * Adds Ultimate FAQs help content to AIAA's Third-Party Help tab.
   *
   * @param array $items
   * @param array $context { screen_id, post_type, taxonomy }
   * @return array
   *
   * @since 2.4.8
   */
  public static function add_help_to_aiaa( $items, $context ) {
    
    $items   = is_array( $items ) ? $items : array();
    $context = is_array( $context ) ? $context : array();
  
    // We only contribute on screens where our own helper would show.
    $screen_id = isset( $context['screen_id'] ) ? (string) $context['screen_id'] : '';
    $post_type = isset( $context['post_type'] ) ? (string) $context['post_type'] : '';
    $taxonomy  = isset( $context['taxonomy'] ) ? (string) $context['taxonomy'] : '';
  
    if ( ! self::aiaa_matches_context( $screen_id, $post_type, $taxonomy ) ) { return $items; }
    
    $page_details = self::get_page_details_for_context( $context );

    // Build AIAA help_links with grouping:
    // - Tutorials: page-specific items
    // - General: documentation/support resources
    $tutorial_links = array();
    if ( ! empty( $page_details['tutorials'] ) && is_array( $page_details['tutorials'] ) ) {

      foreach ( $page_details['tutorials'] as $tutorial ) {

        if ( empty( $tutorial['url'] ) || empty( $tutorial['title'] ) ) { continue; }

        $tutorial_links[] = array(
          'title' => (string) $tutorial['title'],
          'url'   => (string) $tutorial['url'],
        );
      }
    }

    $general_links = array();
    if ( ! empty( self::$documentation_link ) ) {
      $general_links[] = array(
        'title' => __( 'Documentation', 'ultimate-faqs' ),
        'url'   => self::$documentation_link,
      );
    }
    if ( ! empty( self::$tutorials_link ) ) {
      $general_links[] = array(
        'title' => __( 'YouTube Tutorials', 'ultimate-faqs' ),
        'url'   => self::$tutorials_link,
      );
    }
    if ( ! empty( self::$support_center_link ) ) {
      $general_links[] = array(
        'title' => __( 'Support Center', 'ultimate-faqs' ),
        'url'   => self::$support_center_link,
      );
    }

    $help_links = array();
    if ( ! empty( $tutorial_links ) ) { $help_links[ __( 'Tutorials', 'ultimate-faqs' ) ] = $tutorial_links; }
    if ( ! empty( $general_links ) ) { $help_links[ __( 'General', 'ultimate-faqs' ) ] = $general_links; }
  
    $items[] = array(
      'id'          => 'ufaq_help',
      'title'       => __( 'Ultimate FAQs Help', 'ultimate-faqs' ),
      'description' => ! empty( $page_details['description'] ) ? '<p>' . esc_html( $page_details['description'] ) . '</p>' : '',
      'help_links'  => $help_links,
      'source'      => array(
        'type' => 'plugin',
        'name' => 'Ultimate FAQs',
        'slug' => 'ultimate-faqs',
      ),
      'target_callback' => array( __CLASS__, 'aiaa_target_callback' ),
      'priority'        => 20,
      'capability'      => 'manage_options',
      'icon'            => 'dashicons-editor-help',
    );
    
    return $items;
  }
  
  /**
   * AIAA advanced targeting callback.
   *
   * @param array $context
   * @param array $item
   * @return bool
   *
   * @since 2.4.8
   */
  public static function aiaa_target_callback( $context, $item ) {
  
    $context = is_array( $context ) ? $context : array();
  
    $screen_id = isset( $context['screen_id'] ) ? (string) $context['screen_id'] : '';
    $post_type = isset( $context['post_type'] ) ? (string) $context['post_type'] : '';
    $taxonomy  = isset( $context['taxonomy'] ) ? (string) $context['taxonomy'] : '';
  
    return self::aiaa_matches_context( $screen_id, $post_type, $taxonomy );
  }
  
  /**
   * Shared matcher: aligns with should_button_display(), plus supports screen_id substring matches.
   *
   * @param string $screen_id
   * @param string $post_type
   * @param string $taxonomy
   * @return bool
   *
   * @since 2.4.8
   */
  private static function aiaa_matches_context( $screen_id, $post_type, $taxonomy ) {
  
    if ( ! empty( $post_type ) && in_array( $post_type, self::$post_types, true ) ) { return true; }
  
    if ( ! empty( $taxonomy ) && in_array( $taxonomy, self::$taxonomies, true ) ) { return true; }
  
    if ( ! empty( $screen_id ) && ! empty( self::$additional_pages ) ) {
  
      foreach ( self::$additional_pages as $slug ) {
  
        if ( empty( $slug ) ) { continue; }
  
        if ( strpos( $screen_id, $slug ) !== false ) { return true; }
      }
    }
  
    return false;
  }
  
  /**
   * Handle ajax requests in admin area for logged out users
   * @since 2.1.1
   */
  public static function admin_nopriv_ajax() {

    wp_send_json_error(
      array(
        'error' => 'loggedout',
        'msg'   => sprintf( __( 'You have been logged out. Please %slogin again%s.', 'ultimate-faqs' ), '<a href="' . wp_login_url( admin_url( 'admin.php?page=ewd-ufaq-dashboard' ) ) . '">', '</a>' ),
      )
    );
  }

  /**
   * Handle ajax requests where an invalid nonce is passed with the request
   * @since 2.1.1
   */
  public static function bad_nonce_ajax() {

    wp_send_json_error(
      array(
        'error' => 'badnonce',
        'msg'   => __( 'The request has been rejected because it does not appear to have come from this site.', 'ultimate-faqs' ),
      )
    );
  }

  /**
   * Escapes PHP data being passed to JS, recursively
   * @since 2.1.0
   */
  public static function escape_js_recursive( $values ) {

    $return_values = array();

    foreach ( (array) $values as $key => $value ) {

      if ( is_array( $value ) ) {

        $value = ewdufaqHelper::escape_js_recursive( $value );
      }
      elseif ( ! is_scalar( $value ) ) { 

        continue;
      }
      else {

        $value = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
      }
      
      $return_values[ $key ] = $value;
    }

    return $return_values;
  }

  public static function display_help_button() {

    // If AI Admin Assistance (AIAA) is active, UFAQ help is provided via the
    // AIAA third-party help tab instead of showing the native UFAQ bubble.
    if ( defined( 'AIT_AIAA_VERSION' ) ) { return; }

    // If AIAA is active, UFAQ help will be provided via AIAA instead of the UFAQ bubble.
    if ( self::aiaa_is_active() ) { return; }

    if ( ! ewdufaqHelper::should_button_display() ) { return; }

    ewdufaqHelper::enqueue_scripts();

    $page_details = self::get_page_details();

    ?>
      <button class="ewd-ufaq-dashboard-help-button" aria-label="Help">?</button>

      <div class="ewd-ufaq-dashboard-help-modal ewd-ufaq-hidden">
        <div class="ewd-ufaq-dashboard-help-description">
          <?php echo esc_html( $page_details['description'] ); ?>
        </div>
        <div class="ewd-ufaq-dashboard-help-tutorials">
          <?php foreach ( $page_details['tutorials'] as $tutorial ) { ?>
            <a href="<?php echo esc_url( $tutorial['url'] ); ?>" target="_blank">
              <?php echo esc_html( $tutorial['title'] ); ?>
            </a>
          <?php } ?>
        </div>
        <div class="ewd-ufaq-dashboard-help-links">
          <?php if ( ! empty( self::$documentation_link ) ) { ?>
              <a href="<?php echo esc_url( self::$documentation_link ); ?>" target="_blank" aria-label="Documentation">
                <?php _e( 'Documentation', 'ultimate-faqs' ); ?>
              </a>
          <?php } ?>
          <?php if ( ! empty( self::$tutorials_link ) ) { ?>
              <a href="<?php echo esc_url( self::$tutorials_link ); ?>" target="_blank" aria-label="YouTube Tutorials">
                <?php _e( 'YouTube Tutorials', 'ultimate-faqs' ); ?>
              </a>
          <?php } ?>
          <?php if ( ! empty( self::$support_center_link ) ) { ?>
              <a href="<?php echo esc_url( self::$support_center_link ); ?>" target="_blank" aria-label="Support Center">
                <?php _e( 'Support Center', 'ultimate-faqs' ); ?>
              </a>
          <?php } ?>
        </div>
      </div>
    <?php
  }

  

  public static function should_button_display() {
    
    $page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
    $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( $_GET['taxonomy'] ) : '';

    if ( isset( $_GET['post'] ) ) {

      $post = get_post( intval( $_GET['post'] ) );
      $post_type = $post ? $post->post_type : '';
    }
    else {
      
      $post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : '';
    }

    if ( in_array( $post_type, self::$post_types ) ) { return true; }

    if ( in_array( $taxonomy, self::$taxonomies ) ) { return true; }

    if ( in_array( $page, self::$additional_pages ) ) { return true; }

    return false;
  }

  public static function enqueue_scripts() {

    wp_enqueue_style( 'ewd-ufaq-admin-helper-button', EWD_UFAQ_PLUGIN_URL . '/assets/css/ewd-ufaq-helper-button.css', array(), EWD_UFAQ_PLUGIN_URL );

    wp_enqueue_script( 'ewd-ufaq-admin-helper-button', EWD_UFAQ_PLUGIN_URL . '/assets/js/ewd-ufaq-helper-button.js', array( 'jquery' ), EWD_UFAQ_PLUGIN_URL, true );
  }

  /**
   * Get page details based on an AIAA context array (AJAX-safe).
   *
   * @param array $context { screen_id, post_type, taxonomy }
   * @return array { description, tutorials }
   *
   * @since 2.4.8
   */
  public static function get_page_details_for_context( $context ) {

    $context = is_array( $context ) ? $context : array();

    $screen_id = isset( $context['screen_id'] ) ? (string) $context['screen_id'] : '';
    $post_type = isset( $context['post_type'] ) ? (string) $context['post_type'] : '';
    $taxonomy  = isset( $context['taxonomy'] ) ? (string) $context['taxonomy'] : '';

    $req = ( isset( $context['request'] ) && is_array( $context['request'] ) ) ? $context['request'] : array();

    // Prefer the AIAA request snapshot (most accurate for settings tabs).
    $page = isset( $req['page'] ) ? sanitize_text_field( $req['page'] ) : '';

    // Derive a "page" slug from screen_id if not provided.
    if ( $screen_id && ! empty( self::$additional_pages ) ) {
      foreach ( self::$additional_pages as $slug ) {
        if ( $slug && strpos( $screen_id, $slug ) !== false ) {
          $page = $slug;
          break;
        }
      }
    }

    // AIAA request snapshot includes tab (e.g. ewd-ufaq-basic-tab).
    $tab = isset( $req['tab'] ) ? sanitize_text_field( $req['tab'] ) : '';

    return self::get_page_details_by_values( $page, $tab, $taxonomy, $post_type );
  }

  /**
   * Shared resolver used by both get_page_details() (GET-based) and get_page_details_for_context() (context-based).
   *
   * @param string $page
   * @param string $tab
   * @param string $taxonomy
   * @param string $post_type
   * @return array { description, tutorials }
   *
   * @since 2.4.8
   */
  private static function get_page_details_by_values( $page, $tab, $taxonomy, $post_type ) {

    $page_details = array(
      'ufaq' => array(
        'description' => __( 'The FAQs page displays a list of all your frequently asked questions, with options to manage them via quick edit, bulk actions, import/export tools, and sorting or search filters. This serves as the central hub for maintaining your FAQ content at scale.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/create',
            'title' => 'Create an FAQ'
          ),
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/create',
            'title' => 'Add FAQs to a Page'
          ),
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/edit',
            'title' => 'Edit/Delete FAQs'
          ),
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/sort',
            'title' => 'Re-Order FAQs'
          ),
        )
      ),
      'edit-ufaq-question' => array(
        'description' => __( 'The FAQ edit screen allows you to create or update an individual FAQ, including its question, answer, categories/tags, and display settings. Use this to keep your FAQs accurate and up to date.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/create',
            'title' => 'Create an FAQ'
          ),
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/edit',
            'title' => 'Edit/Delete FAQs'
          ),
        )
      ),
      'ufaq-category' => array(
        'description' => __( 'FAQ Categories let you organize your FAQs into groups for easier navigation and display. You can create, edit, and assign categories to FAQs from here.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/categories',
            'title' => 'FAQ Categories'
          ),
        )
      ),
      'ufaq-tag' => array(
        'description' => __( 'FAQ Tags provide an additional way to label and filter FAQs. Use tags to help users find related questions or to power tag-based displays.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/tags',
            'title' => 'FAQ Tags'
          ),
        )
      ),
      'ewd-ufaq-dashboard' => array(
        'description' => __( 'The Ultimate FAQs dashboard provides a quick overview of your plugin configuration, shortcuts to key areas, and useful resources to help you get started.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/overview',
            'title' => 'Plugin Overview'
          ),
        )
      ),
      'ewd-ufaq-ordering-table' => array(
        'description' => __( 'Use the Ordering Table to quickly rearrange FAQs in the order they will appear. This is useful when you want precise control over the display sequence.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/sort',
            'title' => 'Re-Order FAQs'
          ),
        )
      ),
      'ewd-ufaq-import' => array(
        'description' => __( 'The Import screen allows you to upload FAQs in bulk from a file, saving time when migrating or creating large FAQ sets.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/import',
            'title' => 'Import FAQs'
          ),
        )
      ),
      'ewd-ufaq-export' => array(
        'description' => __( 'The Export screen lets you download your FAQs and settings for backup or migration to another site.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/export',
            'title' => 'Export FAQs'
          ),
        )
      ),
      'ewd-ufaq-settings' => array(
        'description' => __( 'The Settings page controls how your FAQs display and behave. Review options to match your theme and desired layout, and use the tabs to explore advanced configuration.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/settings',
            'title' => 'Settings Overview'
          ),
        )
      ),
      'ewd-ufaq-settings-styling' => array(
        'description' => __( 'The Styling tab provides options to customize the look and feel of your FAQ display. You can adjust colors, typography, and layout-related settings.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/styling',
            'title' => 'Styling Settings'
          ),
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/styling/css',
            'title' => 'Custom CSS'
          ),
        )
      ),
    );

    $page    = $page ? sanitize_text_field( $page ) : '';
    $tab     = $tab ? sanitize_text_field( $tab ) : '';
    $taxonomy= $taxonomy ? sanitize_text_field( $taxonomy ) : '';
    $post_type = $post_type ? sanitize_text_field( $post_type ) : '';

    if ( $page && $tab && isset( $page_details[ $page . '-' . $tab ] ) ) {
      return $page_details[ $page . '-' . $tab ];
    }

    if ( $page && isset( $page_details[ $page ] ) ) { return $page_details[ $page ]; }

    if ( $taxonomy && isset( $page_details[ $taxonomy ] ) ) { return $page_details[ $taxonomy ]; }

    if ( $post_type && isset( $page_details[ $post_type ] ) ) { return $page_details[ $post_type ]; }

    return array( 'description' => '', 'tutorials' => array() );
  }


  public static function get_page_details() {
    
    $tab      = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : '';
    $page     = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
    $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( $_GET['taxonomy'] ) : '';

    if ( isset( $_GET['post'] ) ) {
      $post      = get_post( intval( $_GET['post'] ) );
      $post_type = $post ? $post->post_type : '';
    }
    else {
      $post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : '';
    }

    return self::get_page_details_by_values( $page, $tab, $taxonomy, $post_type );
  }

}

// Register integrations after all plugins load.
add_action( 'plugins_loaded', array( 'ewdufaqHelper', 'aiaa_add_filter' ), 20 );

}