<?php
/**
 * Section render callbacks.
 *
 * Every callback is named bookingly_section_{id} and receives attributes already
 * resolved by bookingly_section_atts(). These functions own the theme's markup;
 * templates, shortcodes, blocks, Elementor and Divi all route through here.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service card markup used by the services grid and the homepage.
 *
 * @param int                 $post_id Service post ID.
 * @param array<string,mixed> $display Optional display controls.
 * @return string
 */
function bookingly_get_service_card( $post_id, $display = array() ) {
	$filter_slug = bookingly_get_service_filter_slug( $post_id );
	$subtitle    = bookingly_get_service_subtitle( $post_id );
	$price       = bookingly_get_service_min_price( $post_id );
	$icon        = bookingly_get_service_icon( $post_id );
	$show_images = array_key_exists( 'show_images', $display ) ? ! empty( $display['show_images'] ) : (bool) bookingly_option( 'services.show_images', true );
	$show_prices = array_key_exists( 'show_prices', $display ) ? ! empty( $display['show_prices'] ) : (bool) bookingly_option( 'services.show_prices', true );

	ob_start();
	?>
	<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="hv-service-card<?php echo $show_images ? '' : ' hv-service-card--no-image'; ?>" data-cat="<?php echo esc_attr( $filter_slug ); ?>">
		<?php if ( $show_images ) : ?>
		<div class="hv-service-card__thumb">
			<?php
			if ( has_post_thumbnail( $post_id ) ) {
				echo get_the_post_thumbnail( $post_id, 'bookingly-card', array( 'loading' => 'lazy' ) );
			} else {
				printf(
					'<img src="%1$s" alt="" width="640" height="420" loading="lazy">',
					esc_url( BOOKINGLY_URI . '/assets/images/service-placeholder.svg' )
				);
			}
			?>
			<span class="hv-service-card__chip"><?php bookingly_icon( $icon ); ?></span>
		</div>
		<?php endif; ?>
		<div class="hv-service-card__body">
			<?php if ( ! $show_images ) : ?><span class="hv-service-card__chip hv-service-card__chip--inline"><?php bookingly_icon( $icon ); ?></span><?php endif; ?>
			<h3><?php echo esc_html( get_the_title( $post_id ) ); ?></h3>
			<?php if ( $subtitle ) : ?>
				<p><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
			<div class="hv-service-card__foot">
				<?php if ( $show_prices ) : ?><span class="hv-service-card__price">
					<?php esc_html_e( 'Starting at', 'bookingly' ); ?>
					<b><?php echo $price ? wp_kses_post( $price ) : esc_html__( 'Quote', 'bookingly' ); ?></b>
				</span><?php endif; ?>
				<span class="hv-service-card__go"><?php bookingly_icon( 'arrow-right' ); ?></span>
			</div>
		</div>
	</a>
	<?php
	return ob_get_clean();
}

/**
 * Echo a service card. Kept for template-level use.
 *
 * @param int $post_id Service post ID.
 */
function bookingly_render_service_card( $post_id ) {
	echo bookingly_get_service_card( $post_id ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped in builder.
}

/**
 * Hero.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_hero( $a ) {
	$booking_url = ! empty( $a['cta_url'] ) ? $a['cta_url'] : bookingly_get_book_now_url();
	$stats       = isset( $a['stats'] ) ? (array) $a['stats'] : array();
	$services    = array();
	if ( ! empty( $a['show_card'] ) ) {
		$limit    = max( 1, min( 12, absint( $a['slide_limit'] ?? 6 ) ) );
		$query    = bookingly_get_services_query( array( 'posts_per_page' => $limit ) );
		$services = $query->posts;
	}

	$slide_count   = count( $services );
	$slider_active = $slide_count > 1;
	$carousel_id   = wp_unique_id( 'hv-hero-carousel-' );
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, 'hv-hero' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap hv-hero__inner">
			<div class="hv-hero__copy">
				<?php if ( $a['eyebrow'] ) : ?>
					<span class="hv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span>
				<?php endif; ?>
				<h1>
					<?php echo esc_html( $a['title_before'] ); ?>
					<?php if ( $a['title_emphasis'] ) : ?>
						<em><?php echo esc_html( $a['title_emphasis'] ); ?></em>
					<?php endif; ?>
					<?php echo esc_html( $a['title_after'] ); ?>
				</h1>
				<?php if ( $a['lead'] ) : ?>
					<p class="hv-hero__lead"><?php echo esc_html( $a['lead'] ); ?></p>
				<?php endif; ?>

				<div class="hv-hero__cta">
					<?php if ( $a['cta_label'] ) : ?>
						<a href="<?php echo esc_url( $booking_url ); ?>" class="hv-btn hv-btn--primary">
							<?php echo esc_html( $a['cta_label'] ); ?><?php bookingly_icon( 'arrow-up-right' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $a['cta2_label'] ) : ?>
						<a href="<?php echo esc_url( $a['cta2_url'] ? $a['cta2_url'] : '#how' ); ?>" class="hv-btn hv-btn--ghost"><?php echo esc_html( $a['cta2_label'] ); ?></a>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $a['show_stats'] ) && ! empty( $stats ) ) : ?>
					<div class="hv-hero__trust">
						<?php foreach ( $stats as $stat ) : ?>
							<?php if ( empty( $stat['value'] ) && empty( $stat['label'] ) ) { continue; } ?>
							<div class="hv-stat">
								<b>
									<?php echo esc_html( $stat['value'] ?? '' ); ?>
									<?php if ( ! empty( $stat['show_star'] ) ) { bookingly_icon( 'star-filled' ); } ?>
								</b>
								<span><?php echo esc_html( $stat['label'] ?? '' ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $a['show_card'] ) ) : ?>
				<div
					id="<?php echo esc_attr( $carousel_id ); ?>"
					class="hv-hero__card<?php echo $slider_active ? ' is-slider' : ''; ?>"
					<?php if ( $slider_active ) : ?>
						data-hv-hero-slider
						data-interval="5000"
						role="region"
						aria-roledescription="<?php esc_attr_e( 'carousel', 'bookingly' ); ?>"
						aria-label="<?php esc_attr_e( 'Featured services', 'bookingly' ); ?>"
					<?php endif; ?>
				>
					<?php if ( $a['badge_text'] ) : ?>
						<div class="hv-hero__badge"><?php bookingly_icon( 'circle-check-filled' ); ?> <?php echo esc_html( $a['badge_text'] ); ?></div>
					<?php endif; ?>

					<div class="hv-hero__slides">
						<?php if ( $services ) : ?>
							<?php foreach ( $services as $index => $service ) : ?>
								<?php
								$slide_id = $carousel_id . '-slide-' . ( $index + 1 );
								$title    = get_the_title( $service );
								$price    = bookingly_get_service_min_price( $service->ID );
								$label    = sprintf(
									/* translators: 1: current slide number, 2: total slides, 3: service title. */
									__( 'Service %1$d of %2$d: %3$s', 'bookingly' ),
									$index + 1,
									$slide_count,
									$title
								);
								?>
								<article
									id="<?php echo esc_attr( $slide_id ); ?>"
									class="hv-hero__slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
									data-hv-hero-slide
									role="group"
									aria-roledescription="<?php esc_attr_e( 'slide', 'bookingly' ); ?>"
									aria-label="<?php echo esc_attr( $label ); ?>"
									aria-hidden="<?php echo 0 === $index ? 'false' : 'true'; ?>"
									<?php echo 0 === $index ? '' : 'inert'; ?>
								>
									<?php
									if ( has_post_thumbnail( $service ) ) {
										echo get_the_post_thumbnail(
											$service,
											'bookingly-hero',
											0 === $index ? array( 'fetchpriority' => 'high' ) : array( 'loading' => 'lazy' )
										);
									} else {
										bookingly_image(
											0 === $index ? (int) $a['image'] : 0,
											'bookingly-hero',
											0 === $index ? array( 'alt' => '', 'fetchpriority' => 'high' ) : array( 'alt' => '', 'loading' => 'lazy' ),
											BOOKINGLY_URI . '/assets/images/service-placeholder.svg'
										);
									}
									?>
									<div class="hv-hero__card-body">
										<div class="hv-hero__card-title">
											<h2><?php echo esc_html( $title ); ?></h2>
											<span class="hv-price-tag"><?php echo $price ? esc_html__( 'from', 'bookingly' ) . ' ' . wp_kses_post( $price ) : esc_html__( 'Book now', 'bookingly' ); ?></span>
										</div>
										<div class="hv-hero__card-day"><?php echo esc_html( wp_date( 'l j M' ) ); ?></div>
									</div>
								</article>
							<?php endforeach; ?>
						<?php else : ?>
							<article class="hv-hero__slide is-active" data-hv-hero-slide>
								<?php
								bookingly_image(
									(int) $a['image'],
									'bookingly-hero',
									array( 'alt' => '', 'fetchpriority' => 'high' ),
									BOOKINGLY_URI . '/assets/images/service-placeholder.svg'
								);
								?>
								<div class="hv-hero__card-body">
									<div class="hv-hero__card-title">
										<h2><?php esc_html_e( 'Your first service', 'bookingly' ); ?></h2>
										<span class="hv-price-tag"><?php esc_html_e( 'Book now', 'bookingly' ); ?></span>
									</div>
									<div class="hv-hero__card-day"><?php echo esc_html( wp_date( 'l j M' ) ); ?></div>
								</div>
							</article>
						<?php endif; ?>
					</div>

					<?php if ( $slider_active ) : ?>
						<div class="hv-hero__slider-footer">
							<div class="hv-timeline" role="group" aria-label="<?php esc_attr_e( 'Choose a featured service', 'bookingly' ); ?>">
								<?php foreach ( $services as $index => $service ) : ?>
									<?php
									$dot_label = sprintf(
										/* translators: 1: service number, 2: service title. */
										__( 'Show service %1$d: %2$s', 'bookingly' ),
										$index + 1,
										get_the_title( $service )
									);
									?>
									<button type="button" class="hv-hero__dot<?php echo 0 === $index ? ' is-on' : ''; ?>" data-hv-hero-dot="<?php echo esc_attr( $index ); ?>" aria-controls="<?php echo esc_attr( $carousel_id . '-slide-' . ( $index + 1 ) ); ?>" aria-label="<?php echo esc_attr( $dot_label ); ?>" aria-current="<?php echo 0 === $index ? 'true' : 'false'; ?>" data-announcement="<?php echo esc_attr( $dot_label ); ?>"></button>
								<?php endforeach; ?>
							</div>
							<button
								type="button"
								class="hv-hero__slider-toggle"
								data-hv-hero-toggle
								data-pause-label="<?php esc_attr_e( 'Pause featured services', 'bookingly' ); ?>"
								data-play-label="<?php esc_attr_e( 'Play featured services', 'bookingly' ); ?>"
								data-reduced-label="<?php esc_attr_e( 'Autoplay is disabled by reduced-motion preferences', 'bookingly' ); ?>"
								aria-label="<?php esc_attr_e( 'Pause featured services', 'bookingly' ); ?>"
								aria-pressed="false"
							><span aria-hidden="true">Ⅱ</span></button>
						</div>
						<p class="hv-sr-only" data-hv-hero-status aria-live="polite" aria-atomic="true"></p>
					<?php else : ?>
						<div class="hv-timeline" aria-hidden="true">
							<i class="is-on"></i><i class="is-on"></i><i></i><i></i><i class="is-accent"></i>
							<i></i><i></i><i class="is-on"></i><i></i><i></i>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * Trust strip.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_trust_strip( $a ) {
	$items = bookingly_lines_to_list( $a['items'] );
	if ( empty( $items ) ) {
		return;
	}
	?>
	<div<?php echo bookingly_section_wrapper_attrs( $a, 'hv-strip' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap hv-strip__inner">
			<?php foreach ( $items as $item ) : ?>
				<span><?php echo esc_html( $item ); ?></span>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Services grid.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_services( $a ) {
	$limit = (int) $a['limit'];
	$query = bookingly_get_services_query( array( 'posts_per_page' => 0 === $limit ? -1 : $limit ) );

	$services_page = bookingly_get_page_by_template( 'page-templates/template-services.php' );
	$button_url    = ! empty( $a['button_url'] ) ? $a['button_url'] : ( $services_page ? get_permalink( $services_page ) : '' );

	$columns = in_array( (string) $a['columns'], array( '2', '3', '4' ), true ) ? (string) $a['columns'] : '3';

	$filters = array();
	if ( ! empty( $a['show_filter'] ) ) {
		foreach ( $query->posts as $service_post ) {
			$slug = bookingly_get_service_filter_slug( $service_post->ID );
			if ( $slug ) {
				$filters[ $slug ] = ucwords( str_replace( '-', ' ', $slug ) );
			}
		}
	}

	$grid_id = wp_unique_id( 'hv-services-' );
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, 'hv-section hv-services' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap">
			<?php if ( ! empty( $a['show_head'] ) && ( $a['eyebrow'] || $a['title'] || $a['description'] ) ) : ?>
				<div class="hv-section-head">
					<?php if ( $a['eyebrow'] ) : ?><span class="hv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span><?php endif; ?>
					<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
					<?php if ( $a['description'] ) : ?><p><?php echo esc_html( $a['description'] ); ?></p><?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $filters ) ) : ?>
				<div class="hv-filter-bar" data-hv-filter="<?php echo esc_attr( $grid_id ); ?>" role="group" aria-label="<?php esc_attr_e( 'Filter services by category', 'bookingly' ); ?>">
					<button type="button" class="hv-filter-chip" data-filter="all" aria-pressed="true"><?php esc_html_e( 'All', 'bookingly' ); ?></button>
					<?php foreach ( $filters as $slug => $label ) : ?>
						<button type="button" class="hv-filter-chip" data-filter="<?php echo esc_attr( $slug ); ?>" aria-pressed="false"><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>
				<p class="hv-sr-only" data-hv-filter-status aria-live="polite"></p>
			<?php endif; ?>

			<div class="hv-grid hv-grid--<?php echo esc_attr( $columns ); ?>" id="<?php echo esc_attr( $grid_id ); ?>">
				<?php if ( $query->have_posts() ) : ?>
					<?php foreach ( $query->posts as $service_post ) : ?>
						<?php echo bookingly_get_service_card( $service_post->ID, array( 'show_images' => $a['show_images'], 'show_prices' => $a['show_prices'] ) ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="hv-empty"><?php esc_html_e( 'No services published yet. Add services in Service Booking Manager and they will appear here.', 'bookingly' ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $a['show_button'] ) && $button_url && $a['button_label'] ) : ?>
				<p style="text-align:center;margin-top:44px;">
					<a href="<?php echo esc_url( $button_url ); ?>" class="hv-btn hv-btn--ghost"><?php echo esc_html( $a['button_label'] ); ?><?php bookingly_icon( 'arrow-right' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * How it works.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_how_it_works( $a ) {
	$steps = isset( $a['steps'] ) ? (array) $a['steps'] : array();
	$steps = array_filter(
		$steps,
		static function ( $step ) {
			return ! empty( $step['title'] ) || ! empty( $step['text'] );
		}
	);

	if ( empty( $steps ) ) {
		return;
	}

	$classes = 'hv-section hv-how' . ( ! empty( $a['tint'] ) ? ' hv-section--tint' : '' );
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, $classes ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap">
			<?php if ( $a['eyebrow'] || $a['title'] ) : ?>
				<div class="hv-section-head hv-section-head--center">
					<?php if ( $a['eyebrow'] ) : ?><span class="hv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span><?php endif; ?>
					<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="hv-how__grid">
				<?php foreach ( $steps as $step ) : ?>
					<div class="hv-how__step">
						<div class="hv-how__num"><?php echo esc_html( $step['number'] ?? '' ); ?></div>
						<h3><?php echo esc_html( $step['title'] ?? '' ); ?></h3>
						<p><?php echo esc_html( $step['text'] ?? '' ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * About preview.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_about( $a ) {
	$points     = bookingly_lines_to_list( $a['points'] );
	$about_page = bookingly_get_page_by_template( 'page-templates/template-about.php' );
	$button_url = ! empty( $a['button_url'] ) ? $a['button_url'] : ( $about_page ? get_permalink( $about_page ) : '' );
	$icons      = array( 'shield-check', 'calendar-time', 'refresh', 'lock' );
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, 'hv-section hv-about' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap hv-about__inner">
			<div class="hv-about__media">
				<?php
				bookingly_image(
					(int) $a['image'],
					'bookingly-hero',
					array( 'alt' => '', 'loading' => 'lazy' ),
					BOOKINGLY_URI . '/assets/images/post-placeholder.svg'
				);
				?>
				<?php if ( $a['pill_value'] || $a['pill_text'] ) : ?>
					<div class="hv-pill">
						<b><?php echo esc_html( $a['pill_value'] ); ?></b>
						<span><?php echo esc_html( $a['pill_text'] ); ?></span>
					</div>
				<?php endif; ?>
			</div>
			<div class="hv-about__body">
				<?php if ( $a['eyebrow'] ) : ?><span class="hv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span><?php endif; ?>
				<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
				<?php if ( $a['text'] ) : ?><p><?php echo esc_html( $a['text'] ); ?></p><?php endif; ?>

				<?php if ( ! empty( $points ) ) : ?>
					<div class="hv-about__points">
						<?php foreach ( $points as $index => $point ) : ?>
							<div class="hv-about__point">
								<?php bookingly_icon( $icons[ $index % count( $icons ) ] ); ?>
								<span><?php echo esc_html( $point ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $button_url && $a['button_label'] ) : ?>
					<a href="<?php echo esc_url( $button_url ); ?>" class="hv-btn hv-btn--primary"><?php echo esc_html( $a['button_label'] ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Testimonials.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_testimonials( $a ) {
	$items = array_filter(
		isset( $a['items'] ) ? (array) $a['items'] : array(),
		static function ( $item ) {
			return ! empty( $item['quote'] );
		}
	);

	if ( empty( $items ) ) {
		return;
	}
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, 'hv-section hv-section--dark hv-testimonials' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap">
			<?php if ( $a['eyebrow'] || $a['title'] || $a['description'] ) : ?>
				<div class="hv-section-head hv-section-head--center">
					<?php if ( $a['eyebrow'] ) : ?><span class="hv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span><?php endif; ?>
					<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
					<?php if ( $a['description'] ) : ?><p><?php echo esc_html( $a['description'] ); ?></p><?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="hv-grid hv-grid--3">
				<?php foreach ( $items as $item ) : ?>
					<figure class="hv-testimonial">
						<div class="hv-testimonial__stars" role="img" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: star rating out of five */ __( '%d out of 5 stars', 'bookingly' ), (int) ( $item['stars'] ?? 5 ) ) ); ?>">
							<?php echo esc_html( bookingly_stars( (int) ( $item['stars'] ?? 5 ) ) ); ?>
						</div>
						<blockquote><p><?php echo esc_html( $item['quote'] ?? '' ); ?></p></blockquote>
						<figcaption class="hv-testimonial__person">
							<?php
							if ( ! empty( $item['avatar_id'] ) ) {
								echo wp_get_attachment_image( (int) $item['avatar_id'], 'thumbnail', false, array( 'alt' => '', 'loading' => 'lazy' ) );
							} else {
								printf(
									'<img src="%s" alt="" width="42" height="42" loading="lazy">',
									esc_url( BOOKINGLY_URI . '/assets/images/avatar-placeholder.svg' )
								);
							}
							?>
							<span>
								<b><?php echo esc_html( $item['name'] ?? '' ); ?></b>
								<span><?php echo esc_html( $item['role'] ?? '' ); ?></span>
							</span>
						</figcaption>
					</figure>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Call-to-action band.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_cta_band( $a ) {
	$url = ! empty( $a['button_url'] ) ? $a['button_url'] : bookingly_get_book_now_url();
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, 'hv-section' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap">
			<div class="hv-cta-band">
				<div>
					<?php if ( $a['eyebrow'] ) : ?><span class="hv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span><?php endif; ?>
					<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
				</div>
				<?php if ( $a['button_label'] ) : ?>
					<a href="<?php echo esc_url( $url ); ?>" class="hv-btn hv-btn--primary"><?php echo esc_html( $a['button_label'] ); ?><?php bookingly_icon( 'arrow-up-right' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Statistics band.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_stats( $a ) {
	$items = array_filter(
		isset( $a['items'] ) ? (array) $a['items'] : array(),
		static function ( $item ) {
			return ! empty( $item['value'] );
		}
	);

	if ( empty( $items ) ) {
		return;
	}
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, 'hv-section hv-section--dark' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap">
			<div class="hv-stats-grid">
				<?php foreach ( $items as $item ) : ?>
					<div>
						<b><?php echo esc_html( $item['value'] ); ?></b>
						<span><?php echo esc_html( $item['label'] ?? '' ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Value cards.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_value_props( $a ) {
	$items = array_filter(
		isset( $a['items'] ) ? (array) $a['items'] : array(),
		static function ( $item ) {
			return ! empty( $item['title'] );
		}
	);

	if ( empty( $items ) ) {
		return;
	}

	$classes = 'hv-section' . ( ! empty( $a['tint'] ) ? ' hv-section--tint' : '' );
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, $classes ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap">
			<?php if ( $a['eyebrow'] || $a['title'] ) : ?>
				<div class="hv-section-head hv-section-head--center">
					<?php if ( $a['eyebrow'] ) : ?><span class="hv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span><?php endif; ?>
					<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="hv-grid hv-grid--4">
				<?php foreach ( $items as $item ) : ?>
					<div class="hv-value-card">
						<?php bookingly_icon( $item['icon'] ?? 'shield-check' ); ?>
						<h3><?php echo esc_html( $item['title'] ); ?></h3>
						<p><?php echo esc_html( $item['text'] ?? '' ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Team grid.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_team( $a ) {
	$items = array_filter(
		isset( $a['items'] ) ? (array) $a['items'] : array(),
		static function ( $item ) {
			return ! empty( $item['name'] );
		}
	);

	if ( empty( $items ) ) {
		return;
	}
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, 'hv-section' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap">
			<?php if ( $a['eyebrow'] || $a['title'] ) : ?>
				<div class="hv-section-head hv-section-head--center">
					<?php if ( $a['eyebrow'] ) : ?><span class="hv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span><?php endif; ?>
					<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="hv-grid hv-grid--4">
				<?php foreach ( $items as $item ) : ?>
					<div class="hv-team-card">
						<div class="hv-team-card__photo">
							<?php
							if ( ! empty( $item['photo_id'] ) ) {
								echo wp_get_attachment_image( (int) $item['photo_id'], 'bookingly-card', false, array( 'alt' => '', 'loading' => 'lazy' ) );
							} else {
								printf(
									'<img src="%s" alt="" width="160" height="160" loading="lazy">',
									esc_url( BOOKINGLY_URI . '/assets/images/avatar-placeholder.svg' )
								);
							}
							?>
						</div>
						<h3><?php echo esc_html( $item['name'] ); ?></h3>
						<span><?php echo esc_html( $item['role'] ?? '' ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Story block.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_story( $a ) {
	$images = array(
		(int) $a['image_1'],
		(int) $a['image_2'],
		(int) $a['image_3'],
	);
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, 'hv-section hv-story' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap hv-story__inner">
			<div class="hv-story__media">
				<?php foreach ( $images as $image_id ) : ?>
					<?php
					bookingly_image(
						$image_id,
						'bookingly-card',
						array( 'alt' => '', 'loading' => 'lazy' ),
						BOOKINGLY_URI . '/assets/images/post-placeholder.svg'
					);
					?>
				<?php endforeach; ?>
				<?php if ( $a['pill_value'] || $a['pill_text'] ) : ?>
					<div class="hv-pill">
						<b><?php echo esc_html( $a['pill_value'] ); ?></b>
						<span><?php echo esc_html( $a['pill_text'] ); ?></span>
					</div>
				<?php endif; ?>
			</div>
			<div class="hv-story__body">
				<?php if ( $a['eyebrow'] ) : ?><span class="hv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span><?php endif; ?>
				<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
				<?php
				/*
				 * Falling back to the page content is only safe when this
				 * section was rendered by a PHP template. Inside the_content()
				 * — i.e. when Story is itself a block on the page — calling
				 * the_content() again re-parses the same blocks and renders
				 * this section forever, which exhausts memory.
				 */
				if ( $a['text'] ) {
					// Blank lines start a new paragraph so a multi-paragraph
					// story can be written in Theme Options.
					foreach ( preg_split( '/\n\s*\n/', trim( (string) $a['text'] ) ) as $paragraph ) {
						if ( '' !== trim( $paragraph ) ) {
							echo '<p>' . nl2br( esc_html( trim( $paragraph ) ), false ) . '</p>';
						}
					}
				} elseif ( is_singular() && ! doing_filter( 'the_content' ) && get_the_content() ) {
					echo '<div class="hv-entry-content">';
					the_content();
					echo '</div>';
				}
				?>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Page hero.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_page_hero( $a ) {
	$title = $a['title'] ? $a['title'] : get_the_title();
	$text  = $a['text'];

	if ( ! $text && is_singular() ) {
		$excerpt = get_the_excerpt();
		$text    = $excerpt ? wp_strip_all_tags( $excerpt ) : '';
	}
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, 'hv-page-hero' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap">
			<?php if ( $a['eyebrow'] ) : ?><span class="hv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span><?php endif; ?>
			<h1><?php echo esc_html( $title ); ?></h1>
			<?php if ( $text ) : ?><p><?php echo esc_html( $text ); ?></p><?php endif; ?>
			<?php
			if ( ! empty( $a['show_crumb'] ) ) :
				$trail = bookingly_breadcrumb_trail();
				$last  = count( $trail ) - 1;
				?>
				<nav class="hv-crumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'bookingly' ); ?>">
					<?php foreach ( $trail as $index => $crumb ) : ?>
						<?php if ( $index > 0 ) : ?><span aria-hidden="true">/</span><?php endif; ?>
						<?php if ( $index === $last || empty( $crumb['url'] ) ) : ?>
							<span aria-current="page"><?php echo esc_html( $crumb['name'] ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['name'] ); ?></a>
						<?php endif; ?>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * Contact cards.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_contact_cards( $a ) {
	$cards = array(
		array(
			'icon'  => 'phone',
			'title' => __( 'Call Us', 'bookingly' ),
			'lines' => array( $a['phone'], $a['phone_note'] ),
		),
		array(
			'icon'  => 'mail',
			'title' => __( 'Email Us', 'bookingly' ),
			'lines' => array( $a['email'], $a['email_note'] ),
		),
		array(
			'icon'  => 'map-pin',
			'title' => __( 'Visit Us', 'bookingly' ),
			'lines' => array( $a['address'] ),
		),
	);
	$support_url = isset( $a['support_url'] ) ? trim( (string) $a['support_url'] ) : '';
	?>
	<div<?php echo bookingly_section_wrapper_attrs( $a, 'hv-grid hv-grid--3' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<?php foreach ( $cards as $card ) : ?>
			<?php $lines = array_filter( $card['lines'] ); ?>
			<?php if ( empty( $lines ) ) { continue; } ?>
			<div class="hv-contact-card">
				<div class="hv-contact-card__icon"><?php bookingly_icon( $card['icon'] ); ?></div>
				<h3><?php echo esc_html( $card['title'] ); ?></h3>
				<p><?php echo wp_kses( implode( '<br>', array_map( 'esc_html', $lines ) ), array( 'br' => array() ) ); ?></p>
			</div>
		<?php endforeach; ?>

		<?php
		if ( '' !== $support_url ) :
			$support_label = isset( $a['support_label'] ) && '' !== trim( (string) $a['support_label'] )
				? $a['support_label']
				: __( 'Open a Support Ticket', 'bookingly' );
			$support_note = isset( $a['support_note'] ) ? trim( (string) $a['support_note'] ) : '';
			$is_external  = ! bookingly_is_internal_url( $support_url );
			?>
			<div class="hv-contact-card hv-contact-card--support">
				<div class="hv-contact-card__icon"><?php bookingly_icon( 'lifebuoy' ); ?></div>
				<h3><?php esc_html_e( 'Support Portal', 'bookingly' ); ?></h3>
				<?php if ( '' !== $support_note ) : ?>
					<p><?php echo esc_html( $support_note ); ?></p>
				<?php endif; ?>
				<a class="hv-contact-card__link" href="<?php echo esc_url( $support_url ); ?>"
					<?php echo $is_external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
					<?php echo esc_html( $support_label ); ?>
					<?php if ( $is_external ) : ?>
						<span class="hv-sr-only"><?php esc_html_e( '(opens in a new tab)', 'bookingly' ); ?></span>
					<?php endif; ?>
				</a>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Contact form.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_contact_form( $a ) {
	$topics = bookingly_lines_to_list( $a['topics'] );
	$uid    = wp_unique_id( 'hv-cf-' );
	?>
	<div<?php echo bookingly_section_wrapper_attrs( $a, 'hv-form-card' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
		<?php if ( $a['subtitle'] ) : ?><p class="hv-form-card__sub"><?php echo esc_html( $a['subtitle'] ); ?></p><?php endif; ?>

		<form class="hv-contact-form" method="post" novalidate>
			<input type="hidden" name="submission_id" value="" data-hv-submission-id>
			<div class="hv-form-honeypot" aria-hidden="true">
				<label for="<?php echo esc_attr( $uid ); ?>-website"><?php esc_html_e( 'Leave this field empty', 'bookingly' ); ?></label>
				<input id="<?php echo esc_attr( $uid ); ?>-website" name="website" type="text" tabindex="-1" autocomplete="off">
			</div>
			<div class="hv-form-row">
				<div class="hv-field">
					<label for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'Full Name', 'bookingly' ); ?> <span aria-hidden="true">*</span></label>
					<input id="<?php echo esc_attr( $uid ); ?>-name" name="name" type="text" autocomplete="name" required>
				</div>
				<div class="hv-field">
					<label for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'Email', 'bookingly' ); ?> <span aria-hidden="true">*</span></label>
					<input id="<?php echo esc_attr( $uid ); ?>-email" name="email" type="email" autocomplete="email" required>
				</div>
			</div>
			<div class="hv-form-row">
				<div class="hv-field">
					<label for="<?php echo esc_attr( $uid ); ?>-phone"><?php esc_html_e( 'Phone', 'bookingly' ); ?></label>
					<input id="<?php echo esc_attr( $uid ); ?>-phone" name="phone" type="tel" autocomplete="tel">
				</div>
				<div class="hv-field">
					<label for="<?php echo esc_attr( $uid ); ?>-topic"><?php esc_html_e( 'Topic', 'bookingly' ); ?></label>
					<select id="<?php echo esc_attr( $uid ); ?>-topic" name="topic">
						<?php foreach ( $topics as $topic ) : ?>
							<option value="<?php echo esc_attr( $topic ); ?>"><?php echo esc_html( $topic ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="hv-field">
				<label for="<?php echo esc_attr( $uid ); ?>-message"><?php esc_html_e( 'Message', 'bookingly' ); ?> <span aria-hidden="true">*</span></label>
				<textarea id="<?php echo esc_attr( $uid ); ?>-message" name="message" rows="5" required></textarea>
			</div>
			<p class="hv-form-status" role="status" aria-live="polite"></p>
			<button type="submit" class="hv-btn hv-btn--primary hv-btn--block">
				<?php esc_html_e( 'Send Message', 'bookingly' ); ?><?php bookingly_icon( 'send' ); ?>
			</button>
		</form>
	</div>
	<?php
}

/**
 * Business hours.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_business_hours( $a ) {
	$rows = bookingly_business_hours_grouped();
	?>
	<div<?php echo bookingly_section_wrapper_attrs( $a, 'hv-hours-card' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<?php if ( $a['title'] ) : ?><h3><?php echo esc_html( $a['title'] ); ?></h3><?php endif; ?>
		<?php foreach ( $rows as $row ) : ?>
			<div class="hv-hours-row<?php echo $row['closed'] ? ' is-closed' : ''; ?>">
				<span><?php echo esc_html( $row['label'] ); ?></span>
				<span><?php echo esc_html( $row['closed'] ? __( 'Closed', 'bookingly' ) : $row['hours'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Resolve inherited and legacy map-loading values.
 *
 * @param mixed $value Section or builder value.
 * @return bool
 */
function bookingly_map_requires_consent( $value ) {
	if ( true === $value || 1 === $value ) {
		return true;
	}
	if ( false === $value || 0 === $value ) {
		return false;
	}

	$value = strtolower( trim( (string) $value ) );
	if ( in_array( $value, array( 'consent', 'yes', 'on', '1', 'true' ), true ) ) {
		return true;
	}
	if ( in_array( $value, array( 'immediate', 'no', 'off', '0', 'false' ), true ) ) {
		return false;
	}

	return 'consent' === bookingly_option( 'contact.map_loading', 'immediate' );
}

/**
 * Location map.
 *
 * Loads immediately by default. Theme Options can restore the privacy prompt,
 * and builder instances can inherit or override that global choice.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_map( $a ) {
	$address = trim( (string) $a['address'] );
	if ( '' === $address ) {
		return;
	}

	$src = 'https://maps.google.com/maps?q=' . rawurlencode( $address ) . '&t=m&z=15&output=embed&iwloc=near';
	?>
	<div<?php echo bookingly_section_wrapper_attrs( $a, 'hv-map-card' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<?php if ( bookingly_map_requires_consent( $a['consent'] ?? 'inherit' ) ) : ?>
			<div class="hv-map-card__consent" data-hv-map="<?php echo esc_url( $src ); ?>" data-hv-map-title="<?php esc_attr_e( 'Location map', 'bookingly' ); ?>">
				<p><?php esc_html_e( 'The map is loaded from Google. Loading it shares your IP address with Google.', 'bookingly' ); ?></p>
				<button type="button" class="hv-btn hv-btn--ghost hv-btn--sm"><?php bookingly_icon( 'map-pin' ); ?> <?php esc_html_e( 'Load map', 'bookingly' ); ?></button>
			</div>
		<?php else : ?>
			<iframe src="<?php echo esc_url( $src ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?php esc_attr_e( 'Location map', 'bookingly' ); ?>"></iframe>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Blog posts.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_blog_posts( $a ) {
	$limit = max( 1, (int) $a['limit'] );
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	if ( ! $query->have_posts() ) {
		return;
	}

	$posts    = $query->posts;
	$featured = ! empty( $a['show_featured'] ) ? array_shift( $posts ) : null;
	$columns  = in_array( (string) $a['columns'], array( '2', '3', '4' ), true ) ? (string) $a['columns'] : '3';
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, 'hv-section' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap">
			<?php if ( $a['eyebrow'] || $a['title'] ) : ?>
				<div class="hv-section-head">
					<?php if ( $a['eyebrow'] ) : ?><span class="hv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span><?php endif; ?>
					<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $featured ) : ?>
				<?php echo bookingly_get_featured_post_card( $featured ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>
			<?php endif; ?>

			<div class="hv-grid hv-grid--<?php echo esc_attr( $columns ); ?>">
				<?php foreach ( $posts as $post_item ) : ?>
					<?php echo bookingly_get_post_card( $post_item ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php
	wp_reset_postdata();
}

/**
 * Follow / newsletter band.
 *
 * Points at the site feed. The theme does not run an email list, so it does not
 * claim to; promising "one email a month" from a form that stores nothing would
 * be a lie in the interface.
 *
 * @param array<string,mixed> $a Attributes.
 */
function bookingly_section_newsletter( $a ) {
	?>
	<section<?php echo bookingly_section_wrapper_attrs( $a, 'hv-section' ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped ?>>
		<div class="hv-wrap">
			<div class="hv-newsletter">
				<?php if ( $a['eyebrow'] ) : ?><span class="hv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></span><?php endif; ?>
				<?php if ( $a['title'] ) : ?><h2><?php echo esc_html( $a['title'] ); ?></h2><?php endif; ?>
				<?php if ( $a['text'] ) : ?><p><?php echo esc_html( $a['text'] ); ?></p><?php endif; ?>
				<a class="hv-btn hv-btn--primary" href="<?php echo esc_url( get_bloginfo( 'rss2_url' ) ); ?>">
					<?php bookingly_icon( 'refresh' ); ?><?php echo esc_html( $a['label'] ); ?>
				</a>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Post card markup.
 *
 * @param WP_Post $post_item Post.
 * @return string
 */
function bookingly_get_post_card( $post_item ) {
	$categories = get_the_category( $post_item->ID );

	ob_start();
	?>
	<a href="<?php echo esc_url( get_permalink( $post_item ) ); ?>" class="hv-post-card">
		<div class="hv-post-card__thumb">
			<?php
			if ( has_post_thumbnail( $post_item ) ) {
				echo get_the_post_thumbnail( $post_item, 'bookingly-card', array( 'loading' => 'lazy' ) );
			} else {
				printf(
					'<img src="%s" alt="" width="640" height="420" loading="lazy">',
					esc_url( BOOKINGLY_URI . '/assets/images/post-placeholder.svg' )
				);
			}
			?>
		</div>
		<div class="hv-post-card__body">
			<?php if ( ! empty( $categories[0] ) ) : ?>
				<span class="hv-post-tag"><?php echo esc_html( $categories[0]->name ); ?></span>
			<?php endif; ?>
			<h3><?php echo esc_html( get_the_title( $post_item ) ); ?></h3>
			<div class="hv-post-meta">
				<span><?php bookingly_icon( 'calendar' ); ?> <?php echo esc_html( get_the_date( '', $post_item ) ); ?></span>
				<span><?php bookingly_icon( 'clock' ); ?> <?php echo esc_html( sprintf( /* translators: %d: minutes */ __( '%d min', 'bookingly' ), bookingly_reading_time( $post_item->post_content ) ) ); ?></span>
			</div>
		</div>
	</a>
	<?php
	return ob_get_clean();
}

/**
 * Large featured post card.
 *
 * @param WP_Post $post_item Post.
 * @return string
 */
function bookingly_get_featured_post_card( $post_item ) {
	$categories = get_the_category( $post_item->ID );

	ob_start();
	?>
	<a href="<?php echo esc_url( get_permalink( $post_item ) ); ?>" class="hv-featured-post">
		<div class="hv-featured-post__media">
			<?php
			if ( has_post_thumbnail( $post_item ) ) {
				echo get_the_post_thumbnail( $post_item, 'bookingly-hero' );
			} else {
				printf(
					'<img src="%s" alt="" width="640" height="420">',
					esc_url( BOOKINGLY_URI . '/assets/images/post-placeholder.svg' )
				);
			}
			?>
		</div>
		<div class="hv-featured-post__body">
			<?php if ( ! empty( $categories[0] ) ) : ?>
				<span class="hv-post-tag"><?php echo esc_html( $categories[0]->name ); ?></span>
			<?php endif; ?>
			<h2><?php echo esc_html( get_the_title( $post_item ) ); ?></h2>
			<p><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $post_item ) ) ); ?></p>
			<div class="hv-post-meta">
				<span><?php bookingly_icon( 'calendar' ); ?> <?php echo esc_html( get_the_date( '', $post_item ) ); ?></span>
				<span><?php bookingly_icon( 'clock' ); ?> <?php echo esc_html( sprintf( /* translators: %d: minutes */ __( '%d min read', 'bookingly' ), bookingly_reading_time( $post_item->post_content ) ) ); ?></span>
			</div>
			<span class="hv-btn hv-btn--primary" style="width:fit-content;"><?php esc_html_e( 'Read Article', 'bookingly' ); ?><?php bookingly_icon( 'arrow-right' ); ?></span>
		</div>
	</a>
	<?php
	return ob_get_clean();
}
