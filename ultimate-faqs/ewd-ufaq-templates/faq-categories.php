<div class='ewd-ufaq-faq-categories'>
	
	<?php echo esc_html( $this->get_categories_label() ); ?>

	<?php foreach( $this->faq->categories as $key => $category ) { ?>

		<?php echo $this->get_category_value( $category ) . ( $key != sizeOf( $this->faq->categories ) - 1 ? ', ' : '' ); ?>

	<?php } ?>

</div>