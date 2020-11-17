<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Headers;

/**
 * Class ICWP_WPSF_Processor_Lockdown
 * @deprecated 10.1
 */
class ICWP_WPSF_Processor_Headers extends Modules\BaseShield\ShieldProcessor {

	/**
	 * @var bool
	 */
	private $bHeadersPushed;

	/**
	 * @var array
	 */
	private $aHeaders;

	public function run() {
		if ( $this->getPushHeadersEarly() ) {
			$this->sendHeaders();
		}
		else {
			add_filter( 'wp_headers', [ $this, 'addToHeaders' ], PHP_INT_MAX );
			add_action( 'send_headers', [ $this, 'sendHeaders' ], PHP_INT_MAX, 0 );
		}
	}

	/**
	 * @return bool
	 */
	protected function getPushHeadersEarly() {
		return defined( 'WPCACHEHOME' ); //WP Super Cache
	}

	/**
	 * Tries to ensure duplicate headers are not sent. Previously sent/supplied headers take priority.
	 * @param array $aCurrentWpHeaders
	 * @return array
	 */
	public function addToHeaders( $aCurrentWpHeaders ) {
		if ( !$this->isHeadersPushed() ) {
			$aAlreadySentHeaders = array_map(
				function ( $sHeader ) {
					return strtolower( trim( $sHeader ) );
				},
				( is_array( $aCurrentWpHeaders ) ? array_keys( $aCurrentWpHeaders ) : [] )
			);
			foreach ( $this->gatherSecurityHeaders() as $sHeader => $sValue ) {
				if ( !in_array( strtolower( $sHeader ), $aAlreadySentHeaders ) ) {
					$aCurrentWpHeaders[ $sHeader ] = $sValue;
				}
			}
			$this->setHeadersPushed( true );
		}
		return $aCurrentWpHeaders;
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
		$aHeaders = [];

		if ( function_exists( 'headers_list' ) ) {
			$aSent = headers_list();
			if ( is_array( $aSent ) ) {
				foreach ( $aSent as $sHeader ) {
					if ( strpos( $sHeader, ':' ) ) {
						list( $sKey, $sValue ) = array_map( 'trim', explode( ':', $sHeader, 2 ) );
						$aHeaders[ $sKey ] = $sValue;
					}
				}
			}
		}

		return $aHeaders;
	}

	/**
	 * @return array|null
	 */
	private function getXFrameHeader() {
		switch ( $this->getOptions()->getOpt( 'x_frame' ) ) {
			case 'on_sameorigin':
				$sXFrameOption = 'SAMEORIGIN';
				break;
			case 'on_deny':
				$sXFrameOption = 'DENY';
				break;
			default:
				$sXFrameOption = '';
				break;
		}
		return !empty( $sXFrameOption ) ? [ 'x-frame-options' => $sXFrameOption ] : null;
	}

	/**
	 * @return array
	 */
	private function getXssProtectionHeader() {
		return [ 'X-XSS-Protection' => '1; mode=block' ];
	}

	/**
	 * @return array
	 */
	private function getContentTypeOptionHeader() {
		return [ 'X-Content-Type-Options' => 'nosniff' ];
	}

	/**
	 * @return array|null
	 */
	private function getReferrerPolicyHeader() {
		/** @var Headers\Options $oOpts */
		$oOpts = $this->getOptions();
		return [ 'Referrer-Policy' => $oOpts->getReferrerPolicyValue() ];
	}

	/**
	 * @return array|null
	 */
	private function setContentSecurityPolicyHeader() {
		/** @var Headers\Options $oOpts */
		$oOpts = $this->getOptions();

		$aDefaultSrcDirectives = [];

		if ( $oOpts->isOpt( 'xcsp_self', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "'self'";
		}
		if ( $oOpts->isOpt( 'xcsp_data', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "data:";
		}
		if ( $oOpts->isOpt( 'xcsp_inline', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "'unsafe-inline'";
		}
		if ( $oOpts->isOpt( 'xcsp_eval', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "'unsafe-eval'";
		}
		if ( $oOpts->isOpt( 'xcsp_https', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "https:";
		}

		$aDefaultSrcDirectives[] = implode( " ", $oOpts->getOpt( 'xcsp_hosts', [] ) );

		$aRules = $oOpts->getCspCustomRules();
		array_unshift( $aRules, sprintf( 'default-src %s;', implode( " ", $aDefaultSrcDirectives ) ) );
		return [ 'Content-Security-Policy' => implode( ' ', $aRules ) ];
	}

	/**
	 * @return array
	 */
	private function gatherSecurityHeaders() {
		/** @var Headers\Options $oOpts */
		$oOpts = $this->getOptions();

		if ( $oOpts->isReferrerPolicyEnabled() ) {
			$this->addHeader( $this->getReferrerPolicyHeader() );
		}
		if ( $oOpts->isEnabledXFrame() ) {
			$this->addHeader( $this->getXFrameHeader() );
		}
		if ( $oOpts->isEnabledXssProtection() ) {
			$this->addHeader( $this->getXssProtectionHeader() );
		}
		if ( $oOpts->isEnabledContentTypeHeader() ) {
			$this->addHeader( $this->getContentTypeOptionHeader() );
		}
		if ( $oOpts->isEnabledContentSecurityPolicy() ) {
			$this->addHeader( $this->setContentSecurityPolicyHeader() );
		}
		return $this->getHeaders();
	}

	/**
	 * @return array
	 */
	private function getHeaders() {
		if ( !isset( $this->aHeaders ) || !is_array( $this->aHeaders ) ) {
			$this->aHeaders = [];
		}
		return array_unique( $this->aHeaders );
	}

	/**
	 * @param array $aHeader
	 */
	private function addHeader( $aHeader ) {
		if ( !empty( $aHeader ) && is_array( $aHeader ) ) {
			$this->aHeaders = array_merge( $this->getHeaders(), $aHeader );
		}
	}

	/**
	 * @return bool
	 */
	private function isHeadersPushed() {
		return (bool)$this->bHeadersPushed;
	}

	/**
	 * @param bool $bHeadersPushed
	 * @return $this
	 */
	private function setHeadersPushed( $bHeadersPushed ) {
		$this->bHeadersPushed = $bHeadersPushed;
		return $this;
	}
}