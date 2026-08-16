<?php
/**
 * Header template.
 *
 * @package Bookingly
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="hv-skip-link" href="#primary"><?php esc_html_e( 'Skip to content', 'bookingly' ); ?></a>

<header class="hv-header">
	<nav class="hv-nav" aria-label="<?php esc_attr_e( 'Primary', 'bookingly' ); ?>">
		<?php get_template_part( 'template-parts/brand/logo' ); ?>

		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'hv-nav-links',
				'fallback_cb'    => 'bookingly_fallback_menu',
				'depth'          => 1,
			)
		);
		?>

		<div class="hv-nav-actions">
			<?php if ( bookingly_option( 'header.show_phone', true ) && bookingly_option( 'header.phone' ) ) : ?>
				<a class="hv-nav-phone" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', bookingly_option( 'header.phone' ) ) ); ?>">
					<?php bookingly_icon( 'phone' ); ?><?php echo esc_html( bookingly_option( 'header.phone' ) ); ?>
				</a>
			<?php endif; ?>

			<?php if ( bookingly_option( 'header.show_cta', true ) ) : ?>
				<a href="<?php echo esc_url( bookingly_get_book_now_url() ); ?>" class="hv-btn hv-btn--primary hv-btn--sm hv-nav-cta">
					<?php echo esc_html( bookingly_option( 'header.book_now_label', __( 'Book Now', 'bookingly' ) ) ); ?>
				</a>
			<?php endif; ?>

			<button class="hv-nav-toggle" type="button" aria-expanded="false" aria-controls="hv-mobile-nav">
				<span class="hv-sr-only"><?php esc_html_e( 'Menu', 'bookingly' ); ?></span>
				<?php bookingly_icon( 'menu-2' ); ?>
			</button>
		</div>
	</nav>

	<div id="hv-mobile-nav" class="hv-mobile-nav" hidden>
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'hv-mobile-menu',
				'fallback_cb'    => 'bookingly_fallback_menu',
				'depth'          => 1,
			)
		);
		?>
	</div>
</header>

<main id="primary" class="hv-site-main">
