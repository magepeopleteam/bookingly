<?php
/**
 * Bookingly theme helpers.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service post type slug used by Service Booking Manager.
 */
function bookingly_service_post_type() {
	return class_exists( 'MPWPB_Function' ) ? MPWPB_Function::get_cpt() : 'mpwpb_item';
}

/**
 * Fetch published booking services.
 *
 * @param array $args Query overrides.
 * @return WP_Query
 */
function bookingly_get_services_query( $args = array() ) {
	$defaults = array(
		'post_type'      => bookingly_service_post_type(),
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
	);

	return new WP_Query( wp_parse_args( $args, $defaults ) );
}

/**
 * Minimum configured service price for a booking item.
 *
 * @param int $post_id Service post ID.
 * @return string Formatted price or empty string.
 */
function bookingly_get_service_min_price( $post_id ) {
	if ( ! class_exists( 'MPWPB_Global_Function' ) ) {
		return '';
	}

	$services = MPWPB_Global_Function::get_post_info( $post_id, 'mpwpb_service', array() );
	$min      = null;

	foreach ( (array) $services as $service ) {
		$price = isset( $service['price'] ) ? (float) $service['price'] : 0;
		if ( null === $min || $price < $min ) {
			$min = $price;
		}
	}

	if ( null === $min ) {
		return '';
	}

	return MPWPB_Global_Function::wc_price( $post_id, $min );
}

/**
 * Short subtitle stored on the service.
 *
 * @param int $post_id Service post ID.
 * @return string
 */
function bookingly_get_service_subtitle( $post_id ) {
	if ( ! class_exists( 'MPWPB_Global_Function' ) ) {
		return '';
	}

	$subtitle = MPWPB_Global_Function::get_post_info( $post_id, 'mpwpb_shortcode_sub_title', '' );
	if ( $subtitle ) {
		return $subtitle;
	}

	$excerpt = get_the_excerpt( $post_id );
	return $excerpt ? wp_strip_all_tags( $excerpt ) : '';
}

/**
 * Derive a filter slug for the services grid.
 *
 * @param int $post_id Service post ID.
 * @return string
 */
function bookingly_get_service_filter_slug( $post_id ) {
	if ( ! class_exists( 'MPWPB_Global_Function' ) ) {
		return 'general';
	}

	$categories = MPWPB_Global_Function::get_post_info( $post_id, 'mpwpb_category_service', array() );
	if ( ! empty( $categories[0]['name'] ) ) {
		return sanitize_title( $categories[0]['name'] );
	}

	return sanitize_title( get_the_title( $post_id ) );
}

/**
 * Map a service to a Tabler icon class.
 *
 * @param int $post_id Service post ID.
 * @return string
 */
function bookingly_get_service_icon( $post_id ) {
	$title = strtolower( get_the_title( $post_id ) );

	$map = array(
		'clean'    => 'ti-spray',
		'car rent' => 'ti-car',
		'rental'   => 'ti-car',
		'yoga'     => 'ti-yoga',
		'medical'  => 'ti-stethoscope',
		'dental'   => 'ti-stethoscope',
		'music'    => 'ti-music',
		'wash'     => 'ti-droplet',
		'repair'   => 'ti-tool',
		'engine'   => 'ti-engine',
		'pet'      => 'ti-paw',
		'hair'     => 'ti-scissors',
	);

	foreach ( $map as $needle => $icon ) {
		if ( false !== strpos( $title, $needle ) ) {
			return $icon;
		}
	}

	return 'ti-calendar-event';
}

/**
 * First published service permalink for CTA buttons.
 *
 * @return string
 */
function bookingly_get_primary_booking_url() {
	$query = bookingly_get_services_query(
		array(
			'posts_per_page' => 1,
		)
	);

	if ( $query->have_posts() ) {
		$query->the_post();
		$url = get_permalink();
		wp_reset_postdata();
		return $url;
	}

	$services_page = bookingly_get_page_by_template( 'page-templates/template-services.php' );
	return $services_page ? get_permalink( $services_page ) : home_url( '/' );
}

/**
 * Find a page using a theme template.
 *
 * @param string $template Template path relative to theme root.
 * @return WP_Post|null
 */
function bookingly_get_page_by_template( $template ) {
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_wp_page_template',
			'meta_value'     => $template,
		)
	);

	return ! empty( $pages[0] ) ? $pages[0] : null;
}

/**
 * Estimated reading time for posts.
 *
 * @param string $content Post content.
 * @return int Minutes.
 */
function bookingly_reading_time( $content ) {
	$words = str_word_count( wp_strip_all_tags( $content ) );
	return max( 1, (int) ceil( $words / 220 ) );
}

/**
 * Build the real breadcrumb trail for the current view.
 *
 * Shared by the visual breadcrumb and the BreadcrumbList schema so the two can
 * never drift apart. The last entry is always the current view.
 *
 * @return array<int,array{name:string,url:string}>
 */
function bookingly_breadcrumb_trail() {
	$trail = array(
		array(
			'name' => __( 'Home', 'bookingly' ),
			'url'  => home_url( '/' ),
		),
	);

	if ( is_front_page() ) {
		return apply_filters( 'bookingly_breadcrumb_trail', $trail );
	}

	if ( is_home() ) {
		$blog_id = (int) get_option( 'page_for_posts' );
		if ( $blog_id ) {
			$trail[] = array( 'name' => get_the_title( $blog_id ), 'url' => (string) get_permalink( $blog_id ) );
		}
	} elseif ( is_singular() ) {
		$post_object = get_queried_object();

		if ( $post_object instanceof WP_Post ) {
			$trail = array_merge( $trail, bookingly_breadcrumb_singular_context( $post_object ) );

			foreach ( array_reverse( get_post_ancestors( $post_object ) ) as $ancestor_id ) {
				$trail[] = array( 'name' => get_the_title( $ancestor_id ), 'url' => (string) get_permalink( $ancestor_id ) );
			}

			$trail[] = array( 'name' => get_the_title( $post_object ), 'url' => (string) get_permalink( $post_object ) );
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();

		if ( $term instanceof WP_Term ) {
			foreach ( array_reverse( (array) get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, $term->taxonomy );
				if ( $ancestor instanceof WP_Term ) {
					$trail[] = array( 'name' => $ancestor->name, 'url' => (string) get_term_link( $ancestor ) );
				}
			}
			$trail[] = array( 'name' => $term->name, 'url' => (string) get_term_link( $term ) );
		}
	} elseif ( is_post_type_archive() ) {
		$post_type = get_queried_object();
		if ( $post_type instanceof WP_Post_Type ) {
			$trail[] = array(
				'name' => $post_type->labels->name,
				'url'  => (string) get_post_type_archive_link( $post_type->name ),
			);
		}
	} elseif ( is_author() ) {
		$author = get_queried_object();
		if ( $author instanceof WP_User ) {
			$trail[] = array(
				'name' => $author->display_name,
				'url'  => (string) get_author_posts_url( $author->ID ),
			);
		}
	} elseif ( is_search() ) {
		$trail[] = array(
			/* translators: %s: search term. */
			'name' => sprintf( __( 'Search results for “%s”', 'bookingly' ), get_search_query() ),
			'url'  => (string) get_search_link( get_search_query() ),
		);
	} elseif ( is_404() ) {
		$trail[] = array( 'name' => __( 'Page not found', 'bookingly' ), 'url' => '' );
	} elseif ( is_date() ) {
		$trail[] = array( 'name' => (string) get_the_archive_title(), 'url' => '' );
	}

	/**
	 * Filter the resolved breadcrumb trail.
	 *
	 * @param array<int,array{name:string,url:string}> $trail Ordered crumbs.
	 */
	return apply_filters( 'bookingly_breadcrumb_trail', $trail );
}

/**
 * Resolve the archive/category crumbs that sit above a single entry.
 *
 * @param WP_Post $post_object Queried post.
 * @return array<int,array{name:string,url:string}>
 */
function bookingly_breadcrumb_singular_context( $post_object ) {
	$context = array();

	if ( 'post' === $post_object->post_type ) {
		$blog_id = (int) get_option( 'page_for_posts' );
		if ( $blog_id ) {
			$context[] = array( 'name' => get_the_title( $blog_id ), 'url' => (string) get_permalink( $blog_id ) );
		}

		$categories = get_the_category( $post_object->ID );
		if ( ! empty( $categories[0] ) && $categories[0] instanceof WP_Term ) {
			$link = get_category_link( $categories[0] );
			if ( ! is_wp_error( $link ) ) {
				$context[] = array( 'name' => $categories[0]->name, 'url' => (string) $link );
			}
		}

		return $context;
	}

	$post_type = get_post_type_object( $post_object->post_type );
	if ( $post_type && ! empty( $post_type->has_archive ) ) {
		$archive_link = get_post_type_archive_link( $post_object->post_type );
		if ( $archive_link ) {
			$context[] = array( 'name' => $post_type->labels->name, 'url' => (string) $archive_link );
		}
	}

	return $context;
}

/**
 * Whether a URL points at this site.
 *
 * Used to decide if a link should open in a new tab. Protocol-relative and
 * scheme differences (http vs https) are treated as the same host.
 *
 * @param string $url Absolute or relative URL.
 * @return bool
 */
function bookingly_is_internal_url( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return true;
	}

	// Relative paths and fragments never leave the site.
	if ( ! preg_match( '#^(https?:)?//#i', $url ) ) {
		return true;
	}

	$target = wp_parse_url( $url, PHP_URL_HOST );
	$home   = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

	if ( ! $target || ! $home ) {
		return true;
	}

	// A literal "www." prefix only — ltrim() with a character list would eat
	// leading w/. characters from hosts such as "wordpress.org".
	$strip = static function ( $host ) {
		$host = strtolower( (string) $host );
		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	};

	return $strip( $target ) === $strip( $home );
}

/**
 * Resolve the weekly opening hours, ordered by the site's first day of week.
 *
 * Per-day values are authoritative. Sites saved before per-day hours existed
 * fall back to the old four-field grouping, so nothing is lost on upgrade.
 *
 * @return array<int,array{key:string,label:string,schema_day:string,hours:string,closed:bool}>
 */
function bookingly_business_hours() {
	$days = array(
		'sun' => array( 'label' => __( 'Sunday', 'bookingly' ), 'schema' => 'Sunday', 'legacy' => 'hours_sunday' ),
		'mon' => array( 'label' => __( 'Monday', 'bookingly' ), 'schema' => 'Monday', 'legacy' => 'hours_monday' ),
		'tue' => array( 'label' => __( 'Tuesday', 'bookingly' ), 'schema' => 'Tuesday', 'legacy' => 'hours_tue_fri' ),
		'wed' => array( 'label' => __( 'Wednesday', 'bookingly' ), 'schema' => 'Wednesday', 'legacy' => 'hours_tue_fri' ),
		'thu' => array( 'label' => __( 'Thursday', 'bookingly' ), 'schema' => 'Thursday', 'legacy' => 'hours_tue_fri' ),
		'fri' => array( 'label' => __( 'Friday', 'bookingly' ), 'schema' => 'Friday', 'legacy' => 'hours_tue_fri' ),
		'sat' => array( 'label' => __( 'Saturday', 'bookingly' ), 'schema' => 'Saturday', 'legacy' => 'hours_saturday' ),
	);

	$order = array_keys( $days );
	$start = (int) get_option( 'start_of_week', 1 );
	$start = ( $start >= 0 && $start <= 6 ) ? $start : 1;
	$order = array_merge( array_slice( $order, $start ), array_slice( $order, 0, $start ) );

	$rows = array();

	foreach ( $order as $key ) {
		$day   = $days[ $key ];
		$hours = trim( (string) bookingly_option( 'contact.hours_' . $key, '' ) );

		if ( '' === $hours ) {
			$hours = trim( (string) bookingly_option( 'contact.' . $day['legacy'], '' ) );
		}

		$rows[] = array(
			'key'        => $key,
			'label'      => $day['label'],
			'schema_day' => $day['schema'],
			'hours'      => $hours,
			'closed'     => ( '' === $hours ) || false !== stripos( $hours, 'closed' ),
		);
	}

	/**
	 * Filter the resolved weekly hours.
	 *
	 * @param array<int,array<string,mixed>> $rows Ordered day rows.
	 */
	return apply_filters( 'bookingly_business_hours', $rows );
}

/**
 * Collapse consecutive days that share the same hours into labelled ranges.
 *
 * Turns seven rows into "Sunday – Thursday / Friday / Saturday" so the card
 * stays readable whatever the working week looks like.
 *
 * @return array<int,array{label:string,hours:string,closed:bool}>
 */
function bookingly_business_hours_grouped() {
	$grouped = array();
	$run     = null;

	foreach ( bookingly_business_hours() as $row ) {
		$value = strtolower( $row['closed'] ? 'closed' : $row['hours'] );

		if ( null !== $run && $run['value'] === $value ) {
			$run['last'] = $row['label'];
			continue;
		}

		if ( null !== $run ) {
			$grouped[] = $run;
		}

		$run = array(
			'value'  => $value,
			'first'  => $row['label'],
			'last'   => $row['label'],
			'hours'  => $row['hours'],
			'closed' => $row['closed'],
		);
	}

	if ( null !== $run ) {
		$grouped[] = $run;
	}

	return array_map(
		static function ( $entry ) {
			return array(
				'label'  => $entry['first'] === $entry['last']
					? $entry['first']
					/* translators: 1: first weekday, 2: last weekday. */
					: sprintf( __( '%1$s – %2$s', 'bookingly' ), $entry['first'], $entry['last'] ),
				'hours'  => $entry['hours'],
				'closed' => $entry['closed'],
			);
		},
		$grouped
	);
}
