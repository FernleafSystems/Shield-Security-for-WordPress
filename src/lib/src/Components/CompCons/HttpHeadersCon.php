<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class HttpHeadersCon {

	use ExecOnce;
	use PluginControllerConsumer;

	private $pushed = false;

	/**
	 * @var array
	 */
	private $headers = [];

	protected function canRun() :bool {
		return !self::con()->this_req->request_bypasses_all_restrictions;
	}

	protected function run() {
		if ( $this->getPushHeadersEarly() ) {
			$this->sendHeaders();
		}
		else {
			add_filter( 'wp_headers', [ $this, 'addToHeaders' ], \PHP_INT_MAX );
			add_action( 'send_headers', [ $this, 'sendHeaders' ], \PHP_INT_MAX, 0 );
		}
	}

	protected function getPushHeadersEarly() :bool {
		return \defined( 'WPCACHEHOME' ); //WP Super Cache
	}

	/**
	 * Tries to ensure duplicate headers are not sent. Previously sent/supplied headers take priority.
	 * @param array $wpHeaders
	 */
	public function addToHeaders( $wpHeaders ) :array {

		if ( !$this->pushed ) {

			if ( !\is_array( $wpHeaders ) ) {
				$wpHeaders = [];
			}

			$alreadySent = \array_map(
				function ( $header ) {
					return \strtolower( \trim( $header ) );
				},
				\array_keys( $wpHeaders )
			);
			foreach ( $this->gatherSecurityHeaders() as $header => $value ) {
				if ( !\in_array( \strtolower( $header ), $alreadySent ) ) {
					$wpHeaders[ $header ] = $value;
				}
			}

			$this->pushed = true;
		}

		return \is_array( $wpHeaders ) ? $wpHeaders : [];
	}

	public function sendHeaders() {
		if ( !$this->pushed ) {

			$sent = [];
			if ( \function_exists( '\headers_list' ) ) {
				foreach ( \headers_list() as $header ) {
					if ( \strpos( $header, ':' ) ) {
						[ $key, $value ] = \array_map( '\trim', \explode( ':', $header, 2 ) );
						$sent[ \strtolower( $key ) ] = $value;
					}
				}
			}

			foreach ( $this->gatherSecurityHeaders() as $name => $value ) {
				if ( !isset( $sent[ \strtolower( $name ) ] ) ) {
					@\header( sprintf( '%s: %s', $name, $value ) );
				}
			}

			$this->pushed = true;
		}
	}

	private function gatherSecurityHeaders() :array {
		$opts = self::con()->opts;
		$this->addHeader( $this->getReferrerPolicyHeader() );
		$this->addHeader( $this->getXFrameHeader() );
		$this->addHeader( $opts->optIs( 'x_xss_protect', 'Y' ) ? [ 'X-XSS-Protection' => '1; mode=block' ] : [] );
		$this->addHeader( $opts->optIs( 'x_content_type', 'Y' ) ? [ 'X-Content-Type-Options' => 'nosniff' ] : [] );
		$this->addHeader( $this->setContentSecurityPolicyHeader() );
		return \array_filter( $this->headers );
	}

	private function getXFrameHeader() :array {
		switch ( self::con()->opts->optGet( 'x_frame' ) ) {
			case 'on_sameorigin':
				$xFrame = 'SAMEORIGIN';
				break;
			case 'on_deny':
				$xFrame = 'DENY';
				break;
			default:
				$xFrame = '';
				break;
		}
		return empty( $xFrame ) ? [] : [ 'x-frame-options' => $xFrame ];
	}

	private function getReferrerPolicyHeader() :array {
		$value = self::con()->opts->optGet( 'x_referrer_policy' );
		return $value === 'disabled' ? [] : [ 'Referrer-Policy' => $value === 'empty' ? '' : $value ];
	}

	private function setContentSecurityPolicyHeader() :array {
		$opts = self::con()->opts;
		$rules = ( $opts->optIs( 'enable_x_content_security_policy', 'Y' ) && self::con()->isPremiumActive() ) ?
			\array_filter( \array_map( '\trim', $opts->optGet( 'xcsp_custom' ) ) ) : [];
		return empty( $rules ) ? [] : [ 'Content-Security-Policy' => \implode( ' ', $rules ) ];
	}

	private function addHeader( array $header ) {
		if ( !empty( $header ) ) {
			$this->headers = \array_merge( $this->headers, $header );
		}
	}
}