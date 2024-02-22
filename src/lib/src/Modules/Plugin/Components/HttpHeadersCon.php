<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers\ModConsumer;

class HttpHeadersCon {

	use ExecOnce;
	use ModConsumer;

	private $pushed = false;

	/**
	 * @var array
	 */
	private $headers = [];

	protected function canRun() :bool {
		return self::con()->opts->optIs( 'enable_headers', 'Y' ) && !self::con()->this_req->request_bypasses_all_restrictions;
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

			$sent = \array_map( '\strtolower', \array_keys( $this->getAlreadySentHeaders() ) );
			foreach ( $this->gatherSecurityHeaders() as $name => $value ) {
				if ( !\in_array( \strtolower( $name ), $sent ) ) {
					@\header( sprintf( '%s: %s', $name, $value ) );
				}
			}

			$this->pushed = true;
		}
	}

	private function gatherSecurityHeaders() :array {
		$this->addHeader( $this->getReferrerPolicyHeader() );
		$this->addHeader( $this->getXFrameHeader() );
		$this->addHeader( $this->getXssProtectionHeader() );
		$this->addHeader( $this->getContentTypeOptionHeader() );
		$this->addHeader( $this->setContentSecurityPolicyHeader() );
		return \array_filter( $this->headers );
	}

	/**
	 * @return string[] - array of all previously sent headers. Keys are header names, values are header values.
	 */
	private function getAlreadySentHeaders() :array {
		$headers = [];

		if ( \function_exists( '\headers_list' ) ) {
			foreach ( \headers_list() as $header ) {
				if ( \strpos( $header, ':' ) ) {
					[ $key, $value ] = \array_map( '\trim', \explode( ':', $header, 2 ) );
					$headers[ $key ] = $value;
				}
			}
		}

		return $headers;
	}

	private function getXFrameHeader() :array {
		switch ( $this->opts()->getOpt( 'x_frame' ) ) {
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

	private function getXssProtectionHeader() :array {
		$opts = $this->opts();
		return $opts->isEnabledXssProtection() ? [ 'X-XSS-Protection' => '1; mode=block' ] : [];
	}

	private function getContentTypeOptionHeader() :array {
		$opts = $this->opts();
		return $opts->isEnabledContentTypeHeader() ? [ 'X-Content-Type-Options' => 'nosniff' ] : [];
	}

	private function getReferrerPolicyHeader() :array {
		$opts = $this->opts();
		return $opts->isReferrerPolicyEnabled() ? [ 'Referrer-Policy' => $opts->getReferrerPolicyValue() ] : [];
	}

	private function setContentSecurityPolicyHeader() :array {
		$opts = $this->opts();
		return $opts->isEnabledContentSecurityPolicy() ?
			[ 'Content-Security-Policy' => \implode( ' ', $opts->getCspCustomRules() ) ] : [];
	}

	private function addHeader( array $header ) {
		if ( !empty( $header ) ) {
			$this->headers = \array_merge( $this->headers, $header );
		}
	}
}