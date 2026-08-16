<?php
/**
 * Footer template.
 *
 * @package Bookingly
 */

$services_page = bookingly_get_page_by_template( 'page-templates/template-services.php' );
$about_page    = bookingly_get_page_by_template( 'page-templates/template-about.php' );
$contact_page  = bookingly_get_page_by_template( 'page-templates/template-contact.php' );
$blog_page_id  = (int) get_option( 'page_for_posts' );

$services_query = null;
if ( bookingly_option( 'footer.show_service_links', true ) ) {
	$services_query = bookingly_get_services_query(
		array(
			'posts_per_page' => (int) bookingly_option( 'footer.services_limit', 4 ),
		)
	);
}

$copyright = bookingly_option( 'footer.copyright', '' );
if ( ! $copyright ) {
	$copyright = sprintf(
		/* translators: %s: site name */
		__( '%s. All rights reserved.', 'bookingly' ),
		get_bloginfo( 'name' )
	);
}

$privacy_url = bookingly_option( 'footer.privacy_url', '' );
$terms_url   = bookingly_option( 'footer.terms_url', '' );

$social_links = array(
	array( 'url' => bookingly_option( 'social.facebook', '' ), 'icon' => 'brand-facebook', 'label' => __( 'Facebook', 'bookingly' ) ),
	array( 'url' => bookingly_option( 'social.instagram', '' ), 'icon' => 'brand-instagram', 'label' => __( 'Instagram', 'bookingly' ) ),
	array( 'url' => bookingly_option( 'social.x', '' ), 'icon' => 'brand-x', 'label' => __( 'X', 'bookingly' ) ),
	array( 'url' => bookingly_option( 'social.linkedin', '' ), 'icon' => 'brand-linkedin', 'label' => __( 'LinkedIn', 'bookingly' ) ),
);
$social_links = array_filter(
	$social_links,
	static function ( $link ) {
		return ! empty( $link['url'] );
	}
);

$explore_links = array_filter(
	array(
		array( 'page' => $services_page, 'label' => __( 'Services', 'bookingly' ) ),
		array( 'page' => $about_page, 'label' => __( 'About', 'bookingly' ) ),
		array( 'page' => $blog_page_id ? get_post( $blog_page_id ) : null, 'label' => __( 'Blog', 'bookingly' ) ),
		array( 'page' => $contact_page, 'label' => __( 'Contact', 'bookingly' ) ),
	),
	static function ( $link ) {
		return ! empty( $link['page'] );
	}
);
?>
</main>

<footer class="hv-footer">
	<div class="hv-wrap">
		<div class="hv-footer__grid">
			<div>
				<?php get_template_part( 'template-parts/brand/footer-logo' ); ?>
				<p class="hv-footer__desc"><?php echo esc_html( bookingly_option( 'footer.description' ) ); ?></p>

				<?php if ( bookingly_option( 'footer.show_social', true ) && ! empty( $social_links ) ) : ?>
					<div class="hv-footer__social">
						<?php foreach ( $social_links as $social ) : ?>
							<a href="<?php echo esc_url( $social['url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<span class="hv-sr-only"><?php echo esc_html( $social['label'] ); ?></span>
								<?php bookingly_icon( $social['icon'] ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $explore_links ) ) : ?>
				<nav aria-labelledby="hv-footer-explore">
					<h2 id="hv-footer-explore"><?php echo esc_html( bookingly_option( 'footer.explore_heading', __( 'Explore', 'bookingly' ) ) ); ?></h2>
					<ul>
						<?php foreach ( $explore_links as $link ) : ?>
							<li><a href="<?php echo esc_url( get_permalink( $link['page'] ) ); ?>"><?php echo esc_html( $link['label'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endif; ?>

			<?php if ( bookingly_option( 'footer.show_service_links', true ) ) : ?>
			<nav aria-labelledby="hv-footer-services">
				<h2 id="hv-footer-services"><?php echo esc_html( bookingly_option( 'footer.services_heading', __( 'Services', 'bookingly' ) ) ); ?></h2>
				<ul>
					<?php if ( $services_query && $services_query->have_posts() ) : ?>
						<?php foreach ( $services_query->posts as $service_post ) : ?>
							<li><a href="<?php echo esc_url( get_permalink( $service_post ) ); ?>"><?php echo esc_html( get_the_title( $service_post ) ); ?></a></li>
						<?php endforeach; ?>
					<?php else : ?>
						<li><a href="<?php echo esc_url( $services_page ? get_permalink( $services_page ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'Browse services', 'bookingly' ); ?></a></li>
					<?php endif; ?>
				</ul>
			</nav>
			<?php endif; ?>

			<?php if ( bookingly_option( 'footer.show_contact', true ) ) : ?>
			<div>
				<h2><?php echo esc_html( bookingly_option( 'footer.contact_heading', __( 'Contact', 'bookingly' ) ) ); ?></h2>
				<ul class="hv-footer__contact">
					<?php if ( bookingly_option( 'contact.address' ) ) : ?>
						<li><?php bookingly_icon( 'map-pin' ); ?><span><?php echo esc_html( bookingly_option( 'contact.address' ) ); ?></span></li>
					<?php endif; ?>
					<?php if ( bookingly_option( 'header.phone' ) ) : ?>
						<li><?php bookingly_icon( 'phone' ); ?><span><?php echo esc_html( bookingly_option( 'header.phone' ) ); ?></span></li>
					<?php endif; ?>
					<?php if ( bookingly_option( 'contact.email' ) ) : ?>
						<li><?php bookingly_icon( 'mail' ); ?><span><?php echo esc_html( bookingly_option( 'contact.email' ) ); ?></span></li>
					<?php endif; ?>
				</ul>
			</div>
			<?php endif; ?>
		</div>

		<div class="hv-footer__bottom">
			<span>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( $copyright ); ?></span>
			<?php if ( $privacy_url || $terms_url ) : ?>
				<span>
					<?php if ( $privacy_url ) : ?>
						<a href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Privacy Policy', 'bookingly' ); ?></a>
					<?php endif; ?>
					<?php if ( $privacy_url && $terms_url ) : ?> &middot; <?php endif; ?>
					<?php if ( $terms_url ) : ?>
						<a href="<?php echo esc_url( $terms_url ); ?>"><?php esc_html_e( 'Terms of Service', 'bookingly' ); ?></a>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
