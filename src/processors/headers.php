<?php

class ICWP_WPSF_Processor_Headers extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var bool
	 */
	private $bHeadersPushed;

	/**
	 * @var array
	 */
	private $aHeaders;

	/**
	 */
	public function run() {
		if ( $this->getPushHeadersEarly() ) {
			$this->sendHeaders();
		}
		else {
			add_filter( 'wp_headers', [ $this, 'addToHeaders' ] );
			add_action( 'send_headers', [ $this, 'sendHeaders' ], 100, 0 );
		}
	}

	/**
	 * @return bool
	 */
	protected function getPushHeadersEarly() {
		return defined( 'WPCACHEHOME' ); //WP Super Cache
	}

	/**
	 */
	public function sendHeaders() {
		if ( !$this->isHeadersPushed() ) {
			foreach ( $this->gatherSecurityHeaders() as $sName => $sValue ) {
				@header( sprintf( '%s: %s', $sName, $sValue ) );
			}
			$this->setHeadersPushed( true );
		}
	}

	/**
	 * @param array $aCurrentWpHeaders
	 * @return array
	 */
	public function addToHeaders( $aCurrentWpHeaders ) {
		if ( !$this->isHeadersPushed() ) {
			$aCurrentWpHeaders = array_merge( $aCurrentWpHeaders, $this->gatherSecurityHeaders() );
			$this->setHeadersPushed( true );
		}
		return $aCurrentWpHeaders;
	}

	/**
	 * @return array|null
	 */
	private function getXFrameHeader() {
		switch ( $this->getOption( 'x_frame' ) ) {
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
		/** @var ICWP_WPSF_FeatureHandler_Headers $oFO */
		$oFO = $this->getMod();
		return [ 'Referrer-Policy' => $oFO->getReferrerPolicyValue() ];
	}

	/**
	 * @return array|null
	 */
	private function setContentSecurityPolicyHeader() {
		/** @var ICWP_WPSF_FeatureHandler_Headers $oFO */
		$oFO = $this->getMod();
		if ( !$oFO->isContentSecurityPolicyEnabled() ) {
			return null;
		}

		$sTemplate = 'default-src %s;';

		$aDefaultSrcDirectives = [];

		if ( $oFO->isOpt( 'xcsp_self', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "'self'";
		}
		if ( $oFO->isOpt( 'xcsp_data', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "data:";
		}
		if ( $oFO->isOpt( 'xcsp_inline', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "'unsafe-inline'";
		}
		if ( $oFO->isOpt( 'xcsp_eval', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "'unsafe-eval'";
		}
		if ( $oFO->isOpt( 'xcsp_https', 'Y' ) ) {
			$aDefaultSrcDirectives[] = "https:";
		}

		$aDomains = $oFO->getCspHosts();
		if ( !empty( $aDomains ) && is_array( $aDomains ) ) {
			$aDefaultSrcDirectives[] = implode( " ", $aDomains );
		}
		return [ 'Content-Security-Policy' => sprintf( $sTemplate, implode( " ", $aDefaultSrcDirectives ) ) ];
	}

	/**
	 * @return array
	 */
	private function gatherSecurityHeaders() {
		/** @var ICWP_WPSF_FeatureHandler_Headers $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isReferrerPolicyEnabled() ) {
			$this->addHeader( $this->getReferrerPolicyHeader() );
		}
		if ( $oFO->isEnabledXFrame() ) {
			$this->addHeader( $this->getXFrameHeader() );
		}
		if ( $oFO->isEnabledXssProtection() ) {
			$this->addHeader( $this->getXssProtectionHeader() );
		}
		if ( $oFO->isEnabledContentTypeHeader() ) {
			$this->addHeader( $this->getContentTypeOptionHeader() );
		}
		if ( $oFO->isContentSecurityPolicyEnabled() ) {
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