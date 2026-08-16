<?php
/**
 * Fallback index template.
 *
 * @package Bookingly
 */

get_header();
?>

<section class="hv-section">
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
			<p class="hv-empty"><?php esc_html_e( 'Nothing found.', 'bookingly' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
