<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

class Strings extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Strings {

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

	public function getSectionStrings( string $section ) :array {
		$modName = $this->mod()->getMainFeatureName();

		switch ( $section ) {

			case 'section_enable_plugin_feature_traffic' :
				$short = sprintf( '%s/%s', __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$title = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $modName );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Monitor and review all requests to your site.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Required only if you need to review and investigate and monitor requests to your site', 'wp-simple-firewall' ) )
				];
				break;

			case 'section_traffic_options' :
				$short = __( 'Traffic Logging Options', 'wp-simple-firewall' );
				$title = __( 'Traffic Logging Options', 'wp-simple-firewall' );
				$summary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Provides finer control over the Traffic Logging system.', 'wp-simple-firewall' ) ),
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
			'summary'     => $summary,
		];
	}

	public function getOptionStrings( string $key ) :array {
		$con = self::con();
		$modName = $this->mod()->getMainFeatureName();

		switch ( $key ) {

			case 'enable_traffic' :
				$name = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), $modName );
				$summary = sprintf( __( 'Enable (or Disable) The %s Module', 'wp-simple-firewall' ), $modName );
				$desc = [ sprintf( __( 'Un-Checking this option will completely disable the %s module.', 'wp-simple-firewall' ), $modName ) ];
				break;

			case 'enable_logger' :
				$name = __( 'Enable Traffic Logger', 'wp-simple-firewall' );
				$summary = __( 'Turn On The Traffic Logging Feature', 'wp-simple-firewall' );
				$desc = [ __( 'Enable or disable the ability to log and monitor requests to your site', 'wp-simple-firewall' ) ];
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

			case 'enable_live_log' :
				/** @var Options $opts */
				$opts = $this->opts();
				$max = \round( $opts->liveLoggingDuration()/\MINUTE_IN_SECONDS );

				$name = __( 'Live Traffic', 'wp-simple-firewall' );
				$summary = __( 'Temporarily Log All Traffic', 'wp-simple-firewall' );
				$desc = [
					__( "Requires standard traffic logging to be switched-on and logs all requests to the site (nothing is excluded).", 'wp-simple-firewall' ),
					__( "For high-traffic sites, this option can cause your database to become quite large and isn't recommend unless required.", 'wp-simple-firewall' ),
					sprintf( __( 'This setting will automatically be disabled after %s and all requests logged during that period that would normally have been excluded will also be deleted.', 'wp-simple-firewall' ),
						sprintf( _n( '%s minute', '%s minutes', $max ), $max ) ),
					sprintf( '<a href="%s">%s &rarr;</a>',
						$con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE ),
						__( 'Live Logs Viewer', 'wp-simple-firewall' )
					),

				];

				$remaining = $opts->liveLoggingTimeRemaining();
				if ( $remaining > 0 ) {
					$desc[] = sprintf(
						__( 'Live logging will be automatically disabled: %s', 'wp-simple-firewall' ),
						sprintf( '<code>%s</code>', Services::Request()
															->carbon()
															->addSeconds( $remaining )
															->diffForHumans()
						)
					);
				}
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
				$name = __( 'Log Retention', 'wp-simple-firewall' );
				$summary = __( 'Traffic Log Retention Policy (Days)', 'wp-simple-firewall' );
				$desc = [
					__( 'Traffic logs older than this maximum number of days will be automatically deleted.', 'wp-simple-firewall' ),
					sprintf( '%s: %s',
						__( 'Note', 'wp-simple-firewall' ),
						__( 'Activity logs depend on these traffic logs so if they have a longer retention period, some traffic logs will be retained longer.', 'wp-simple-firewall' )
					),
				];
				if ( !$con->caps->hasCap( 'logs_retention_unlimited' ) ) {
					$desc[] = sprintf(
						__( 'The maximum log retention limit (%s) may be increased by upgrading your ShieldPRO plan.', 'wp-simple-firewall' ),
						$con->caps->getMaxLogRetentionDays()
					);
				}
				break;

			case 'enable_limiter' :
				$name = __( 'Enable Rate Limiting', 'wp-simple-firewall' );
				$summary = __( 'Turn On The Rate Limiting Feature', 'wp-simple-firewall' );
				$desc = [
					__( 'Limit requests to your site based on the your rate-limiting settings.', 'wp-simple-firewall' ),
					sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'Enabling this option automatically switches-on the traffic log.', 'wp-simple-firewall' ) ),
				];
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