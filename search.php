<?php
/**
 * Search results template.
 *
 * @package Bookingly
 */

get_header();
?>

<section class="hv-page-hero">
	<div class="hv-wrap">
		<span class="hv-eyebrow"><?php esc_html_e( 'Search', 'bookingly' ); ?></span>
		<h1>
			<?php
			printf(
				/* translators: %s: search query */
				esc_html__( 'Results for “%s”', 'bookingly' ),
				esc_html( get_search_query() )
			);
			?>
		</h1>
		<p>
			<?php
			printf(
				/* translators: %d: number of results */
				esc_html( _n( '%d result found.', '%d results found.', (int) $wp_query->found_posts, 'bookingly' ) ),
				(int) $wp_query->found_posts
			);
			?>
		</p>
	</div>
</section>

<section class="hv-section hv-section--flush-top">
	<div class="hv-wrap hv-content-narrow">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>
				<article <?php post_class( 'hv-search-item' ); ?>>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="hv-entry-content"><?php the_excerpt(); ?></div>
				</article>
			<?php endwhile; ?>

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
			<p class="hv-empty"><?php esc_html_e( 'No results found. Try a different search term.', 'bookingly' ); ?></p>
			<?php get_search_form(); ?>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
