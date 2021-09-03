<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Strings extends Base\Strings {

	/**
	 * @inheritDoc
	 */
	public function getEventStrings() :array {
		return [
			'request_limit_exceeded' => [
				'name'  => __( 'Rate Limit Exceeded', 'wp-simple-firewall' ),
				'audit' => [
					__( 'Rate limit ({{count}}) was exceeded with {{requests}} requests within {{span}} seconds.', 'wp-simple-firewall' ),
				],
			],
		];
	}

	/**
	 * @param string $section
	 * @return array
	 * @throws \Exception
	 */
	public function getSectionStrings( string $section ) :array {
		$sModName = $this->getMod()->getMainFeatureName();

		switch ( $section ) {

			case 'section_enable_plugin_feature_traffic' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $sModName );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Monitor and review all requests to your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Required only if you need to review and investigate and monitor requests to your site', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_traffic_options' :
				$short = __( 'Traffic Logging Options', 'wp-simple-firewall' );
				$title = __( 'Traffic Watch Options', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control over the Traffic Watch system.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'These settings are dependent on your requirements.', 'wp-simple-firewall' ), __( 'User Management', 'wp-simple-firewall' ) ) )
				];
				break;

			case 'section_traffic_limiter' :
				$title = __( 'Brute Force Traffic Rate Limiting', 'wp-simple-firewall' );
				$short = __( 'Traffic Rate Limiting', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Prevents excessive requests from a single visitor.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Important', 'wp-simple-firewall' ), sprintf( __( 'This feature is only available while the Traffic Logger is active.', 'wp-simple-firewall' ), __( 'User Management', 'wp-simple-firewall' ) ) ),
					sprintf( '%s - %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Use this feature with care.', 'wp-simple-firewall' ) )
					.' '.__( 'You could block legitimate visitors who load too many pages in quick succession on your site.', 'wp-simple-firewall' )
				];
				break;

			default:
				return parent::getSectionStrings( $section );
		}

		return [
			'title'       => $title,
			'title_short' => $short,
			'summary'     => ( isset( $summary ) && is_array( $summary ) ) ? $summary : [],
		];
	}

	/**
	 * @param string $key
	 * @return array
	 * @throws \Exception
	 */
	public function getOptionStrings( string $key ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$sModName = $mod->getMainFeatureName();

		switch ( $key ) {

			case 'enable_traffic' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $sModName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $sModName );
				$desc = sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $sModName );
				break;

			case 'enable_logger' :
				$name = __( 'Enable Traffic Logger', 'wp-simple-firewall' );
				$summary = __( 'Turn On The Traffic Logging Feature', 'wp-simple-firewall' );
				$desc = __( 'Enable or disable the ability to log and monitor requests to your site', 'wp-simple-firewall' );
				break;

			case 'type_exclusions' :
				$name = __( 'Traffic Log Exclusions', 'wp-simple-firewall' );
				$summary = __( 'Select Which Types Of Requests To Exclude', 'wp-simple-firewall' );
				$desc = [
					__( "There's no need to have unnecessary traffic noise in your logs, so we automatically exclude certain types of requests.", 'wp-simple-firewall' ),
					__( "Select request types that you don't want to appear in the traffic viewer.", 'wp-simple-firewall' ),
					__( 'If a request matches any exclusion rule, it wont show in the traffic logs.', 'wp-simple-firewall' )
				];
				break;

			case 'custom_exclusions' :
				$name = __( 'Custom Exclusions', 'wp-simple-firewall' );
				$summary = __( 'Provide Custom Traffic Exclusions', 'wp-simple-firewall' );
				$desc = [
					__( "For each entry, if the text is present in either the User Agent or request Path, it will be excluded.", 'wp-simple-firewall' ),
					__( 'Take a new line for each entry.', 'wp-simple-firewall' ),
					__( 'Comparisons are case-insensitive.', 'wp-simple-firewall' )
				];
				break;

			case 'auto_clean' :
				$name = __( 'Auto Expiry Cleaning', 'wp-simple-firewall' );
				$summary = __( 'Enable Traffic Log Auto Expiry', 'wp-simple-firewall' );
				$desc = __( 'DB cleanup will delete logs older than this maximum value (in days).', 'wp-simple-firewall' );
				break;

			case 'max_entries' :
				$name = __( 'Max Log Length', 'wp-simple-firewall' );
				$summary = __( 'Maximum Traffic Log Length To Keep', 'wp-simple-firewall' );
				$desc = __( 'DB cleanup will delete logs to maintain this maximum number of records.', 'wp-simple-firewall' );
				break;

			case 'enable_limiter' :
				$name = __( 'Enable Rate Limiting', 'wp-simple-firewall' );
				$summary = __( 'Turn On The Rate Limiting Feature', 'wp-simple-firewall' );
				$desc = __( 'Enable or disable the rate limiting feature according to your rate limiting parameters.', 'wp-simple-firewall' );
				break;

			case 'limit_requests' :
				$name = __( 'Max Request Limit', 'wp-simple-firewall' );
				$summary = __( 'Maximum Number Of Requests Allowed In Time Limit', 'wp-simple-firewall' );
				$desc = [
					__( 'The maximum number of requests that are allowed within the given request time limit.', 'wp-simple-firewall' ),
					__( 'Any visitor that exceeds this number of requests in the given time period will register an offense against their IP address.', 'wp-simple-firewall' ),
					__( 'Enough offenses will result in a ban of the IP address.', 'wp-simple-firewall' ),
					__( 'Use a larger maximum request limit to reduce the risk of blocking legitimate visitors.', 'wp-simple-firewall' )
				];
				break;

			case 'limit_time_span' :
				$name = __( 'Request Limit Time Interval', 'wp-simple-firewall' );
				$summary = __( 'The Time Interval To Test For Excessive Requests', 'wp-simple-firewall' );
				$desc = [
					__( 'The time period within which to monitor for multiple requests that exceed the max request limit.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'Interval is measured in seconds.', 'wp-simple-firewall' ) ),
					sprintf( '%s: %s', __( 'Example', 'wp-simple-firewall' ),
						sprintf( __( 'Use %s to test for excessive requests within a %s minutes interval.', 'wp-simple-firewall' ), '<code>300</code>', 5 ) ),
					sprintf( '%s: %s', __( 'Example', 'wp-simple-firewall' ),
						sprintf( __( 'Use %s to test for excessive requests within a %s minutes interval.', 'wp-simple-firewall' ), '<code>3600</code>', 60 ) ),
					__( 'Use a smaller interval to reduce the risk of blocking legitimate visitors.', 'wp-simple-firewall' )
				];
				break;

			default:
				return parent::getOptionStrings( $key );
		}

		return [
			'name'        => $name,
			'summary'     => $summary,
			'description' => $desc,
		];
	}
}