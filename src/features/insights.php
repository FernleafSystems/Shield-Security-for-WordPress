<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_Insights', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_Insights extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @param array $aData
	 */
	protected function displayModulePage( $aData = array() ) {
		$oWp = $this->loadWp();

		$aData = array(
			'vars'    => array(
				'activation_url' => $oWp->getHomeUrl(),
				'summary'        => $this->getModsSummary()
			),
			'inputs'  => array(
				'license_key' => array(
					'name'      => $this->prefixOptionKey( 'license_key' ),
					'maxlength' => $this->getDef( 'license_key_length' ),
				)
			),
			'ajax'    => array(
				'license_handling' => $this->getAjaxActionData( 'license_handling' ),
				'connection_debug' => $this->getAjaxActionData( 'connection_debug' )
			),
			'aHrefs'  => array(
				'shield_pro_url'           => 'http://icwp.io/shieldpro',
				'shield_pro_more_info_url' => 'http://icwp.io/shld1',
				'iframe_url'               => $this->getDef( 'landing_page_url' ),
				'keyless_cp'               => $this->getDef( 'keyless_cp' ),
			),
			'flags'   => array(
				'show_ads'              => false,
				'show_standard_options' => false,
				'show_alt_content'      => true,
			),
			'strings' => $this->getDisplayStrings(),
		);

//		var_dump( $aData[ 'vars' ][ 'summary' ] );

		echo $this->renderTemplate( '/wpadmin_pages/insights', $aData, true );
	}

	/**
	 * @return array[]
	 */
	protected function getModsSummary() {
		$aMods = apply_filters( $this->prefix( 'get_feature_summary_data' ), array() );
		foreach ( $aMods as $nKey => $aMod ) {
			if ( in_array( $aMod[ 'slug' ], [ 'plugin', 'insights' ] ) ) {
				unset( $aMods[ $nKey ] );
			}
		}
		return array_values( $aMods );
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_email_options' :
				$sTitle = _wpsf__( 'Email Options' );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
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
			case 'send_email_throttle_limit' :
				$sName = _wpsf__( 'Email Throttle Limit' );
				$sSummary = _wpsf__( 'Limit Emails Per Second' );
				$sDescription = _wpsf__( 'You throttle emails sent by this plugin by limiting the number of emails sent every second. This is useful in case you get hit by a bot attack. Zero (0) turns this off. Suggested: 10' );
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		$sLimit = $this->getOpt( 'send_email_throttle_limit' );
		if ( !is_numeric( $sLimit ) || $sLimit < 0 ) {
			$sLimit = 0;
		}
		$this->setOpt( 'send_email_throttle_limit', $sLimit );
	}
}