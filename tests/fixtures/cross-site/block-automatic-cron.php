<?php declare( strict_types=1 );

/**
 * Plugin Name: Shield Cross-Site Automatic Cron Blocker
 * Description: Test-only control that leaves scheduled events for explicit WP-CLI execution.
 */

add_filter(
	'pre_http_request',
	static function( $preempt, array $parsedArgs, string $url ) {
		$target = \wp_parse_url( $url );
		$home = \wp_parse_url( \home_url() );
		if ( !\is_array( $target ) || !\is_array( $home )
			|| \strtolower( (string)( $target[ 'host' ] ?? '' ) ) !== \strtolower( (string)( $home[ 'host' ] ?? '' ) )
			|| (string)( $target[ 'port' ] ?? '' ) !== (string)( $home[ 'port' ] ?? '' )
			|| \rtrim( (string)( $target[ 'path' ] ?? '' ), '/' ) !== \rtrim( (string)( $home[ 'path' ] ?? '' ), '/' ).'/wp-cron.php' ) {
			return $preempt;
		}

		\parse_str( (string)( $target[ 'query' ] ?? '' ), $query );
		if ( !\array_key_exists( 'doing_wp_cron', $query ) ) {
			return $preempt;
		}

		return new \WP_Error(
			'shield_cross_site_automatic_cron_blocked',
			'Automatic WordPress cron loopbacks are disabled for this cross-site test.'
		);
	},
	10,
	3
);
