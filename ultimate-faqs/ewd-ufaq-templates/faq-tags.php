<div class='ewd-ufaq-faq-tags'>
	
	<?php echo esc_html( $this->get_tags_label() ); ?>
	
	<?php foreach( $this->faq->tags as $key => $tag ) { ?>

		<?php echo $this->get_tag_value( $tag ) . ( $key != sizeOf( $this->faq->tags ) - 1 ? ', ' : '' ); ?>

	<?php } ?>

</div>