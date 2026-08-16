<?php
/**
 * Per-entry SEO overrides.
 *
 * @package Bookingly
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a compact SEO panel to every public entry.
 *
 * Blank fields are the normal case: the theme derives a title, description and
 * sharing image automatically, and these controls only override that.
 */
final class Bookingly_Seo_Meta {

	const NONCE = 'bookingly_seo_meta';

	/**
	 * Register admin hooks.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Public post types that should receive the panel.
	 *
	 * @return array<int,string>
	 */
	public static function post_types() {
		$types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'names'
		);

		unset( $types['attachment'] );

		/**
		 * Filter which post types get the SEO panel.
		 *
		 * @param array<int,string> $types Post type names.
		 */
		return (array) apply_filters( 'bookingly_seo_meta_post_types', array_values( $types ) );
	}

	/**
	 * Register the meta box.
	 */
	public static function register() {
		if ( ! Bookingly_Seo::enabled() || Bookingly_Seo::plugin_owns_meta() ) {
			return;
		}

		add_meta_box(
			'bookingly-seo',
			__( 'SEO — Bookingly', 'bookingly' ),
			array( __CLASS__, 'render' ),
			self::post_types(),
			'normal',
			'default'
		);
	}

	/**
	 * Render the panel.
	 *
	 * @param WP_Post $post Current entry.
	 */
	public static function render( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );
		wp_enqueue_media();

		$title       = (string) get_post_meta( $post->ID, Bookingly_Seo::META_TITLE, true );
		$description = (string) get_post_meta( $post->ID, Bookingly_Seo::META_DESC, true );
		$image_id    = (int) get_post_meta( $post->ID, Bookingly_Seo::META_IMAGE, true );
		$noindex     = (bool) get_post_meta( $post->ID, Bookingly_Seo::META_NOINDEX, true );
		$image_url   = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<style>
			.bookingly-seo-field { margin: 0 0 18px; }
			.bookingly-seo-field > label { display: block; font-weight: 600; margin: 0 0 4px; }
			.bookingly-seo-field input[type="text"], .bookingly-seo-field textarea { width: 100%; }
			.bookingly-seo-count { float: right; font-weight: 400; color: #646970; }
			.bookingly-seo-count.is-over { color: #b32d2e; font-weight: 600; }
			.bookingly-seo-preview { padding: 12px 14px; margin: 0 0 18px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 6px; max-width: 620px; }
			.bookingly-seo-preview-url { color: #202124; font-size: 12px; }
			.bookingly-seo-preview-title { color: #1a0dab; font-size: 18px; line-height: 1.3; margin: 2px 0; }
			.bookingly-seo-preview-desc { color: #4d5156; font-size: 13px; line-height: 1.5; }
			.bookingly-seo-thumb img { max-width: 200px; height: auto; display: block; margin: 0 0 8px; border-radius: 4px; }
		</style>

		<p class="description">
			<?php esc_html_e( 'Leave any field empty to use the automatic value. Nothing here is required.', 'bookingly' ); ?>
		</p>

		<div class="bookingly-seo-preview" aria-hidden="true">
			<div class="bookingly-seo-preview-url"><?php echo esc_html( self::preview_url( $post ) ); ?></div>
			<div class="bookingly-seo-preview-title" data-bookingly-seo-preview="title"></div>
			<div class="bookingly-seo-preview-desc" data-bookingly-seo-preview="description"></div>
		</div>

		<div class="bookingly-seo-field">
			<label for="bookingly-seo-title">
				<?php esc_html_e( 'SEO Title', 'bookingly' ); ?>
				<span class="bookingly-seo-count" data-bookingly-seo-count="title"></span>
			</label>
			<input type="text" id="bookingly-seo-title" name="bookingly_seo_title"
				value="<?php echo esc_attr( $title ); ?>"
				data-bookingly-seo-input="title" data-bookingly-seo-limit="70"
				data-bookingly-seo-fallback="<?php echo esc_attr( wp_strip_all_tags( $post->post_title ) ); ?>">
			<p class="description"><?php esc_html_e( 'Aim for roughly 70 characters. Defaults to the entry title.', 'bookingly' ); ?></p>
		</div>

		<div class="bookingly-seo-field">
			<label for="bookingly-seo-description">
				<?php esc_html_e( 'Meta Description', 'bookingly' ); ?>
				<span class="bookingly-seo-count" data-bookingly-seo-count="description"></span>
			</label>
			<textarea id="bookingly-seo-description" name="bookingly_seo_description" rows="3"
				data-bookingly-seo-input="description" data-bookingly-seo-limit="160"
				data-bookingly-seo-fallback="<?php echo esc_attr( self::fallback_description( $post ) ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Aim for roughly 160 characters. Defaults to the excerpt, then the opening content.', 'bookingly' ); ?></p>
		</div>

		<div class="bookingly-seo-field bookingly-seo-thumb">
			<label><?php esc_html_e( 'Social Sharing Image', 'bookingly' ); ?></label>
			<div data-bookingly-seo-thumb>
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="">
				<?php endif; ?>
			</div>
			<input type="hidden" id="bookingly-seo-image" name="bookingly_seo_image" value="<?php echo esc_attr( (string) $image_id ); ?>">
			<button type="button" class="button" data-bookingly-seo-select><?php esc_html_e( 'Select Image', 'bookingly' ); ?></button>
			<button type="button" class="button-link" data-bookingly-seo-clear><?php esc_html_e( 'Remove', 'bookingly' ); ?></button>
			<p class="description"><?php esc_html_e( 'Used by Facebook, LinkedIn and X. Defaults to the featured image, then the site-wide sharing image.', 'bookingly' ); ?></p>
		</div>

		<div class="bookingly-seo-field">
			<label for="bookingly-seo-noindex">
				<input type="checkbox" id="bookingly-seo-noindex" name="bookingly_seo_noindex" value="1" <?php checked( $noindex ); ?>>
				<?php esc_html_e( 'Hide this entry from search engines (noindex)', 'bookingly' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Links on the page are still followed. Use for thank-you pages, duplicates and internal-only content.', 'bookingly' ); ?></p>
		</div>

		<script>
		( function () {
			var box = document.getElementById( 'bookingly-seo' );
			if ( ! box ) { return; }

			function sync( key ) {
				var input = box.querySelector( '[data-bookingly-seo-input="' + key + '"]' );
				var count = box.querySelector( '[data-bookingly-seo-count="' + key + '"]' );
				var preview = box.querySelector( '[data-bookingly-seo-preview="' + key + '"]' );
				if ( ! input ) { return; }

				var limit = parseInt( input.getAttribute( 'data-bookingly-seo-limit' ), 10 ) || 0;
				var value = input.value.trim() || input.getAttribute( 'data-bookingly-seo-fallback' ) || '';

				if ( count ) {
					count.textContent = value.length + ' / ' + limit;
					count.classList.toggle( 'is-over', limit > 0 && value.length > limit );
				}
				if ( preview ) {
					preview.textContent = value;
				}
			}

			[ 'title', 'description' ].forEach( function ( key ) {
				var input = box.querySelector( '[data-bookingly-seo-input="' + key + '"]' );
				if ( ! input ) { return; }
				input.addEventListener( 'input', function () { sync( key ); } );
				sync( key );
			} );

			var frame = null;
			var field = document.getElementById( 'bookingly-seo-image' );
			var thumb = box.querySelector( '[data-bookingly-seo-thumb]' );
			var select = box.querySelector( '[data-bookingly-seo-select]' );
			var clear = box.querySelector( '[data-bookingly-seo-clear]' );

			if ( select && field && window.wp && window.wp.media ) {
				select.addEventListener( 'click', function () {
					if ( ! frame ) {
						frame = window.wp.media( {
							title: <?php echo wp_json_encode( __( 'Select Sharing Image', 'bookingly' ) ); ?>,
							library: { type: 'image' },
							multiple: false
						} );
						frame.on( 'select', function () {
							var item = frame.state().get( 'selection' ).first().toJSON();
							field.value = item.id;
							if ( thumb ) {
								var src = ( item.sizes && item.sizes.medium ) ? item.sizes.medium.url : item.url;
								thumb.innerHTML = '<img alt="">';
								thumb.firstChild.src = src;
							}
						} );
					}
					frame.open();
				} );
			}

			if ( clear && field ) {
				clear.addEventListener( 'click', function () {
					field.value = '';
					if ( thumb ) { thumb.innerHTML = ''; }
				} );
			}
		}() );
		</script>
		<?php
	}

	/**
	 * Persist the overrides.
	 *
	 * @param int     $post_id Entry ID.
	 * @param WP_Post $post    Entry.
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE . '_nonce' ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, self::post_types(), true ) ) {
			return;
		}

		$title = isset( $_POST['bookingly_seo_title'] )
			? sanitize_text_field( wp_unslash( $_POST['bookingly_seo_title'] ) )
			: '';
		$description = isset( $_POST['bookingly_seo_description'] )
			? sanitize_textarea_field( wp_unslash( $_POST['bookingly_seo_description'] ) )
			: '';
		$image_id = isset( $_POST['bookingly_seo_image'] ) ? absint( wp_unslash( $_POST['bookingly_seo_image'] ) ) : 0;
		$noindex  = ! empty( $_POST['bookingly_seo_noindex'] );

		// An attachment ID that is not an image would produce a broken card.
		if ( $image_id && ! wp_attachment_is_image( $image_id ) ) {
			$image_id = 0;
		}

		self::update( $post_id, Bookingly_Seo::META_TITLE, self::cap( $title, 200 ) );
		self::update( $post_id, Bookingly_Seo::META_DESC, self::cap( $description, 320 ) );
		self::update( $post_id, Bookingly_Seo::META_IMAGE, $image_id ? $image_id : '' );
		self::update( $post_id, Bookingly_Seo::META_NOINDEX, $noindex ? 1 : '' );
	}

	/**
	 * Write a value, deleting the row when it is empty.
	 *
	 * @param int    $post_id Entry ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 */
	private static function update( $post_id, $key, $value ) {
		if ( '' === $value || null === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}

		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * The description the front end would generate on its own.
	 *
	 * @param WP_Post $post Entry.
	 * @return string
	 */
	private static function fallback_description( $post ) {
		$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';

		if ( '' === trim( (string) $excerpt ) ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), 40, '' );
		}

		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $excerpt ) ) );
	}

	/**
	 * Readable permalink for the search preview.
	 *
	 * @param WP_Post $post Entry.
	 * @return string
	 */
	private static function preview_url( $post ) {
		$permalink = get_permalink( $post );
		if ( ! $permalink ) {
			return (string) home_url( '/' );
		}

		return str_replace( array( 'https://', 'http://' ), '', $permalink );
	}

	/**
	 * Hard length guard for stored overrides.
	 *
	 * @param string $value  Raw value.
	 * @param int    $length Maximum characters.
	 * @return string
	 */
	private static function cap( $value, $length ) {
		$value = trim( (string) $value );

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
