<?php
/**
 * Single blog post.
 *
 * @package Bookingly
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<section class="hv-page-hero">
		<div class="hv-wrap">
			<span class="hv-eyebrow"><?php esc_html_e( 'Article', 'bookingly' ); ?></span>
			<h1><?php the_title(); ?></h1>
			<div class="hv-post-meta" style="justify-content:center;margin-top:16px;">
				<span><?php bookingly_icon( 'calendar' ); ?> <?php echo esc_html( get_the_date() ); ?></span>
				<span>
					<?php bookingly_icon( 'clock' ); ?>
					<?php echo esc_html( sprintf( /* translators: %d: minutes */ __( '%d min read', 'bookingly' ), bookingly_reading_time( get_the_content() ) ) ); ?>
				</span>
			</div>
		</div>
	</section>

	<section class="hv-section hv-section--flush-top">
		<div class="hv-wrap hv-content-narrow">
			<?php if ( bookingly_option( 'blog.show_featured_image', true ) && has_post_thumbnail() ) : ?>
				<div class="hv-single-thumb"><?php the_post_thumbnail( 'bookingly-hero' ); ?></div>
			<?php endif; ?>

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
