<?php
/**
 * Site logo markup.
 *
 * @package Bookingly
 */

$logo_id   = (int) bookingly_option( 'brand.logo_id', 0 );
$show_name = bookingly_option( 'brand.show_site_name', true );
$site_name = bookingly_option( 'brand.site_name', '' );
$site_name = $site_name ? $site_name : get_bloginfo( 'name' );

if ( ! $logo_id && has_custom_logo() ) {
	$logo_id = (int) get_theme_mod( 'custom_logo' );
}
?>
<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hv-logo" rel="home">
	<?php if ( $logo_id ) : ?>
		<span class="hv-logo__img">
			<?php
			echo wp_get_attachment_image(
				$logo_id,
				'medium',
				false,
				array(
					'alt'           => esc_attr( $site_name ),
					'fetchpriority' => 'high',
				)
			);
			?>
		</span>
	<?php else : ?>
		<span class="hv-logo__mark"><?php bookingly_icon( bookingly_option( 'brand.footer_icon', 'spa' ) ); ?></span>
	<?php endif; ?>

	<?php if ( $show_name ) : ?>
		<span class="hv-logo__text"><?php echo esc_html( $site_name ); ?></span>
	<?php elseif ( $logo_id ) : ?>
		<span class="hv-sr-only"><?php echo esc_html( $site_name ); ?></span>
	<?php endif; ?>
</a>
