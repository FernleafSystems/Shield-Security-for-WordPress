<?php

class ICWP_WPSF_FeatureHandler_Statistics extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return string
	 */
	public function getReportingTableName() {
		return $this->prefix( $this->getDef( 'reporting_table_name' ), '_' );
	}

	/**
	 * @return string
	 */
	public function getFullReportingTableName() {
		return $this->loadDbProcessor()->getPrefix().$this->getReportingTableName();
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_statistics' :
				$sTitleShort = sprintf( __( '%s/%s ', 'wp-simple-firewall' ), __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Helps you see at a glance how effective the plugin has been.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), $this->getMainFeatureName() ) )
				];
				break;

			case 'section_enable_plugin_feature_reporting' :
				$sTitleShort = sprintf( __( '%s/%s ', 'wp-simple-firewall' ), __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'To track stats and issue reports.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), $this->getMainFeatureName() ) )
				];
				break;

			case 'section_stats_sharing' :
				$sTitle = __( 'Statistics Sharing', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Help us to provide globally accessible statistics on the effectiveness of the plugin.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Enabling this option helps us improve our plugin over time.', 'wp-simple-firewall' ) )
					.__( 'All statistics data collection is 100% anonymous.', 'wp-simple-firewall' ).__( 'Neither we nor anyone else will be able to trace the data back to the originating site.', 'wp-simple-firewall' )

				];
				$sTitleShort = __( 'Sharing', 'wp-simple-firewall' );
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [];
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_statistics' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				break;

			case 'enable_reporting' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}
}