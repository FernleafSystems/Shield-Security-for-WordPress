<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_Support', false ) ) {
	return;
}

require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_Support extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 */
	public function displayFeatureConfigPage() {
		$aData = array(
			'has_premium_support' => $this->getHasPremiumSupport(),
			'aHrefs'              => array(
				'shield_pro_url'           => 'http://icwp.io/shieldpro',
				'shield_pro_more_info_url' => 'http://icwp.io/shld1',
				'iframe_url'               => $this->getDefinition( 'landing_page_url' ),
			),
			'bShowStateSummary'   => false,
			'flags'               => array(
				'wrap_page_content' => false,
			),
		);
		$this->display( $aData, 'feature-support' );
	}

	/**
	 * @return bool
	 */
	public function getIfShowFeatureMenuItem() {
		return !$this->getHasPremiumSupport();
	}

	/**
	 * @return bool
	 */
	public function getHasPremiumSupport() {
		return $this->getIcwpLinked(); // todo - detect whether Shield addon is active for this site.
//			return apply_filters( $this->prefix( 'has_premium_support' ), $this->getIcwpLinked() );
	}

	/**
	 * @return bool
	 */
	protected function getHasIcwpPluginActive() {
		return ( class_exists( 'ICWP_Plugin' ) && method_exists( 'ICWP_Plugin', 'IsLinked' ) );
	}

	/**
	 * @return bool
	 */
	protected function getIcwpLinked() {
		return ( $this->getHasIcwpPluginActive() && ICWP_Plugin::IsLinked() && $this->getIcwpPluginMeetsMinimumVersion() );
	}

	/**
	 * @return bool
	 */
	protected function getIcwpPluginMeetsMinimumVersion() {
		return version_compare( ICWP_Plugin::GetVersion(), '3.4', '>=' );
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_support' :
				$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Contact Plugin Premium Support Centre.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), $this->getMainFeatureName() ) )
				);
				$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_support' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), $this->getMainFeatureName() );
				$sSummary = sprintf( _wpsf__( 'Enable (or Disable) The %s Feature' ), $this->getMainFeatureName() );
				$sDescription = sprintf( _wpsf__( 'Checking/Un-Checking this option will completely turn on/off the whole %s feature.' ), $this->getMainFeatureName() );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}