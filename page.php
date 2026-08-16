<?php
/**
 * Generic page template.
 *
 * @package Bookingly
 */

get_header();

if ( bookingly_use_page_builder_content() ) {
	bookingly_render_builder_content( true );
	get_footer();
	return;
}

/*
 * A reading measure is right for prose, and wrong for an application UI. An
 * integration that puts one on a page — WooCommerce cart and checkout, for
 * one — needs the full content width, so the wrapper is filterable.
 */
$bookingly_page_wrap = apply_filters( 'bookingly_page_content_class', 'hv-wrap hv-content-narrow', get_queried_object_id() );

while ( have_posts() ) :
	the_post();
	?>

	<section class="hv-page-hero">
		<div class="hv-wrap">
			<h1><?php the_title(); ?></h1>
		</div>
	</section>

	<section class="hv-section hv-section--flush-top">
		<div class="<?php echo esc_attr( $bookingly_page_wrap ); ?>">
			<article <?php post_class( 'hv-entry-content' ); ?>>
				<?php
				the_content();

				wp_link_pages(
					array(
						'before' => '<nav class="hv-pagination"><ul><li>',
						'after'  => '</li></ul></nav>',
					)
				);
				?>
			</article>

			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
		</div>
	</section>

	<?php
endwhile;

get_footer();
