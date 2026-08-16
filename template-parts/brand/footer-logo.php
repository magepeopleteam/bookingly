<?php
/**
 * Footer logo markup.
 *
 * @package Bookingly
 */

$footer_logo_id = (int) bookingly_option( 'brand.footer_logo_id', 0 );
$footer_icon    = bookingly_option( 'brand.footer_icon', 'spa' );
$show_name      = bookingly_option( 'brand.show_site_name', true );
$site_name      = bookingly_option( 'brand.site_name', '' );
$site_name      = $site_name ? $site_name : get_bloginfo( 'name' );
?>
<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hv-footer__logo" rel="home">
	<?php if ( $footer_logo_id ) : ?>
		<span class="hv-logo__img">
			<?php echo wp_get_attachment_image( $footer_logo_id, 'medium', false, array( 'alt' => esc_attr( $site_name ), 'loading' => 'lazy' ) ); ?>
		</span>
	<?php else : ?>
		<span class="hv-logo__mark"><?php bookingly_icon( $footer_icon ); ?></span>
	<?php endif; ?>

	<?php
	/*
	 * Matches the header: a logo image that already contains the wordmark would
	 * otherwise print the site name a second time right beside it.
	 */
	if ( $show_name || ! $footer_logo_id ) :
		?>
		<span><?php echo esc_html( $site_name ); ?></span>
	<?php else : ?>
		<span class="hv-sr-only"><?php echo esc_html( $site_name ); ?></span>
	<?php endif; ?>
</a>
