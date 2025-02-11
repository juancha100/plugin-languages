<?php

/**
 * Define wordpress.com VIP equivalent of uncached functions
 */

if ( ! function_exists( 'wpcom_vip_get_page_by_title' ) ) {
	function wpcom_vip_get_page_by_title( $page_title, $output = OBJECT, $post_type = 'page' ) {
		return get_page_by_title( $page_title, $output, $post_type );
	}
}

if ( ! function_exists( 'wpcom_vip_get_category_by_slug' ) ) {
	function wpcom_vip_get_category_by_slug( $slug ) {
		return get_category_by_slug( $slug );
	}
}

if ( ! function_exists( 'wpcom_vip_get_term_by' ) ) {
	function wpcom_vip_get_term_by( $field, $value, $taxonomy = '', $output = OBJECT, $filter = 'raw' ) {
		return get_term_by( $field, $value, $taxonomy, $output, $filter );
	}
}

if ( ! function_exists( 'wpcom_vip_get_term_link' ) ) {
	function wpcom_vip_get_term_link( $term, $taxonomy = '' ) {
		return get_term_link( $term, $taxonomy );
	}
}
/**
 * Determines whether we should load the cache compatibility
 *
 * @since 2.3.8
 *
 * return bool True if the cache compatibility must be loaded
 */
function pll_is_cache_active() {
	/**
	 * Filters whether we should load the cache compatibility
	 *
	 * @since 2.3.8
	 *
	 * @bool $is_cache True if a known cache plugin is active
	 *                 incl. WP Fastest Cache which doesn't use WP_CACHE
	 */
	return apply_filters( 'pll_is_cache_active', ( defined( 'WP_CACHE' ) && WP_CACHE ) || defined( 'WPFC_MAIN_PATH' ) );
}