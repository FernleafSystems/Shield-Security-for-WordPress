<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Requests from this site to itself, made the way WordPress makes its own loopback requests (Site Health and the
 * plugin and theme editors), so every Shield loopback behaves the same on restrictive hosting.
 */
class SiteLoopback {

	/**
	 * WordPress's own Site Health loopback test.
	 */
	public function canLoopback() :bool {
		$can = false;

		if ( !\class_exists( '\WP_Site_Health' ) && \defined( 'ABSPATH' ) ) {
			$siteHealthFile = \rtrim( ABSPATH, '/\\' ).'/wp-admin/includes/class-wp-site-health.php';
			if ( Services::WpFs()->isAccessibleFile( $siteHealthFile ) ) {
				require_once $siteHealthFile;
			}
		}

		if ( \class_exists( '\WP_Site_Health' )
			 // @phpstan-ignore function.alreadyNarrowedType
			 && \method_exists( '\WP_Site_Health', 'get_instance' )
			 && \method_exists( \WP_Site_Health::get_instance(), 'get_test_loopback_requests' ) ) {
			$result = \WP_Site_Health::get_instance()->get_test_loopback_requests();
			$can = \is_array( $result ) && ( $result[ 'status' ] ?? '' ) === 'good';
		}

		return $can;
	}

	/**
	 * @return array|\WP_Error
	 */
	public function get( string $url ) {
		return \wp_remote_get( $url, $this->args( $url ) );
	}

	/**
	 * The request settings WordPress uses for its loopback requests.
	 */
	public function args( string $url ) :array {
		$headers = [
			'Cache-Control' => 'no-cache',
		];
		// Sites behind HTTP Basic authentication pass the same credentials back to themselves.
		if ( isset( $_SERVER[ 'PHP_AUTH_USER' ] ) && isset( $_SERVER[ 'PHP_AUTH_PW' ] ) ) {
			$headers[ 'Authorization' ] = 'Basic '.\base64_encode( \wp_unslash( $_SERVER[ 'PHP_AUTH_USER' ] ).':'.\wp_unslash( $_SERVER[ 'PHP_AUTH_PW' ] ) );
		}
		return [
			'timeout'   => 10,
			'headers'   => $headers,
			'sslverify' => \apply_filters( 'https_local_ssl_verify', false, $url ),
		];
	}

	/**
	 * Before loopback requests that check a change to the site, as WordPress's plugin editor does: let the
	 * process outlive the requests and the visitor closing the page, and release the PHP session so the
	 * requests can't wait on it.
	 */
	public function prepareForChecks() :void {
		if ( \function_exists( 'set_time_limit' ) ) {
			@\set_time_limit( 300 );
		}
		if ( \function_exists( 'ignore_user_abort' ) ) {
			@\ignore_user_abort( true );
		}
		if ( \function_exists( 'session_status' ) && \PHP_SESSION_ACTIVE === \session_status() ) {
			\session_write_close();
		}
	}
}
