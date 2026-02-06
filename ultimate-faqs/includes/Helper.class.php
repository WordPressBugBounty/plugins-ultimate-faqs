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
    global $post;
    
    $page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
    $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( $_GET['taxonomy'] ) : '';

    if ( isset( $_GET['post'] ) ) {

      $post = get_post( intval( $_GET['post'] ) );
      $post_type = $post->post_type;
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

  public static function get_page_details() {
    global $post;

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
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/ai',
            'title' => 'Create FAQs using AI (Premium)'
          ),
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/blocks-shortcodes/',
            'title' => 'Block and Shortcodes to display your FAQs'
          ),
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/search/add-to-page',
            'title' => 'Add FAQ Search to a Page (Premium)'
          ),
        )
      ),
      'ufaq-category' => array(
        'description' => __( 'The FAQ Categories page lets you create, edit, and manage categories to group related FAQs for better organization. While you can view, sort, and search categories here, FAQs can only be assigned to categories through the FAQ Add/Edit screen or the Quick Edit option on the main FAQs page.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/categories',
            'title' => 'Add or Edit a Category'
          ),
        )
      ),
      'ufaq-tag' => array(
        'description' => __( 'The FAQ Tags page allows you to create, edit, and manage tags to label and organize FAQs. Like categories, tags can only be assigned to FAQs through the Add/Edit screen or the Quick Edit option on the main FAQs page.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/tags',
            'title' => 'Add or Edit a Tag'
          ),
        )
      ),
      'ewd-ufaq-import' => array(
        'description' => __( 'Import your FAQs from a spreadsheet to create them more quickly.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/import',
            'title' => 'Import FAQs'
          ),
        )
      ),
      'ewd-ufaq-export' => array(
        'description' => __( 'Export your FAQs to a spreadsheet so they can be easily shared.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/export',
            'title' => 'Export FAQs'
          ),
        )
      ),
      'ewd-ufaq-settings' => array(
        'description' => __( 'The Basic Settings page lets you customize how FAQs behave and appear on your site, including layout toggles, comment support, permalink options, and display preferences. It also includes access control settings and a custom CSS field to fine-tune the style and functionality of your FAQ section.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/styling/css',
            'title' => 'Custom CSS'
          ),
        )
      ),
      'ewd-ufaq-settings-ewd-ufaq-basic-tab' => array(
        'description' => __( 'The Basic Settings page lets you customize how FAQs behave and appear on your site, including layout toggles, comment support, permalink options, and display preferences. It also includes access control settings and a custom CSS field to fine-tune the style and functionality of your FAQ section.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/styling/css',
            'title' => 'Custom CSS'
          ),
        )
      ),
      'ewd-ufaq-settings-ewd-ufaq-ordering-tab' => array(
        'description' => __( 'The Ordering Settings page controls how FAQs and categories are sorted and displayed on the front end, with options to group by category, show FAQ counts, and nest sub-categories. You can define sort criteria such as title, date created or modified, and set ascending or descending order for both FAQs and categories.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/faqs/order',
            'title' => 'Order of FAQs (Premium)'
          ),
        )
      ),
      'ewd-ufaq-settings-ewd-ufaq-premium-tab' => array(
        'description' => __( 'The Premium Settings page provides advanced customization options for FAQ display styles, pagination behavior, voting, search autocomplete, and permalink structure. It also includes features for user-submitted FAQs, WooCommerce and WPForms integrations, and admin notifications for streamlined content management.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/woocommerce/',
            'title' => 'WooCommerce Integration'
          ),
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/woocommerce/add',
            'title' => 'Add FAQs to WooCommerce Products'
          ),
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/wpforms/',
            'title' => 'WPForms Integration'
          ),
        )
      ),
      'ewd-ufaq-settings-ewd-ufaq-fields-tab' => array(
        'description' => __( 'The Fields Settings page lets you create and manage custom fields to add extra information to your FAQs, such as links, dates, files, or dropdowns. These fields can appear on your main FAQ display, individual FAQ pages, and the search page, offering advanced customization beyond categories and tags.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/custom-fields/create',
            'title' => 'Create and Edit Custom Fields'
          ),
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/custom-fields/faqs',
            'title' => 'Using Custom Fields with FAQs'
          ),
        )
      ),
      'ewd-ufaq-settings-ewd-ufaq-labelling-tab' => array(
        'description' => __( 'The Labelling Settings page allows you to customize or translate the text labels used throughout the plugin, making it easy to adapt the interface to your preferred language or tone. It offers a quick alternative to translation plugins for single-language sites, while still supporting full localization for multilingual setups.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/labelling/translating',
            'title' => 'Translating'
          ),
        )
      ),
      'ewd-ufaq-settings-ewd-ufaq-styling-tab' => array(
        'description' => __( 'The Styling Settings page offers extensive options to customize the appearance of your FAQs, including toggle symbols, colors, fonts, padding, and margins for various FAQ elements and themes. You can also control heading tags and styles, enabling precise visual integration with your site’s design.', 'ultimate-faqs' ),
        'tutorials'   => array(
          array(
            'url'   => 'https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/styling/css',
            'title' => 'Custom CSS'
          ),
        )
      ),
    );

    $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : '';
    $page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
    $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( $_GET['taxonomy'] ) : '';

    if ( isset( $_GET['post'] ) ) {

      $post = get_post( intval( $_GET['post'] ) );
      $post_type = $post->post_type;
    }
    else {
      
      $post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : '';
    }

    if ( in_array( $page . '-' . $tab, array_keys( $page_details ) ) ) { return $page_details[ $page . '-' . $tab ]; }

    if ( in_array( $page, array_keys( $page_details ) ) ) { return $page_details[ $page ]; }

    if ( in_array( $taxonomy, array_keys( $page_details ) ) ) { return $page_details[ $taxonomy ]; }

    if ( in_array( $post_type, array_keys( $page_details ) ) ) { return $page_details[ $post_type ]; }

    return array( 'description', 'tutorials' => array() );
  }
}

}