<?php
/**
 * WooCommerce shell.
 *
 * Once a theme declares WooCommerce support, this one file is used for every
 * shop archive, product taxonomy and single product. Cart, checkout and the
 * account pages are ordinary pages and keep going through page.php.
 *
 * @package Bookingly
 */

get_header();

/*
 * Single products print their own title inside the summary column, so a hero
 * title here would give the page two <h1>s saying the same thing.
 */
$bookingly_show_hero = ! is_singular( 'product' );

if ( $bookingly_show_hero ) :
	?>
	<section class="hv-page-hero">
		<div class="hv-wrap">
			<?php if ( is_shop() || is_product_taxonomy() ) : ?>
				<span class="hv-eyebrow"><?php esc_html_e( 'Shop', 'bookingly' ); ?></span>
			<?php endif; ?>

			<h1><?php woocommerce_page_title(); ?></h1>

			<?php
			$bookingly_term = is_product_taxonomy() ? get_queried_object() : null;
			if ( $bookingly_term && ! empty( $bookingly_term->description ) ) :
				?>
				<p><?php echo wp_kses_post( $bookingly_term->description ); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<?php
endif;

bookingly_woocommerce_wrapper_start( $bookingly_show_hero );
woocommerce_content();
bookingly_woocommerce_wrapper_end();

get_footer();
