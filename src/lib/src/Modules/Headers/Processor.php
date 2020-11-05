<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Processor extends BaseShield\Processor {

	/**
	 * @var bool
	 */
	private $bHeadersPushed;

	/**
	 * @var array
	 */
	private $headers;

	public function run() {
		if ( $this->getPushHeadersEarly() ) {
			$this->sendHeaders();
		}
		else {
			add_filter( 'wp_headers', [ $this, 'addToHeaders' ], PHP_INT_MAX );
			add_action( 'send_headers', [ $this, 'sendHeaders' ], PHP_INT_MAX, 0 );
		}
	}

	protected function getPushHeadersEarly() :bool {
		return defined( 'WPCACHEHOME' ); //WP Super Cache
	}

	/**
	 * Tries to ensure duplicate headers are not sent. Previously sent/supplied headers take priority.
	 * @param array $wpHeaders
	 * @return array
	 */
	public function addToHeaders( $wpHeaders ) {

		if ( !$this->isHeadersPushed() ) {

			if ( !is_array( $wpHeaders ) ) {
				$wpHeaders = [];
			}

			$alreadySent = array_map(
				function ( $header ) {
					return strtolower( trim( $header ) );
				},
				array_keys( $wpHeaders )
			);
			foreach ( $this->gatherSecurityHeaders() as $header => $value ) {
				if ( !in_array( strtolower( $header ), $alreadySent ) ) {
					$wpHeaders[ $header ] = $value;
				}
			}
			$this->setHeadersPushed( true );
		}
		return $wpHeaders;
	}

	/**
	 * Tries to ensure duplicate headers are not sent.
	 */
	public function sendHeaders() {
		if ( !$this->isHeadersPushed() ) {
			$aAlreadySent = array_map( 'strtolower', array_keys( $this->getAlreadySentHeaders() ) );
			foreach ( $this->gatherSecurityHeaders() as $sName => $sValue ) {
				if ( !in_array( strtolower( $sName ), $aAlreadySent ) ) {
					@header( sprintf( '%s: %s', $sName, $sValue ) );
				}
			}
			$this->setHeadersPushed( true );
		}
	}

	/**
	 * @return string[] - array of all previously sent headers. Keys are header names, values are header values.
	 */
	private function getAlreadySentHeaders() {
		$headers = [];

		if ( function_exists( 'headers_list' ) ) {
			$sent = headers_list();
			if ( is_array( $sent ) ) {
				foreach ( $sent as $header ) {
					if ( strpos( $header, ':' ) ) {
						list( $key, $value ) = array_map( 'trim', explode( ':', $header, 2 ) );
						$headers[ $key ] = $value;
					}
				}
			}
		}

		return $headers;
	}

	private function getXFrameHeader() :array {
		switch ( $this->getOptions()->getOpt( 'x_frame' ) ) {
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
		return [ 'X-XSS-Protection' => '1; mode=block' ];
	}

	private function getContentTypeOptionHeader() :array {
		return [ 'X-Content-Type-Options' => 'nosniff' ];
	}

	private function getReferrerPolicyHeader() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return [ 'Referrer-Policy' => $opts->getReferrerPolicyValue() ];
	}

	private function setContentSecurityPolicyHeader() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$aDefaultSrcDirectives = [];

		if ( $opts->isOpt( 'xcsp_self', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "'self'";
		}
		if ( $opts->isOpt( 'xcsp_data', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "data:";
		}
		if ( $opts->isOpt( 'xcsp_inline', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "'unsafe-inline'";
		}
		if ( $opts->isOpt( 'xcsp_eval', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "'unsafe-eval'";
		}
		if ( $opts->isOpt( 'xcsp_https', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "https:";
		}

		$aDefaultSrcDirectives[] = implode( " ", $opts->getOpt( 'xcsp_hosts', [] ) );

		$rules = $opts->getCspCustomRules();
		array_unshift( $rules, sprintf( 'default-src %s;', implode( " ", $aDefaultSrcDirectives ) ) );
		return [ 'Content-Security-Policy' => implode( ' ', $rules ) ];
	}

	private function gatherSecurityHeaders() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();

		if ( $opts->isReferrerPolicyEnabled() ) {
			$this->addHeader( $this->getReferrerPolicyHeader() );
		}
		if ( $opts->isEnabledXFrame() ) {
			$this->addHeader( $this->getXFrameHeader() );
		}
		if ( $opts->isEnabledXssProtection() ) {
			$this->addHeader( $this->getXssProtectionHeader() );
		}
		if ( $opts->isEnabledContentTypeHeader() ) {
			$this->addHeader( $this->getContentTypeOptionHeader() );
		}
		if ( $opts->isEnabledContentSecurityPolicy() ) {
			$this->addHeader( $this->setContentSecurityPolicyHeader() );
		}
		return $this->getHeaders();
	}

	private function getHeaders() :array {
		if ( !isset( $this->headers ) || !is_array( $this->headers ) ) {
			$this->headers = [];
		}
		return array_unique( $this->headers );
	}

	private function addHeader( array $header ) {
		if ( !empty( $header ) && is_array( $header ) ) {
			$this->headers = array_merge( $this->getHeaders(), $header );
		}
	}

	private function isHeadersPushed() :bool {
		return (bool)$this->bHeadersPushed;
	}

	private function setHeadersPushed( bool $pushed ) :self {
		$this->bHeadersPushed = $pushed;
		return $this;
	}
}