<?php

class ICWP_WPSF_Processor_Headers extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var bool
	 */
	protected $bHeadersPushed;
	/**
	 * @var array
	 */
	protected $aHeaders;

	/**
	 */
	public function run() {
		if ( $this->getPushHeadersEarly() ) {
			$this->pushHeaders();
		}
		else {
			add_filter( 'wp_headers', array( $this, 'addToHeaders' ) );
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
	protected function pushHeaders() {
		if ( !$this->isHeadersPushed() ) {
			$aHeaders = $this->gatherSecurityHeaders();
			foreach ( $aHeaders as $sHeader => $sValue ) {
				header( sprintf( '%s: %s', $sHeader, $sValue ) );
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
	protected function getXFrameHeader() {
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
		return !empty( $sXFrameOption ) ? array( 'x-frame-options' => $sXFrameOption ) : null;
	}

	/**
	 * @return array
	 */
	protected function getXssProtectionHeader() {
		return array( 'X-XSS-Protection' => '1; mode=block' );
	}

	/**
	 * @return array
	 */
	protected function getContentTypeOptionHeader() {
		return array( 'X-Content-Type-Options' => 'nosniff' );
	}

	/**
	 * @return array|null
	 */
	protected function getReferrerPolicyHeader() {
		/** @var ICWP_WPSF_FeatureHandler_Headers $oFO */
		$oFO = $this->getMod();
		return array( 'Referrer-Policy' => $oFO->getReferrerPolicyValue() );
	}

	/**
	 * @return array|null
	 */
	protected function setContentSecurityPolicyHeader() {
		/** @var ICWP_WPSF_FeatureHandler_Headers $oFO */
		$oFO = $this->getMod();
		if ( !$oFO->isContentSecurityPolicyEnabled() ) {
			return null;
		}

		$sTemplate = 'default-src %s;';

		$aDefaultSrcDirectives = array();

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
		return array( 'Content-Security-Policy' => sprintf( $sTemplate, implode( " ", $aDefaultSrcDirectives ) ) );
	}

	/**
	 * @return array
	 */
	protected function gatherSecurityHeaders() {
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
			$this->aHeaders = array();
		}
		return $this->aHeaders;
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
	protected function isHeadersPushed() {
		return (bool)$this->bHeadersPushed;
	}

	/**
	 * @param bool $bHeadersPushed
	 * @return $this
	 */
	protected function setHeadersPushed( $bHeadersPushed ) {
		$this->bHeadersPushed = $bHeadersPushed;
		return $this;
	}
}