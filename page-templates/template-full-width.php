<?php
/**
 * Template Name: Bookingly Full Width
 * Template Post Type: page
 *
 * Theme header/footer with a wide content column for page builders.
 *
 * @package Bookingly
 */

get_header();

if ( bookingly_use_page_builder_content() ) {
	bookingly_render_builder_content( true );
} else {
	while ( have_posts() ) :
		the_post();
		?>
		<section class="hv-page-hero">
			<div class="hv-wrap">
				<h1><?php the_title(); ?></h1>
			</div>
		</section>

		<section class="hv-section hv-section--flush-top">
			<div class="hv-wrap">
				<article <?php post_class( 'hv-entry-content' ); ?>>
					<?php the_content(); ?>
				</article>
			</div>
		</section>
		<?php
	endwhile;
}

get_footer();
