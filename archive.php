<?php
/**
 * Archive template.
 *
 * @package Bookingly
 */

get_header();
?>

<section class="hv-page-hero">
	<div class="hv-wrap">
		<span class="hv-eyebrow"><?php esc_html_e( 'Archive', 'bookingly' ); ?></span>
		<h1><?php the_archive_title(); ?></h1>
		<?php the_archive_description( '<p>', '</p>' ); ?>
	</div>
</section>

<section class="hv-section hv-section--flush-top">
	<div class="hv-wrap">
		<?php if ( have_posts() ) : ?>
			<div class="hv-grid hv-grid--3">
				<?php
				while ( have_posts() ) {
					the_post();
					echo bookingly_get_post_card( get_post() ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped
				}
				?>
			</div>

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
			<p class="hv-empty"><?php esc_html_e( 'Nothing found in this archive.', 'bookingly' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
