<?php
/**
 * Comments template.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Never render comments for a post that is password protected and unlocked.
 */
if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="hv-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="hv-comments__title">
			<?php
			$bookingly_comment_count = get_comments_number();

			printf(
				esc_html(
					/* translators: %s: comment count */
					_n( '%s comment', '%s comments', $bookingly_comment_count, 'bookingly' )
				),
				esc_html( number_format_i18n( $bookingly_comment_count ) )
			);
			?>
		</h2>

		<ol class="hv-comments__list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>

		<?php
		the_comments_pagination(
			array(
				'prev_text' => __( 'Previous', 'bookingly' ),
				'next_text' => __( 'Next', 'bookingly' ),
				'class'     => 'hv-pagination',
			)
		);
		?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="hv-comments__closed"><?php esc_html_e( 'Comments are closed.', 'bookingly' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'class_form'         => 'hv-comment-form',
			'title_reply_before' => '<h2 class="hv-comments__title">',
			'title_reply_after'  => '</h2>',
			'submit_button'      => '<button type="submit" name="%1$s" id="%2$s" class="hv-btn hv-btn--primary">%4$s</button>',
		)
	);
	?>
</div>
