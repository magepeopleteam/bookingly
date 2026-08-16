<?php
/**
 * Search form.
 *
 * @package Bookingly
 */

$bookingly_search_id = wp_unique_id( 'hv-search-' );
?>
<form role="search" method="get" class="hv-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="hv-sr-only" for="<?php echo esc_attr( $bookingly_search_id ); ?>">
		<?php esc_html_e( 'Search this site', 'bookingly' ); ?>
	</label>
	<input
		type="search"
		id="<?php echo esc_attr( $bookingly_search_id ); ?>"
		class="hv-search-form__input"
		name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php esc_attr_e( 'Search…', 'bookingly' ); ?>"
	>
	<button type="submit" class="hv-btn hv-btn--primary">
		<?php esc_html_e( 'Search', 'bookingly' ); ?>
	</button>
</form>
