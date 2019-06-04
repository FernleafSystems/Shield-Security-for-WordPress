<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @param string $sSectionSlug
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_SectionTitles( $sSectionSlug ) {
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_traffic' :
				$sTitleShort = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $sModName );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Monitor and review all requests to your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Required only if you need to review and investigate and monitor requests to your site', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_traffic_options' :
				$sTitle = __( 'Traffic Watch Options', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control over the Traffic Watch system.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'These settings are dependent on your requirements.', 'wp-simple-firewall' ), __( 'User Management', 'wp-simple-firewall' ) ) )
				];
				$sTitleShort = __( 'Traffic Logging Options', 'wp-simple-firewall' );
				break;

			default:
				return parent::loadStrings_SectionTitles( $sSectionSlug );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $sOptKey
	 * @return array
	 * @throws \Exception
	 */
	public function loadStrings_Options( $sOptKey ) {
		/** @var \ICWP_WPSF_FeatureHandler_Traffic $oMod */
		$oMod = $this->getMod();
		$sModName = $oMod->getMainFeatureName();

		switch ( $sOptKey ) {

			case 'enable_traffic' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			case 'type_exclusions' :
				$sName = __( 'Traffic Log Exclusions', 'wp-simple-firewall' );
				$sSummary = __( 'Select Which Types Of Requests To Exclude', 'wp-simple-firewall' );
				$sDescription = __( "Select request types that you don't want to appear in the traffic viewer.", 'wp-simple-firewall' )
								.'<br/>'.__( 'If a request matches any exclusion rule, it will not show on the traffic viewer.', 'wp-simple-firewall' );
				break;

			case 'custom_exclusions' :
				$sName = __( 'Custom Exclusions', 'wp-simple-firewall' );
				$sSummary = __( 'Provide Custom Traffic Exclusions', 'wp-simple-firewall' );
				$sDescription = __( "For each entry, if the text is present in either the User Agent or request Path, it will be excluded.", 'wp-simple-firewall' )
								.'<br/>'.__( 'Take a new line for each entry.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Comparisons are case-insensitive.', 'wp-simple-firewall' );
				break;

			case 'auto_clean' :
				$sName = __( 'Auto Expiry Cleaning', 'wp-simple-firewall' );
				$sSummary = __( 'Enable Traffic Log Auto Expiry', 'wp-simple-firewall' );
				$sDescription = __( 'DB cleanup will delete logs older than this maximum value (in days).', 'wp-simple-firewall' );
				break;

			case 'max_entries' :
				$sName = __( 'Max Log Length', 'wp-simple-firewall' );
				$sSummary = __( 'Maximum Traffic Log Length To Keep', 'wp-simple-firewall' );
				$sDescription = __( 'DB cleanup will delete logs to maintain this maximum number of records.', 'wp-simple-firewall' );
				break;

			case 'auto_disable' :
				$sName = __( 'Auto Disable', 'wp-simple-firewall' );
				$sSummary = __( 'Auto Disable Traffic Logging After 1 Week', 'wp-simple-firewall' );

				if ( $oMod->isAutoDisable() ) {
					$sTimestamp = '<br/>'.sprintf( __( 'Auto Disable At: %s', 'wp-simple-firewall' ), $oMod->getAutoDisableTimestamp() );
				}
				else {
					$sTimestamp = '';
				}
				$sDescription = __( 'Turn on to prevent unnecessary long-term traffic logging.', 'wp-simple-firewall' )
								.'<br />'.__( 'Timer resets after options save.', 'wp-simple-firewall' )
								.$sTimestamp;
				break;

			default:
				return parent::loadStrings_Options( $sOptKey );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}
}