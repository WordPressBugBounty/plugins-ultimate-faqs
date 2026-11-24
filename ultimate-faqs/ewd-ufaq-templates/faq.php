<div <?php echo ewd_format_classes( $this->classes ); ?> id='<?php echo esc_attr( $this->get_id() ); ?>' data-post_id='<?php echo esc_attr( $this->faq->post->ID ); ?>'>

	<?php global $ewd_ufaq_controller; ?>
	
	<?php $this->print_faq_title(); ?>

	<?php $this->maybe_print_faq_preview(); ?>

	<div <?php echo ewd_format_classes( $this->get_body_classes() ); ?>>

		<?php foreach ( $this->get_order_elements() as $element => $label ) { ?>

			<?php $this->maybe_print_element( $element ); ?>

		<?php } ?>

	</div>

</div>
