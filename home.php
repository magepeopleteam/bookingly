<?php
/**
 * Blog posts index.
 *
 * @package Bookingly
 */

get_header();

$blog_page_id = (int) get_option( 'page_for_posts' );
$paged        = max( 1, (int) get_query_var( 'paged' ) );

/*
 * WordPress never renders the posts page's own content — the archive query
 * replaces it. So if an editor laid this page out with Bookingly blocks, render
 * that layout here instead of the built-in blog markup, otherwise editing the
 * Blog page would appear to do nothing.
 */
if ( $blog_page_id && ( bookingly_has_bookingly_blocks( $blog_page_id ) || bookingly_is_elementor_editing() ) ) {
	/*
	 * the_post() first so the_content() runs inside the loop: Elementor's
	 * editor looks for that call and refuses to open a page without it.
	 */
	global $post;
	$post = get_post( $blog_page_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the posts page renders its own content here.
	setup_postdata( $post );

	the_content();

	wp_reset_postdata();
	get_footer();
	return;
}
?>

<section class="hv-page-hero">
	<div class="hv-wrap">
		<?php if ( bookingly_option( 'blog.eyebrow' ) ) : ?>
			<span class="hv-eyebrow"><?php echo esc_html( bookingly_option( 'blog.eyebrow' ) ); ?></span>
		<?php endif; ?>
		<h1><?php echo esc_html( $blog_page_id ? get_the_title( $blog_page_id ) : bookingly_option( 'blog.title' ) ); ?></h1>
		<?php if ( bookingly_option( 'blog.description' ) ) : ?>
			<p><?php echo esc_html( bookingly_option( 'blog.description' ) ); ?></p>
		<?php endif; ?>
	</div>
</section>

<section class="hv-section hv-section--flush-top">
	<div class="hv-wrap">
		<?php if ( have_posts() ) : ?>
			<?php
			$post_items = array();
			while ( have_posts() ) {
				the_post();
				$post_items[] = get_post();
			}

			$featured = ( 1 === $paged ) ? array_shift( $post_items ) : null;

			if ( $featured ) {
				echo bookingly_get_featured_post_card( $featured ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped
			}
			?>

			<?php if ( ! empty( $post_items ) ) : ?>
				<div class="hv-grid hv-grid--3">
					<?php foreach ( $post_items as $post_item ) : ?>
						<?php echo bookingly_get_post_card( $post_item ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php
			the_posts_pagination(
				array(
					'type'      => 'list',
					'mid_size'  => 2,
					'prev_text' => __( 'Previous', 'bookingly' ),
					'next_text' => __( 'Next', 'bookingly' ),
					'class'     => 'hv-pagination',
				)
			);
			?>
		<?php else : ?>
			<p class="hv-empty"><?php esc_html_e( 'No posts published yet.', 'bookingly' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
bookingly_section( 'newsletter' );

wp_reset_postdata();
get_footer();
