<?php
/**
 * Template Name: Bookingly Contact
 * Template Post Type: page
 *
 * @package Bookingly
 */

get_header();

if ( bookingly_use_page_builder_content() ) {
	bookingly_render_builder_content( true );
	get_footer();
	return;
}

bookingly_section(
	'page_hero',
	array( 'eyebrow' => __( 'Get In Touch', 'bookingly' ) )
);
?>

<section class="hv-section hv-section--flush-top">
	<div class="hv-wrap">
		<?php bookingly_section( 'contact_cards', array( 'class' => 'hv-contact-cards' ) ); ?>

		<div class="hv-contact-layout">
			<?php bookingly_section( 'contact_form' ); ?>

			<div>
				<?php bookingly_section( 'map' ); ?>
				<?php bookingly_section( 'business_hours' ); ?>
			</div>
		</div>
	</div>
</section>

<?php
get_footer();
