<?php

if ( class_exists( 'ICWP_WPSF_Processor_Headers' ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

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
	protected function setXFrameHeader() {
		$sXFrame = $this->getOption( 'x_frame' );
		switch ( $sXFrame ) {
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
	 * @return array|null
	 */
	protected function setXssProtectionHeader() {
		return $this->getIsOption( 'x_xss_protect', 'Y' ) ? array( 'X-XSS-Protection' => '1; mode=block' ) : null;
	}

	/**
	 * @return array|null
	 */
	protected function setContentTypeOptionHeader() {
		return $this->getIsOption( 'x_content_type', 'Y' ) ? array( 'X-Content-Type-Options' => 'nosniff' ) : null;
	}

	/**
	 * @return array|null
	 */
	protected function setReferrerPolicyHeader() {
		/** @var ICWP_WPSF_FeatureHandler_Headers $oFO */
		$oFO = $this->getMod();
		$sValue = null;
		if ( $oFO->isReferrerPolicyEnabled() ) {
			$sValue = $oFO->getReferrerPolicyValue();
		}
		return is_string( $sValue ) ? array( 'Referrer-Policy' => $sValue ) : null;
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

		$this->addHeader( $this->setReferrerPolicyHeader() );
		$this->addHeader( $this->setXFrameHeader() );
		$this->addHeader( $this->setXssProtectionHeader() );
		$this->addHeader( $this->setContentTypeOptionHeader() );
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