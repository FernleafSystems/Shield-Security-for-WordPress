<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @return string[][]
	 */
	protected function getAuditMessages() {
		return [
			'request_limit_exceeded' => [
				__( 'Visitor exceeded the maximum allowable requests (%s) within %s seconds.', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( $section ) {
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $section ) {

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

			case 'section_traffic_limiter' :
				$sTitle = __( 'Brute Force Traffic Rate Limiting', 'wp-simple-firewall' );
				$sTitleShort = __( 'Traffic Rate Limiting', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Prevents excessive requests from a single visitor.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ), sprintf( __( 'This feature is only available while the Traffic Logger is active.', 'wp-simple-firewall' ), __( 'User Management', 'wp-simple-firewall' ) ) ),
					sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Use this feature with care.', 'wp-simple-firewall' ) )
					.' '.sprintf( __( 'You could block legitimate visitors who load too many pages in quick succession on your site.', 'wp-simple-firewall' ) )
				];
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $sTitle,
			'title_short' => $sTitleShort,
			'summary'     => ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [],
		];
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( $key ) {
		/** @var \ICWP_WPSF_FeatureHandler_Traffic $oMod */
		$oMod = $this->getMod();
		$sModName = $oMod->getMainFeatureName();

		switch ( $key ) {

			case 'enable_traffic' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$sSummary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$sDescription = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			case 'enable_logger' :
				$sName = __( 'Enable Traffic Logger', 'wp-simple-firewall' );
				$sSummary = __( 'Turn On The Traffic Logging Feature', 'wp-simple-firewall' );
				$sDescription = __( 'Enable or disable the ability to log and monitor requests to your site', 'wp-simple-firewall' );
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

			case 'enable_limiter' :
				$sName = __( 'Enable Rate Limiting', 'wp-simple-firewall' );
				$sSummary = __( 'Turn On The Rate Limiting Feature', 'wp-simple-firewall' );
				$sDescription = __( 'Enable or disable the rate limiting feature according to your rate limiting parameters.', 'wp-simple-firewall' );
				break;

			case 'limit_requests' :
				$sName = __( 'Max Request Limit', 'wp-simple-firewall' );
				$sSummary = __( 'Maximum Number Of Requests Allowed In Time Limit', 'wp-simple-firewall' );
				$sDescription = __( 'The maximum number of requests that are allowed within the given request time limit.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Any visitor that exceeds this number of requests in the given time period will register an offense against their IP address.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Enough offenses will result in a ban of the IP address.', 'wp-simple-firewall' )
								.'<br/>'.__( 'Use a larger maximum request limit to reduce the risk of blocking legitimate visitors.', 'wp-simple-firewall' );
				break;

			case 'limit_time_span' :
				$sName = __( 'Request Limit Time Interval', 'wp-simple-firewall' );
				$sSummary = __( 'The Time Interval To Test For Excessive Requests', 'wp-simple-firewall' );
				$sDescription = __( 'The time period within which to monitor for multiple requests that exceed the max request limit.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'Interval is measured in seconds.', 'wp-simple-firewall' ) )
								.'<br/>'.sprintf( '%s: %s', __( 'Example', 'wp-simple-firewall' ),
						sprintf( __( 'Use %s to test for excessive requests within a %s minutes interval.', 'wp-simple-firewall' ), '<code>300</code>', 5 ) )
								.'<br/>'.sprintf( '%s: %s', __( 'Example', 'wp-simple-firewall' ),
						sprintf( __( 'Use %s to test for excessive requests within a %s minutes interval.', 'wp-simple-firewall' ), '<code>3600</code>', 60 ) )
								.'<br/>'.__( 'Use a smaller interval to reduce the risk of blocking legitimate visitors.', 'wp-simple-firewall' );
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $sName,
			'summary'     => $sSummary,
			'description' => $sDescription,
		];
	}
}