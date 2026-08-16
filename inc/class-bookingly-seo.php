<?php
/**
 * Search-engine optimisation output.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emit titles, descriptions, robots rules, social cards and structured data.
 *
 * Every value degrades to a sensible automatic default, so a fresh install is
 * optimised with no configuration at all. When a dedicated SEO plugin is
 * active the overlapping output stands down rather than duplicating tags.
 */
final class Bookingly_Seo {

	const META_TITLE   = '_bookingly_seo_title';
	const META_DESC    = '_bookingly_seo_description';
	const META_IMAGE   = '_bookingly_seo_image';
	const META_NOINDEX = '_bookingly_seo_noindex';

	const DESCRIPTION_LENGTH = 160;
	const TITLE_LENGTH       = 70;

	/** Registered image size used for social share previews (1200x630). */
	const SHARE_SIZE = 'bookingly-share';

	/**
	 * Register front-end hooks.
	 */
	public static function init() {
		add_filter( 'document_title_parts', array( __CLASS__, 'filter_title_parts' ) );
		add_filter( 'document_title_separator', array( __CLASS__, 'filter_title_separator' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ) );
		add_action( 'wp_head', array( __CLASS__, 'render_head' ), 2 );

		/*
		 * Share-image sizing applies whoever is writing the tags. When an SEO
		 * plugin owns the social output the theme stands down everywhere else,
		 * but the plugin still has to be told which image size to share, or it
		 * defaults to the full-size original.
		 */
		add_filter( 'rank_math/opengraph/image_sizes', array( __CLASS__, 'filter_share_image_sizes' ) );
		add_filter( 'rank_math/opengraph/attachment_id', array( __CLASS__, 'prepare_share_crop' ) );
		add_filter( 'wpseo_opengraph_image_size', array( __CLASS__, 'filter_yoast_image_size' ) );
	}

	/**
	 * Put the theme's 1200x630 crop at the front of Rank Math's size preference.
	 *
	 * Rank Math walks its list and takes the first size with usable dimensions,
	 * so with the default list of full, large, medium_large it always shares the
	 * untouched original — frequently a multi-megabyte PNG, heavy enough that
	 * WhatsApp drops the preview and the link arrives with no picture at all.
	 *
	 * @param array $sizes Image size names, in preference order.
	 * @return array
	 */
	public static function filter_share_image_sizes( $sizes ) {
		$sizes = is_array( $sizes ) ? $sizes : array();

		return array_merge( array( self::SHARE_SIZE ), array_diff( $sizes, array( self::SHARE_SIZE ) ) );
	}

	/**
	 * The same preference for Yoast, which asks for a single size name.
	 *
	 * @param string $size Image size name.
	 * @return string
	 */
	public static function filter_yoast_image_size( $size ) {
		unset( $size );

		return self::SHARE_SIZE;
	}

	/**
	 * Rank Math hook wrapper: ensure the crop exists, then hand the ID back.
	 *
	 * @param int $id Attachment ID.
	 * @return int
	 */
	public static function prepare_share_crop( $id ) {
		self::ensure_share_crop( $id );

		return $id;
	}

	/**
	 * Create the share crop for an attachment if it is missing.
	 *
	 * Registering an image size only affects images uploaded afterwards, so on
	 * any site that had media before the theme was activated the crop does not
	 * exist. That matters more than it looks: asking WordPress for a size that
	 * was never generated does not fail loudly — it returns the *original* file
	 * with the requested dimensions reported alongside it. The share tags would
	 * then advertise 1200x630 while serving a 1.07MB original, which is worse
	 * than not trying, because scrapers lay the preview out from those numbers.
	 *
	 * Generating it on first use keeps the theme correct without requiring the
	 * site owner to run a bulk thumbnail regeneration. It is one image editor
	 * pass, once per attachment, and the transient stops a failing image from
	 * being retried on every request.
	 *
	 * @param int $id Attachment ID.
	 * @return bool Whether the crop is present when this returns.
	 */
	public static function ensure_share_crop( $id ) {
		$id = (int) $id;
		if ( ! $id || ! wp_attachment_is_image( $id ) ) {
			return false;
		}

		$meta = wp_get_attachment_metadata( $id );
		if ( ! is_array( $meta ) ) {
			return false;
		}

		/*
		 * An existing crop is kept unless it is still a PNG, which means it
		 * predates the JPEG conversion below or was just rebuilt by a bulk
		 * thumbnail regeneration (that uses the registered size and so restores
		 * the original format). Those get redone; anything else is left alone.
		 */
		$existing = isset( $meta['sizes'][ self::SHARE_SIZE ] ) ? $meta['sizes'][ self::SHARE_SIZE ] : array();
		if ( ! empty( $existing['file'] ) && 'image/png' !== ( $existing['mime-type'] ?? '' ) ) {
			return true;
		}

		$registered = wp_get_registered_image_subsizes();
		if ( empty( $registered[ self::SHARE_SIZE ] ) ) {
			return false;
		}

		$target = $registered[ self::SHARE_SIZE ];
		$width  = (int) ( $meta['width'] ?? 0 );
		$height = (int) ( $meta['height'] ?? 0 );

		/*
		 * Too small to be worth a preview at all. Facebook ignores anything
		 * under 200px, and below the recommended 600x315 the result is a
		 * thumbnail rather than a card, so leave those on the original.
		 */
		if ( $width < 600 || $height < 315 ) {
			return false;
		}

		/*
		 * Crop to the 1.91:1 frame only when the source can fill it. WordPress
		 * never upscales, so asking a 1072x581 screenshot for a 1200x630 image
		 * simply produces nothing — and those are exactly the heavy PNGs this
		 * exists to shrink. They are already inside the frame, so they need
		 * re-encoding rather than resizing.
		 */
		$fits = $width >= (int) $target['width'] && $height >= (int) $target['height'];

		$lock = 'bookingly_share_crop_' . $id;
		if ( get_transient( $lock ) ) {
			return false;
		}
		set_transient( $lock, 1, DAY_IN_SECONDS );

		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) {
			return false;
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return false;
		}

		$editor->set_quality( 82 );

		/*
		 * Re-encode heavy PNGs as JPEG. Format is the dominant factor here, not
		 * dimensions: a 1200x630 crop of a UI screenshot is still ~600KB as PNG
		 * and about 90KB as JPEG, and it is the file size — not the pixel count
		 * — that decides whether WhatsApp shows a preview at all.
		 *
		 * Only heavy files are converted. JPEG has no alpha channel, so a small
		 * transparent PNG such as a logo would be flattened onto a solid block;
		 * those are already light enough to share as they are, so they are left
		 * in their original format.
		 */
		$convert = 'image/png' === get_post_mime_type( $id ) && filesize( $file ) > 150 * KB_IN_BYTES;

		// Nothing to crop and nothing to re-encode means there is nothing to do.
		if ( ! $fits && ! $convert ) {
			return ! empty( $existing['file'] );
		}

		if ( $fits && ! $editor->resize( (int) $target['width'], (int) $target['height'], true ) ) {
			return false;
		}

		$size      = $editor->get_size();
		$extension = $convert ? 'jpg' : null;
		$mime      = $convert ? 'image/jpeg' : null;
		$filename  = $editor->generate_filename( $size['width'] . 'x' . $size['height'], null, $extension );

		$saved = $editor->save( $filename, $mime );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
			return false;
		}

		$meta['sizes'] = isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array();
		$meta['sizes'][ self::SHARE_SIZE ] = array(
			'file'      => $saved['file'],
			'width'     => (int) $saved['width'],
			'height'    => (int) $saved['height'],
			'mime-type' => $saved['mime-type'],
			'filesize'  => file_exists( $saved['path'] ) ? filesize( $saved['path'] ) : 0,
		);
		wp_update_attachment_metadata( $id, $meta );

		return true;
	}

	/**
	 * Whether the theme's SEO output is switched on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) apply_filters( 'bookingly_seo_enabled', ! empty( bookingly_option( 'seo.enable', true ) ) );
	}

	/**
	 * Whether a dedicated SEO plugin already owns meta, social and schema output.
	 *
	 * Only the overlapping tags stand down; performance hints and the breadcrumb
	 * trail stay active either way.
	 *
	 * @return bool
	 */
	public static function plugin_owns_meta() {
		$active = defined( 'WPSEO_VERSION' )                 // Yoast SEO.
			|| defined( 'RANK_MATH_VERSION' )                // Rank Math.
			|| defined( 'AIOSEO_VERSION' )                   // All in One SEO.
			|| defined( 'SEOPRESS_VERSION' )                 // SEOPress.
			|| defined( 'SLIM_SEO_VERSION' )                 // Slim SEO.
			|| defined( 'THE_SEO_FRAMEWORK_VERSION' )        // The SEO Framework.
			|| function_exists( 'aioseo' )
			|| function_exists( 'the_seo_framework' );

		/**
		 * Filter whether Bookingly should defer its meta output to a plugin.
		 *
		 * @param bool $active Whether a known SEO plugin was detected.
		 */
		return (bool) apply_filters( 'bookingly_seo_defer_to_plugin', $active );
	}

	/**
	 * Whether Bookingly should print its own meta, social and schema tags.
	 *
	 * @return bool
	 */
	public static function owns_output() {
		return self::enabled() && ! self::plugin_owns_meta();
	}

	/* ---------------------------------------------------------------------
	 * Title
	 * ------------------------------------------------------------------ */

	/**
	 * Apply per-page and home title overrides.
	 *
	 * @param array<string,string> $parts Title parts.
	 * @return array<string,string>
	 */
	public static function filter_title_parts( $parts ) {
		if ( ! self::owns_output() ) {
			return $parts;
		}

		$override = '';

		if ( is_front_page() ) {
			$override = (string) bookingly_option( 'seo.home_title', '' );
		} elseif ( is_singular() ) {
			$override = (string) get_post_meta( get_the_ID(), self::META_TITLE, true );
		}

		$override = trim( wp_strip_all_tags( $override ) );
		if ( '' !== $override ) {
			$parts['title'] = self::cap( $override, self::TITLE_LENGTH );
			unset( $parts['tagline'] );
		}

		return $parts;
	}

	/**
	 * Use the configured title separator.
	 *
	 * @param string $separator Default separator.
	 * @return string
	 */
	public static function filter_title_separator( $separator ) {
		if ( ! self::owns_output() ) {
			return $separator;
		}

		$configured = trim( (string) bookingly_option( 'seo.title_separator', '' ) );
		return '' !== $configured ? $configured : $separator;
	}

	/* ---------------------------------------------------------------------
	 * Robots
	 * ------------------------------------------------------------------ */

	/**
	 * Keep thin and duplicate views out of the index.
	 *
	 * @param array<string,mixed> $robots Robots directives.
	 * @return array<string,mixed>
	 */
	public static function filter_robots( $robots ) {
		if ( ! self::enabled() ) {
			return $robots;
		}

		// Rich image previews in search results.
		if ( ! isset( $robots['max-image-preview'] ) ) {
			$robots['max-image-preview'] = 'large';
		}

		$noindex = false;

		if ( is_search() && ! empty( bookingly_option( 'seo.noindex_search', true ) ) ) {
			$noindex = true;
		} elseif ( is_404() ) {
			$noindex = true;
		} elseif ( is_singular() && get_post_meta( get_the_ID(), self::META_NOINDEX, true ) ) {
			$noindex = true;
		}

		if ( $noindex ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}

		return $robots;
	}

	/* ---------------------------------------------------------------------
	 * Head output
	 * ------------------------------------------------------------------ */

	/**
	 * Print description, canonical, social cards, schema and speed hints.
	 */
	public static function render_head() {
		if ( ! self::enabled() || is_feed() || is_embed() ) {
			return;
		}

		if ( self::owns_output() ) {
			self::render_description();
			self::render_canonical();
			self::render_social();
			self::render_schema();
		}

		// Plugins do not handle this, so it always runs.
		self::render_speed_hints();
	}

	/** Print the meta description. */
	private static function render_description() {
		$description = self::description();
		if ( '' === $description ) {
			return;
		}

		printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $description ) );
	}

	/**
	 * Print a self-referencing canonical for views core does not cover.
	 *
	 * Core's rel_canonical() only runs on singular views.
	 */
	private static function render_canonical() {
		if ( is_singular() ) {
			return;
		}

		$url = self::current_url();
		if ( '' === $url ) {
			return;
		}

		printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $url ) );
	}

	/** Print Open Graph and Twitter card tags. */
	private static function render_social() {
		$title       = self::social_title();
		$description = self::description();
		$url         = self::current_url();
		$image       = self::share_image();
		$site_name   = self::site_name();

		$tags = array(
			'og:locale'    => get_locale(),
			'og:site_name' => $site_name,
			'og:type'      => is_singular( 'post' ) ? 'article' : 'website',
			'og:title'     => $title,
			'og:url'       => $url,
		);

		if ( '' !== $description ) {
			$tags['og:description'] = $description;
		}

		if ( is_singular( 'post' ) ) {
			$published = get_the_date( DATE_W3C );
			$modified  = get_the_modified_date( DATE_W3C );
			if ( $published ) {
				$tags['article:published_time'] = $published;
			}
			if ( $modified ) {
				$tags['article:modified_time'] = $modified;
			}
		}

		foreach ( $tags as $property => $content ) {
			if ( '' === $content ) {
				continue;
			}
			printf( "<meta property=\"%s\" content=\"%s\">\n", esc_attr( $property ), esc_attr( $content ) );
		}

		if ( ! empty( $image['url'] ) ) {
			printf( "<meta property=\"og:image\" content=\"%s\">\n", esc_url( $image['url'] ) );
			if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ) {
				printf( "<meta property=\"og:image:width\" content=\"%d\">\n", (int) $image['width'] );
				printf( "<meta property=\"og:image:height\" content=\"%d\">\n", (int) $image['height'] );
			}
			if ( ! empty( $image['alt'] ) ) {
				printf( "<meta property=\"og:image:alt\" content=\"%s\">\n", esc_attr( $image['alt'] ) );
			}
		}

		$card = ! empty( $image['url'] ) ? 'summary_large_image' : 'summary';
		printf( "<meta name=\"twitter:card\" content=\"%s\">\n", esc_attr( $card ) );
		printf( "<meta name=\"twitter:title\" content=\"%s\">\n", esc_attr( $title ) );
		if ( '' !== $description ) {
			printf( "<meta name=\"twitter:description\" content=\"%s\">\n", esc_attr( $description ) );
		}
		if ( ! empty( $image['url'] ) ) {
			printf( "<meta name=\"twitter:image\" content=\"%s\">\n", esc_url( $image['url'] ) );
		}

		$handle = self::twitter_handle();
		if ( '' !== $handle ) {
			printf( "<meta name=\"twitter:site\" content=\"%s\">\n", esc_attr( $handle ) );
		}
	}

	/**
	 * Hint the browser about the largest-contentful-paint image.
	 *
	 * Preloading the hero and opting it out of lazy loading is the single
	 * biggest Core Web Vitals win available to a theme.
	 */
	private static function render_speed_hints() {
		if ( ! is_singular() || ! has_post_thumbnail() ) {
			return;
		}

		$id  = (int) get_post_thumbnail_id();
		$src = wp_get_attachment_image_src( $id, 'bookingly-hero' );
		if ( empty( $src[0] ) ) {
			return;
		}

		$srcset = wp_get_attachment_image_srcset( $id, 'bookingly-hero' );
		$sizes  = wp_get_attachment_image_sizes( $id, 'bookingly-hero' );

		printf(
			"<link rel=\"preload\" as=\"image\" href=\"%s\"%s%s fetchpriority=\"high\">\n",
			esc_url( $src[0] ),
			$srcset ? ' imagesrcset="' . esc_attr( $srcset ) . '"' : '',
			$sizes ? ' imagesizes="' . esc_attr( $sizes ) . '"' : ''
		);
	}

	/* ---------------------------------------------------------------------
	 * Structured data
	 * ------------------------------------------------------------------ */

	/** Print the JSON-LD @graph. */
	private static function render_schema() {
		if ( empty( bookingly_option( 'seo.output_schema', true ) ) ) {
			return;
		}

		$graph = array_values( array_filter( self::schema_graph() ) );
		if ( empty( $graph ) ) {
			return;
		}

		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			)
		);

		if ( ! $json ) {
			return;
		}

		// Slashes stay escaped, so a literal </script> in content cannot break out.
		echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped
	}

	/**
	 * Assemble every schema node for the current view.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function schema_graph() {
		$home      = home_url( '/' );
		$url       = self::current_url();
		$site_id   = $home . '#/schema/website';
		$org_id    = $home . '#/schema/organization';
		$page_id   = $url . '#/schema/webpage';
		$crumb_id  = $url . '#/schema/breadcrumb';
		$site_name = self::site_name();

		$organization = self::organization_node( $org_id );

		$website = array(
			'@type'         => 'WebSite',
			'@id'           => $site_id,
			'url'           => $home,
			'name'          => $site_name,
			'inLanguage'    => get_bloginfo( 'language' ),
			'publisher'     => array( '@id' => $org_id ),
			'potentialAction' => array(
				array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => $home . '?s={search_term_string}',
					),
					'query-input' => 'required name=search_term_string',
				),
			),
		);

		$description = self::description();
		$webpage     = array(
			'@type'      => 'WebPage',
			'@id'        => $page_id,
			'url'        => $url,
			'name'       => self::social_title(),
			'isPartOf'   => array( '@id' => $site_id ),
			'about'      => array( '@id' => $org_id ),
			'inLanguage' => get_bloginfo( 'language' ),
		);

		if ( '' !== $description ) {
			$webpage['description'] = $description;
		}

		$image = self::share_image();
		if ( ! empty( $image['url'] ) ) {
			$webpage['primaryImageOfPage'] = array(
				'@type'  => 'ImageObject',
				'url'    => $image['url'],
				'width'  => (int) $image['width'],
				'height' => (int) $image['height'],
			);
		}

		if ( is_singular() ) {
			$webpage['datePublished'] = get_the_date( DATE_W3C );
			$webpage['dateModified']  = get_the_modified_date( DATE_W3C );
		}

		$breadcrumb = self::breadcrumb_node( $crumb_id );
		if ( $breadcrumb ) {
			$webpage['breadcrumb'] = array( '@id' => $crumb_id );
		}

		$graph = array( $organization, $website, $webpage, $breadcrumb );

		$entity = self::entity_node( $page_id, $org_id );
		if ( $entity ) {
			$graph[] = $entity;
		}

		/**
		 * Filter the complete JSON-LD graph.
		 *
		 * @param array<int,array<string,mixed>> $graph Schema nodes.
		 */
		return apply_filters( 'bookingly_seo_schema_graph', $graph );
	}

	/**
	 * Organization, upgraded to LocalBusiness when an address is configured.
	 *
	 * @param string $org_id Node identifier.
	 * @return array<string,mixed>
	 */
	private static function organization_node( $org_id ) {
		$address = trim( (string) bookingly_option( 'contact.address', '' ) );
		$phone   = trim( (string) bookingly_option( 'header.phone', '' ) );
		$email   = trim( (string) bookingly_option( 'contact.email', '' ) );
		$hours   = self::opening_hours();

		$is_local = '' !== $address && ! self::is_placeholder( $address );

		$node = array(
			'@type' => $is_local ? 'LocalBusiness' : 'Organization',
			'@id'   => $org_id,
			'name'  => self::site_name(),
			'url'   => home_url( '/' ),
		);

		$tagline = trim( (string) get_bloginfo( 'description' ) );
		if ( '' !== $tagline ) {
			$node['description'] = $tagline;
		}

		$logo = self::logo_image();
		if ( ! empty( $logo['url'] ) ) {
			$node['logo'] = array(
				'@type'  => 'ImageObject',
				'@id'    => home_url( '/' ) . '#/schema/logo',
				'url'    => $logo['url'],
				'width'  => (int) $logo['width'],
				'height' => (int) $logo['height'],
			);
			$node['image'] = array( '@id' => home_url( '/' ) . '#/schema/logo' );
		}

		if ( '' !== $phone ) {
			$node['telephone'] = $phone;
		}

		if ( '' !== $email && ! self::is_placeholder( $email ) ) {
			$node['email'] = $email;
		}

		if ( $is_local ) {
			$node['address'] = array(
				'@type'         => 'PostalAddress',
				'streetAddress' => $address,
			);

			$price_range = trim( (string) bookingly_option( 'seo.price_range', '' ) );
			if ( '' !== $price_range ) {
				$node['priceRange'] = $price_range;
			}

			if ( ! empty( $hours ) ) {
				$node['openingHoursSpecification'] = $hours;
			}
		}

		$profiles = self::social_profiles();
		if ( ! empty( $profiles ) ) {
			$node['sameAs'] = $profiles;
		}

		return $node;
	}

	/**
	 * BreadcrumbList built from the shared trail.
	 *
	 * @param string $crumb_id Node identifier.
	 * @return array<string,mixed>|null
	 */
	private static function breadcrumb_node( $crumb_id ) {
		$trail = bookingly_breadcrumb_trail();

		if ( count( $trail ) < 2 ) {
			return null;
		}

		$items    = array();
		$position = 1;

		foreach ( $trail as $crumb ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => wp_strip_all_tags( (string) $crumb['name'] ),
			);

			if ( ! empty( $crumb['url'] ) ) {
				$item['item'] = $crumb['url'];
			}

			$items[] = $item;
			$position++;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $crumb_id,
			'itemListElement' => $items,
		);
	}

	/**
	 * The main entity for single views: Article for posts, Service for services.
	 *
	 * @param string $page_id Page node identifier.
	 * @param string $org_id  Organization node identifier.
	 * @return array<string,mixed>|null
	 */
	private static function entity_node( $page_id, $org_id ) {
		if ( ! is_singular() ) {
			return null;
		}

		$post_id = (int) get_the_ID();
		$url     = self::current_url();

		if ( is_singular( 'post' ) ) {
			$node = array(
				'@type'            => 'BlogPosting',
				'@id'              => $url . '#/schema/article',
				'headline'         => self::cap( wp_strip_all_tags( get_the_title( $post_id ) ), 110 ),
				'mainEntityOfPage' => array( '@id' => $page_id ),
				'datePublished'    => get_the_date( DATE_W3C, $post_id ),
				'dateModified'     => get_the_modified_date( DATE_W3C, $post_id ),
				'publisher'        => array( '@id' => $org_id ),
				'inLanguage'       => get_bloginfo( 'language' ),
			);

			$author_id = (int) get_post_field( 'post_author', $post_id );
			if ( $author_id ) {
				$node['author'] = array(
					'@type' => 'Person',
					'@id'   => home_url( '/' ) . '#/schema/person/' . $author_id,
					'name'  => get_the_author_meta( 'display_name', $author_id ),
					'url'   => get_author_posts_url( $author_id ),
				);
			}

			$description = self::description();
			if ( '' !== $description ) {
				$node['description'] = $description;
			}

			$image = self::share_image();
			if ( ! empty( $image['url'] ) ) {
				$node['image'] = array(
					'@type'  => 'ImageObject',
					'url'    => $image['url'],
					'width'  => (int) $image['width'],
					'height' => (int) $image['height'],
				);
			}

			$terms = get_the_category( $post_id );
			if ( ! empty( $terms ) ) {
				$node['articleSection'] = wp_list_pluck( $terms, 'name' );
			}

			return $node;
		}

		if ( get_post_type( $post_id ) === bookingly_service_post_type() ) {
			$node = array(
				'@type'            => 'Service',
				'@id'              => $url . '#/schema/service',
				'name'             => wp_strip_all_tags( get_the_title( $post_id ) ),
				'url'              => $url,
				'mainEntityOfPage' => array( '@id' => $page_id ),
				'provider'         => array( '@id' => $org_id ),
			);

			$subtitle = bookingly_get_service_subtitle( $post_id );
			if ( '' !== $subtitle ) {
				$node['description'] = self::cap( wp_strip_all_tags( $subtitle ), self::DESCRIPTION_LENGTH );
			}

			$image = self::share_image();
			if ( ! empty( $image['url'] ) ) {
				$node['image'] = $image['url'];
			}

			return $node;
		}

		return null;
	}

	/**
	 * Opening hours parsed from the contact settings.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function opening_hours() {
		$by_range = array();

		foreach ( bookingly_business_hours() as $row ) {
			if ( $row['closed'] ) {
				continue;
			}

			$range = self::parse_hours( $row['hours'] );
			if ( ! $range ) {
				continue;
			}

			// Days that share a range belong in one specification.
			$key = $range['opens'] . '-' . $range['closes'];

			if ( ! isset( $by_range[ $key ] ) ) {
				$by_range[ $key ] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => array(),
					'opens'     => $range['opens'],
					'closes'    => $range['closes'],
				);
			}

			$by_range[ $key ]['dayOfWeek'][] = $row['schema_day'];
		}

		return array_values( $by_range );
	}

	/**
	 * Parse "11:00 – 21:00" or "9am - 7pm" into 24-hour schema times.
	 *
	 * @param string $value Stored hours string.
	 * @return array{opens:string,closes:string}|null
	 */
	public static function parse_hours( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}

		// The /u flag matters: the stored separator is usually a multi-byte en dash.
		$pattern = '/(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\s*[\x{2010}-\x{2015}\-]\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/iu';
		if ( 1 !== preg_match( $pattern, $value, $matches ) ) {
			return null;
		}

		$opens  = self::to_24_hour( $matches[1], $matches[2] ?? '', $matches[3] ?? '' );
		$closes = self::to_24_hour( $matches[4], $matches[5] ?? '', $matches[6] ?? '' );

		return ( $opens && $closes ) ? array( 'opens' => $opens, 'closes' => $closes ) : null;
	}

	/**
	 * Normalise one clock time to HH:MM.
	 *
	 * @param string $hour   Hour digits.
	 * @param string $minute Minute digits.
	 * @param string $suffix am/pm marker.
	 * @return string
	 */
	private static function to_24_hour( $hour, $minute, $suffix ) {
		$hour   = (int) $hour;
		$minute = '' !== $minute ? (int) $minute : 0;
		$suffix = strtolower( (string) $suffix );

		if ( 'pm' === $suffix && $hour < 12 ) {
			$hour += 12;
		} elseif ( 'am' === $suffix && 12 === $hour ) {
			$hour = 0;
		}

		if ( $hour > 24 || $minute > 59 ) {
			return '';
		}

		return sprintf( '%02d:%02d', $hour, $minute );
	}

	/* ---------------------------------------------------------------------
	 * Value resolution
	 * ------------------------------------------------------------------ */

	/**
	 * The description for the current view.
	 *
	 * @return string
	 */
	public static function description() {
		$description = '';

		if ( is_front_page() ) {
			$description = (string) bookingly_option( 'seo.home_description', '' );

			if ( '' === trim( $description ) ) {
				$description = (string) get_bloginfo( 'description' );
			}

			// A static front page still has its own excerpt or content to fall back on.
			if ( '' === trim( $description ) && is_singular() ) {
				$description = self::entry_description( (int) get_the_ID() );
			}
		} elseif ( is_singular() ) {
			$post_id  = (int) get_the_ID();
			$override = (string) get_post_meta( $post_id, self::META_DESC, true );

			$description = '' !== trim( $override ) ? $override : self::entry_description( $post_id );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$description = (string) term_description();
		} elseif ( is_author() ) {
			$description = (string) get_the_author_meta( 'description', (int) get_query_var( 'author' ) );
		} elseif ( is_home() ) {
			$blog_id     = (int) get_option( 'page_for_posts' );
			$description = $blog_id ? (string) get_the_excerpt( $blog_id ) : (string) get_bloginfo( 'description' );
		}

		// Entities are decoded here and re-escaped at output, so search engines
		// see "Tom & Jerry" rather than a literal "Tom &amp; Jerry".
		$description = wp_strip_all_tags( (string) $description, true );
		$description = wp_specialchars_decode( $description, ENT_QUOTES );
		$description = trim( preg_replace( '/\s+/', ' ', $description ) );

		/*
		 * Bookingly template pages render entirely from theme sections, so they
		 * legitimately have no post content to summarise. Fall back to the site
		 * description rather than shipping a page with no meta description.
		 */
		if ( '' === $description && ! is_search() && ! is_404() ) {
			$fallback = trim( (string) get_bloginfo( 'description' ) );
			if ( '' === $fallback ) {
				$fallback = trim( (string) bookingly_option( 'footer.description', '' ) );
			}
			$description = $fallback;
		}

		/**
		 * Filter the resolved meta description.
		 *
		 * @param string $description Description text.
		 */
		return self::cap( (string) apply_filters( 'bookingly_seo_description', $description ), self::DESCRIPTION_LENGTH );
	}

	/**
	 * Derive a description from an entry's excerpt, then its opening content.
	 *
	 * @param int $post_id Entry ID.
	 * @return string
	 */
	private static function entry_description( $post_id ) {
		$excerpt = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : '';

		if ( '' === trim( (string) $excerpt ) ) {
			$content = (string) get_post_field( 'post_content', $post_id );
			$content = excerpt_remove_blocks( $content );
			$excerpt = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $content ) ), 40, '' );
		}

		return (string) $excerpt;
	}

	/**
	 * Title used for social cards and the WebPage node.
	 *
	 * @return string
	 */
	public static function social_title() {
		if ( is_front_page() ) {
			$override = trim( (string) bookingly_option( 'seo.home_title', '' ) );
			return '' !== $override ? $override : self::site_name();
		}

		if ( is_singular() ) {
			$override = trim( (string) get_post_meta( get_the_ID(), self::META_TITLE, true ) );
			if ( '' !== $override ) {
				return $override;
			}
			return wp_strip_all_tags( get_the_title() );
		}

		return wp_strip_all_tags( (string) wp_get_document_title() );
	}

	/**
	 * Sharing image for the current view, with graceful fallbacks.
	 *
	 * @return array{url:string,width:int,height:int,alt:string}
	 */
	public static function share_image() {
		$empty = array( 'url' => '', 'width' => 0, 'height' => 0, 'alt' => '' );

		$id = 0;

		if ( is_singular() ) {
			$id = (int) get_post_meta( get_the_ID(), self::META_IMAGE, true );
			if ( ! $id && has_post_thumbnail() ) {
				$id = (int) get_post_thumbnail_id();
			}
		}

		if ( ! $id ) {
			$id = (int) bookingly_option( 'seo.share_image_id', 0 );
		}

		if ( ! $id ) {
			$logo = self::logo_image();
			return $logo['url'] ? $logo : $empty;
		}

		/*
		 * Prefer the purpose-built 1200x630 crop over the original. Sharing the
		 * full-size file is what leaves a link with no picture on WhatsApp,
		 * which drops preview images over roughly 300KB, and it makes Facebook
		 * crop an arbitrary ratio into its own 1.91:1 frame.
		 *
		 * Only ask for the crop once it genuinely exists. WordPress answers a
		 * request for an ungenerated size with the original file *and* the
		 * requested dimensions, so asking blindly would advertise 1200x630
		 * against a multi-megabyte original — worse than not trying.
		 */
		$size = self::ensure_share_crop( $id ) ? self::SHARE_SIZE : 'full';
		$src  = wp_get_attachment_image_src( $id, $size );

		if ( empty( $src[0] ) ) {
			return $empty;
		}

		return array(
			'url'    => (string) $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
			'alt'    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * The site logo, from theme options or the core custom logo.
	 *
	 * @return array{url:string,width:int,height:int,alt:string}
	 */
	public static function logo_image() {
		$empty = array( 'url' => '', 'width' => 0, 'height' => 0, 'alt' => '' );

		$id = (int) bookingly_option( 'brand.logo_id', 0 );
		if ( ! $id ) {
			$id = (int) get_theme_mod( 'custom_logo' );
		}

		if ( ! $id ) {
			return $empty;
		}

		$src = wp_get_attachment_image_src( $id, 'full' );
		if ( empty( $src[0] ) ) {
			return $empty;
		}

		return array(
			'url'    => (string) $src[0],
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
			'alt'    => self::site_name(),
		);
	}

	/**
	 * Social profile URLs for sameAs.
	 *
	 * @return array<int,string>
	 */
	public static function social_profiles() {
		$profiles = array();

		foreach ( array( 'facebook', 'instagram', 'x', 'linkedin' ) as $network ) {
			$url = trim( (string) bookingly_option( 'social.' . $network, '' ) );
			if ( '' !== $url ) {
				$profiles[] = esc_url_raw( $url );
			}
		}

		return array_values( array_filter( $profiles ) );
	}

	/**
	 * Normalised Twitter/X handle.
	 *
	 * @return string
	 */
	public static function twitter_handle() {
		$handle = trim( (string) bookingly_option( 'seo.twitter_site', '' ) );
		if ( '' === $handle ) {
			return '';
		}

		$handle = ltrim( $handle, '@' );
		$handle = preg_replace( '/[^A-Za-z0-9_]/', '', $handle );

		return '' !== $handle ? '@' . $handle : '';
	}

	/**
	 * Public-facing site name.
	 *
	 * @return string
	 */
	public static function site_name() {
		$custom = trim( (string) bookingly_option( 'brand.site_name', '' ) );
		if ( '' !== $custom ) {
			return $custom;
		}

		return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Canonical URL for the current view.
	 *
	 * @return string
	 */
	public static function current_url() {
		if ( is_front_page() ) {
			return home_url( '/' );
		}

		if ( is_singular() ) {
			return (string) get_permalink();
		}

		if ( is_home() ) {
			$blog_id = (int) get_option( 'page_for_posts' );
			return $blog_id ? (string) get_permalink( $blog_id ) : home_url( '/' );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$link = get_term_link( $term );
				return is_wp_error( $link ) ? home_url( '/' ) : (string) $link;
			}
		}

		if ( is_post_type_archive() ) {
			$post_type = get_queried_object();
			if ( $post_type instanceof WP_Post_Type ) {
				return (string) get_post_type_archive_link( $post_type->name );
			}
		}

		if ( is_author() ) {
			$author = get_queried_object();
			if ( $author instanceof WP_User ) {
				return (string) get_author_posts_url( $author->ID );
			}
		}

		if ( is_search() ) {
			return (string) get_search_link( get_search_query() );
		}

		global $wp;
		return isset( $wp->request ) ? home_url( user_trailingslashit( $wp->request ) ) : home_url( '/' );
	}

	/**
	 * Whether a stored value is still one of the demo placeholders.
	 *
	 * @param string $value Option value.
	 * @return bool
	 */
	private static function is_placeholder( $value ) {
		return false !== stripos( (string) $value, '.example' )
			|| false !== stripos( (string) $value, 'bookingly.example' );
	}

	/**
	 * Trim to a length without cutting a word in half.
	 *
	 * @param string $text   Source text.
	 * @param int    $length Maximum characters.
	 * @return string
	 */
	private static function cap( $text, $length ) {
		$text = trim( (string) $text );

		$size = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
		if ( $size <= $length ) {
			return $text;
		}

		$clipped = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length ) : substr( $text, 0, $length );
		$space   = function_exists( 'mb_strrpos' ) ? mb_strrpos( $clipped, ' ' ) : strrpos( $clipped, ' ' );

		if ( $space && $space > (int) ( $length * 0.6 ) ) {
			$clipped = function_exists( 'mb_substr' ) ? mb_substr( $clipped, 0, $space ) : substr( $clipped, 0, $space );
		}

		return rtrim( $clipped, " \t\n\r\0\x0B,.;:-–—" );
	}
}
