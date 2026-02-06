<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'ewdufaqDashboard' ) ) {
/**
 * Class to handle plugin dashboard
 *
 * @since 2.0.0
 */
class ewdufaqDashboard {

	public $message;
	public $status = true;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_dashboard_to_menu' ), 99 );
	}

	public function add_dashboard_to_menu() {
		global $ewd_ufaq_controller;
		global $submenu;

		add_submenu_page( 
			'edit.php?post_type=ufaq', 
			'Dashboard', 
			'Dashboard', 
			'manage_options', 
			'ewd-ufaq-dashboard', 
			array($this, 'display_dashboard_screen') 
		);

		// Create a new sub-menu in the order that we want
		$new_submenu = array();
		$menu_item_count = 3;

		if ( ! isset( $submenu['edit.php?post_type=ufaq'] ) or  ! is_array($submenu['edit.php?post_type=ufaq']) ) { return; }
		
		foreach ( $submenu['edit.php?post_type=ufaq'] as $key => $sub_item ) {
			
			if ( $sub_item[0] == 'Dashboard' ) { $new_submenu[0] = $sub_item; }
			elseif ( $sub_item[0] == 'Settings' ) { $new_submenu[ sizeof($submenu) ] = $sub_item; }
			else {
				
				$new_submenu[$menu_item_count] = $sub_item;
				$menu_item_count++;
			}
		}

		ksort($new_submenu);
		
		$submenu['edit.php?post_type=ufaq'] = $new_submenu;
	}

	public function display_dashboard_screen() { 
		global $ewd_ufaq_controller;

		$permission = $ewd_ufaq_controller->permissions->check_permission( 'styling' );

		$args = array(
			'post_type' => EWD_UFAQ_FAQ_POST_TYPE,
			'orderby' => 'meta_value_num',
			'meta_key' => 'ufaq_view_count',
			'posts_per_page' => 10
		);
		
		$query = new WP_Query( $args );
		$faqs = $query->get_posts();

		?>

		<div id="ewd-ufaq-dashboard-content-area">
		
			<?php if ( ! $permission or get_option("EWD_UFAQ_Trial_Happening") == "Yes" ) {
				$premium_info = '<div class="ewd-ufaq-dashboard-visit-our-site">';
				$premium_info .= sprintf( __( '<a href="%s" target="_blank">Visit our website</a> to learn how to upgrade to premium.'), 'https://www.etoilewebdesign.com/premium-upgrade-instructions/' );
				$premium_info .= '</div>';

				$premium_info = apply_filters( 'ewd_dashboard_top', $premium_info, 'UFAQ', 'https://www.etoilewebdesign.com/license-payment/?Selected=UFAQ&Quantity=1' );

				echo wp_kses(
					$premium_info,
					apply_filters( 'ewd_dashboard_top_kses_allowed_tags', wp_kses_allowed_html( 'post' ) )
				);
			} ?>

			<ul class="ewd-ufaq-dashboard-support-widgets">
				<li>
					<div class="ewd-ufaq-dashboard-support-widgets-title"><?php _e('YouTube Tutorials', 'ultimate-faqs'); ?></div>
					<div class="ewd-ufaq-dashboard-support-widgets-text-and-link">
						<div class="ewd-ufaq-dashboard-support-widgets-text"><span class="dashicons dashicons-star-empty"></span>Get help with our video tutorials</div>
						<a class="ewd-ufaq-dashboard-support-widgets-link" href="https://www.youtube.com/watch?v=zf-tYLqHpRs&list=PLEndQUuhlvSrNdfu5FKa1uGHsaKZxgdWt&index=2&ab_channel=%C3%89toileWebDesign" target="_blank"><?php _e('View', 'ultimate-faqs'); ?></a>
					</div>
				</li>
				<li>
					<div class="ewd-ufaq-dashboard-support-widgets-title"><?php _e('Documentation', 'ultimate-faqs'); ?></div>
					<div class="ewd-ufaq-dashboard-support-widgets-text-and-link">
						<div class="ewd-ufaq-dashboard-support-widgets-text"><span class="dashicons dashicons-star-empty"></span>View our in-depth plugin documentation</div>
						<a class="ewd-ufaq-dashboard-support-widgets-link" href="https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/?utm_source=ufaq_dashboard&utm_content=icons_documentation" target="_blank"><?php _e('View', 'ultimate-faqs'); ?></a>
					</div>
				</li>
				<li>
					<div class="ewd-ufaq-dashboard-support-widgets-title"><?php _e('Plugin FAQs', 'ultimate-faqs'); ?></div>
					<div class="ewd-ufaq-dashboard-support-widgets-text-and-link">
						<div class="ewd-ufaq-dashboard-support-widgets-text"><span class="dashicons dashicons-star-empty"></span>Access plugin and info and FAQs here.</div>
						<a class="ewd-ufaq-dashboard-support-widgets-link" href="https://wordpress.org/plugins/ultimate-faqs/#faq" target="_blank"><?php _e('View', 'ultimate-faqs'); ?></a>
					</div>
				</li>
				<li>
					<div class="ewd-ufaq-dashboard-support-widgets-title"><?php _e('Get Support', 'ultimate-faqs'); ?></div>
					<div class="ewd-ufaq-dashboard-support-widgets-text-and-link">
						<div class="ewd-ufaq-dashboard-support-widgets-text"><span class="dashicons dashicons-star-empty"></span>Need more help? Get in touch.</div>
						<a class="ewd-ufaq-dashboard-support-widgets-link" href="https://www.etoilewebdesign.com/support-center/?utm_source=ufaq_dashboard&utm_content=icons_get_support" target="_blank"><?php _e('View', 'ultimate-faqs'); ?></a>
					</div>
				</li>
			</ul>
	
			<div class="ewd-ufaq-dashboard-catalogs">
				<div class="ewd-ufaq-dashboard-catalogs-title"><?php _e('FAQ Summary', 'ultimate-faqs'); ?></div>
				<table class='ewd-ufaq-overview-table wp-list-table widefat fixed striped posts'>
					<thead>
						<tr>
							<th><?php _e("Title", 'ultimate-faqs'); ?></th>
							<th><?php _e("Views", 'ultimate-faqs'); ?></th>
							<th><?php _e("Categories", 'ultimate-faqs'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
							if ( empty( $faqs ) ) {echo "<tr><td colspan='3'>" . __("No faqs to display yet. Create an faq and then view it for it to be displayed here.", 'ultimate-faqs') . "</td></tr>";}
							else {
								foreach ( $faqs as $faq ) { ?>
									<tr>
										<td><a href='post.php?post=<?php echo esc_attr( $faq->ID );?>&action=edit'><?php echo esc_html( $faq->post_title ); ?></a></td>
										<td><?php echo esc_html( get_post_meta( $faq->ID, 'ufaq_view_count', true ) ); ?></td>
										<td><?php echo get_the_term_list( $faq->ID, EWD_UFAQ_FAQ_CATEGORY_TAXONOMY ); ?></td>
									</tr>
								<?php }
							}
						?>
					</tbody>
				</table>
			</div>

			<?php if ( ! $permission or get_option("EWD_UFAQ_Trial_Happening") == "Yes" ) { ?>
				<div class="ewd-ufaq-dashboard-get-premium-and-trial<?php echo get_option( 'EWD_UFAQ_Trial_Happening' ) == 'Yes' ? ' trial-happening' : ''; ?>">
					<div id="ewd-ufaq-dashboard-new-footer-one">
						<div class="ewd-ufaq-dashboard-new-footer-one-inside">
							<div class="ewd-ufaq-dashboard-new-footer-one-left">
								<div class="ewd-ufaq-dashboard-new-footer-one-title">What's Included in Our Premium Version?</div>
								<ul class="ewd-ufaq-dashboard-new-footer-one-benefits">
									<li>Unlimited FAQs</li>
									<li>AI-Generated FAQs</li>
									<li>FAQ Search</li>
									<li>Custom Fields</li>
									<li>WooCommerce FAQs</li>
									<li>15 Different Icon Sets</li>
									<li>Import/Export FAQs</li>
									<li>Advanced Styling Options</li>
									<li>Email Support</li>
								</ul>
							</div>
							<div class="ewd-ufaq-dashboard-new-footer-one-buttons">
								<a class="ewd-ufaq-dashboard-new-upgrade-button" href="https://www.etoilewebdesign.com/license-payment/?Selected=UFAQ&Quantity=1&utm_source=ufaq_dashboard&utm_content=footer_upgrade" target="_blank">UPGRADE NOW</a>
								<?php if ( ! get_option("EWD_UFAQ_Trial_Happening") ) { 
									$trial_info = '';
									echo apply_filters( 'ewd_trial_button', $trial_info, 'UFAQ' );
								} ?>
							</div>
						</div>
					</div>
					<?php if ( get_option( "EWD_UFAQ_Trial_Happening" ) == "Yes" ) { ?>
						<div class="ewd-ufaq-dashboard-trial-container">
							<?php do_action( 'ewd_trial_happening', 'UFAQ' ); ?>
						</div>
					<?php } ?>
				</div>
			<?php } ?>	

			<div class="ewd-ufaq-dashboard-testimonials-and-other-plugins">

				<div class="ewd-ufaq-dashboard-testimonials-container">
					<div class="ewd-ufaq-dashboard-testimonials-container-title"><?php _e( 'What People Are Saying', 'ultimate-faqs' ); ?></div>
					<ul class="ewd-ufaq-dashboard-testimonials">
						<?php $randomTestimonial = rand(0,2);
						if($randomTestimonial == 0){ ?>
							<li id="ewd-ufaq-dashboard-testimonial-one">
								<img src="<?php echo plugins_url( '../assets/img/dash-asset-stars.png', __FILE__ ); ?>">
								<div class="ewd-ufaq-dashboard-testimonial-title">"Awesome. Just Awesome."</div>
								<div class="ewd-ufaq-dashboard-testimonial-author">- @shizart</div>
								<div class="ewd-ufaq-dashboard-testimonial-text">Thanks for this very well-made plugin. This works so well out of the box, I barely had to do ANYTHING to create an amazing FAQ accordion display... <a href="https://wordpress.org/support/topic/awesome-just-awesome-11/" target="_blank">read more</a></div>
							</li>
						<?php }
						if($randomTestimonial == 1){ ?>
							<li id="ewd-ufaq-dashboard-testimonial-two">
								<img src="<?php echo plugins_url( '../assets/img/dash-asset-stars.png', __FILE__ ); ?>">
								<div class="ewd-ufaq-dashboard-testimonial-title">"Absolutely perfect with great support"</div>
								<div class="ewd-ufaq-dashboard-testimonial-author">- @isaac85</div>
								<div class="ewd-ufaq-dashboard-testimonial-text">I tried several different FAQ plugins and this is by far the prettiest and easiest to use... <a href="https://wordpress.org/support/topic/absolutely-perfect-with-great-support/" target="_blank">read more</a></div>
							</li>
						<?php }
						if($randomTestimonial == 2){ ?>
							<li id="ewd-ufaq-dashboard-testimonial-three">
								<img src="<?php echo plugins_url( '../assets/img/dash-asset-stars.png', __FILE__ ); ?>">
								<div class="ewd-ufaq-dashboard-testimonial-title">"Perfect FAQ Plugin"</div>
								<div class="ewd-ufaq-dashboard-testimonial-author">- @muti-wp</div>
								<div class="ewd-ufaq-dashboard-testimonial-text">Works great! Easy to configure and to use. Thanks! <a href="https://wordpress.org/support/topic/perfect-faq-plugin/" target="_blank">read more</a></div>
							</li>
						<?php } ?>
					</ul>
				</div>

				<div class="ewd-ufaq-dashboard-other-plugins-container">
					<div class="ewd-ufaq-dashboard-other-plugins-container-title"><?php _e('Other plugins by Etoile', 'ultimate-faqs'); ?></div>
					<ul class="ewd-ufaq-dashboard-other-plugins">
						<li>
							<a href="https://wordpress.org/plugins/ultimate-reviews/" target="_blank"><img src="<?php echo plugins_url( '../assets/img/ewd-urp-icon.png', __FILE__ ); ?>"></a>
							<div class="ewd-ufaq-dashboard-other-plugins-text">
								<div class="ewd-ufaq-dashboard-other-plugins-title">Ultimate Reviews</div>
								<div class="ewd-ufaq-dashboard-other-plugins-blurb">Easily add a full reviewing system to your website. Also can be used to replace the default WooCommerce review system.</div>
							</div>
						</li>
						<li>
							<a href="https://wordpress.org/plugins/order-tracking/" target="_blank"><img src="<?php echo plugins_url( '../assets/img/ewd-otp-icon.png', __FILE__ ); ?>"></a>
							<div class="ewd-ufaq-dashboard-other-plugins-text">
								<div class="ewd-ufaq-dashboard-other-plugins-title">Order Tracking</div>
								<div class="ewd-ufaq-dashboard-other-plugins-blurb">Add a full order tracking system to your site. Integrates directly with WooCommerce for automatic order creation and status updates.</div>
							</div>
						</li>
					</ul>
				</div>

			</div>

			<?php if ( ! $permission or get_option("EWD_UFAQ_Trial_Happening") == "Yes" ) { ?>
				<div class="ewd-ufaq-dashboard-guarantee">
					<img src="<?php echo plugins_url( '../assets/img/dash-asset-badge.png', __FILE__ ); ?>" alt="14-Day 100% Money-Back Guarantee">
					<div class="ewd-ufaq-dashboard-guarantee-title-and-text">
						<div class="ewd-ufaq-dashboard-guarantee-title">14-Day 100% Money-Back Guarantee</div>
						<div class="ewd-ufaq-dashboard-guarantee-text">If you're not 100% satisfied with the premium version of our plugin - no problem. You have 14 days to receive a FULL REFUND. We're certain you won't need it, though.</div>
					</div>
				</div>
			<?php } ?>

		</div> <!-- ewd-ufaq-dashboard-content-area -->
		
		<div id="ewd-ufaq-dashboard-new-footer-two">
			<div class="ewd-ufaq-dashboard-new-footer-two-inside">
				<img src="<?php echo plugins_url( '../assets/img/ewd-logo-white.png', __FILE__ ); ?>" class="ewd-ufaq-dashboard-new-footer-two-icon">
				<div class="ewd-ufaq-dashboard-new-footer-two-blurb">
					At Etoile Web Design, we build reliable, easy-to-use WordPress plugins with a modern look. Rich in features, highly customizable and responsive, plugins by Etoile Web Design can be used as out-of-the-box solutions and can also be adapted to your specific requirements.
				</div>
				<ul class="ewd-ufaq-dashboard-new-footer-two-menu">
					<li>SUPPORT</li>
					<li><a href="https://www.youtube.com/watch?v=zf-tYLqHpRs&list=PLEndQUuhlvSrNdfu5FKa1uGHsaKZxgdWt&index=2&ab_channel=%C3%89toileWebDesign" target="_blank">YouTube Tutorials</a></li>
					<li><a href="https://doc.etoilewebdesign.com/plugins/ultimate-faq/user/?utm_source=ufaq_dashboard&utm_content=footer_documentation" target="_blank">Documentation</a></li>
					<li><a href="https://www.etoilewebdesign.com/support-center/?utm_source=ufaq_dashboard&utm_content=footer_get_support" target="_blank">Get Support</a></li>
					<li><a href="https://wordpress.org/plugins/ultimate-faqs/#faq" target="_blank">FAQs</a></li>
				</ul>
				<ul class="ewd-ufaq-dashboard-new-footer-two-menu">
					<li>SOCIAL</li>
					<li><a href="https://www.facebook.com/EtoileWebDesign/" target="_blank">Facebook</a></li>
					<li><a href="https://twitter.com/EtoileWebDesign" target="_blank">Twitter</a></li>
					<li><a href="https://www.etoilewebdesign.com/category/blog/?utm_source=ufaq_dashboard&utm_content=footer_blog" target="_blank">Blog</a></li>
				</ul>
			</div>
		</div> <!-- ewd-ufaq-dashboard-new-footer-two -->
		
	<?php }

}

} // endif
