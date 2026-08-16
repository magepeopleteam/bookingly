<?php
/**
 * 404 template.
 *
 * @package Bookingly
 */

get_header();

$services_page = bookingly_get_page_by_template( 'page-templates/template-services.php' );
?>

<section class="hv-page-hero">
	<div class="hv-wrap">
		<span class="hv-eyebrow"><?php esc_html_e( 'Error 404', 'bookingly' ); ?></span>
		<h1><?php esc_html_e( 'This page took a day off.', 'bookingly' ); ?></h1>
		<p><?php esc_html_e( 'The page you asked for could not be found. It may have moved, or the link may be out of date.', 'bookingly' ); ?></p>

		<p style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:28px;">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hv-btn hv-btn--primary"><?php esc_html_e( 'Back to Home', 'bookingly' ); ?></a>
			<?php if ( $services_page ) : ?>
				<a href="<?php echo esc_url( get_permalink( $services_page ) ); ?>" class="hv-btn hv-btn--ghost"><?php esc_html_e( 'Browse Services', 'bookingly' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</section>

<section class="hv-section hv-section--flush-top">
	<div class="hv-wrap hv-content-narrow">
		<?php get_search_form(); ?>
	</div>
</section>

<?php
get_footer();
