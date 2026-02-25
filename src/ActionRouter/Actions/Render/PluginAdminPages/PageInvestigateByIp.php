<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\Container as IpAnalyseContainer;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\LoadRequestLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
use FernleafSystems\Wordpress\Services\Services;

class PageInvestigateByIp extends BasePluginAdminPage {

	use InvestigateCountCache;
	use InvestigateStatusMapping;

	public const SLUG = 'plugin_admin_page_investigate_by_ip';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_by_ip.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$lookup = \trim( sanitize_text_field( (string)Services::Request()->query( 'analyse_ip', '' ) ) );
		$hasLookup = !empty( $lookup );
		$hasSubject = $hasLookup && Services::IP()->isValidIp( $lookup );

		$summary = [];
		$subject = [];
		$ipAnalysis = '';
		if ( $hasSubject ) {
			$counts = $this->buildSummaryCounts( $lookup );
			$summary = [
				'sessions' => [
					'label'  => __( 'Sessions', 'wp-simple-firewall' ),
					'count'  => $counts[ 'sessions' ],
					'status' => $this->mapCountToStatus( $counts[ 'sessions' ], 'info', 'good' ),
				],
				'activity' => [
					'label'  => __( 'Activity', 'wp-simple-firewall' ),
					'count'  => $counts[ 'activity' ],
					'status' => $this->mapCountToStatus( $counts[ 'activity' ], 'info', 'warning' ),
				],
				'requests' => [
					'label'  => __( 'Requests', 'wp-simple-firewall' ),
					'count'  => $counts[ 'requests' ],
					'status' => $this->highestStatus( [
						$this->mapCountToStatus( $counts[ 'requests' ], 'info', 'warning' ),
						$counts[ 'offenses' ] > 0 ? 'critical' : '',
					], 'info' ),
				],
				'offenses' => [
					'label'  => __( 'Offenses', 'wp-simple-firewall' ),
					'count'  => $counts[ 'offenses' ],
					'status' => $this->mapCountToStatus( $counts[ 'offenses' ], 'good', 'critical' ),
				],
			];

			$subject = [
				'status'       => $this->highestStatus( [ $counts[ 'offenses' ] > 0 ? 'warning' : '' ], 'info' ),
				'title'        => $lookup,
				'avatar_icon'  => $con->svgs->iconClass( 'globe2' ),
				'meta'         => [
					[
						'label' => __( 'IP Address', 'wp-simple-firewall' ),
						'value' => $lookup,
					],
				],
				'status_pills' => [
					[
						'status' => $this->mapCountToStatus( $counts[ 'offenses' ], 'good', 'critical' ),
						'label'  => $counts[ 'offenses' ] > 0
							? \sprintf( _n( '%d offense', '%d offenses', $counts[ 'offenses' ], 'wp-simple-firewall' ), $counts[ 'offenses' ] )
							: __( 'No offenses', 'wp-simple-firewall' ),
					],
				],
				'change_href'  => $con->plugin_urls->investigateByIp(),
				'change_text'  => __( 'Change IP', 'wp-simple-firewall' ),
			];

			$ipAnalysis = $con->action_router->render( IpAnalyseContainer::class, [
				'ip' => $lookup,
			] );
		}

		return [
			'flags'   => [
				'has_lookup'  => $hasLookup,
				'has_subject' => $hasSubject,
			],
			'hrefs'   => [
				'back_to_investigate' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ),
				'by_ip'               => $con->plugin_urls->investigateByIp(),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'globe2' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Investigate By IP', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Inspect sessions, activity, and request behavior for a specific IP address.', 'wp-simple-firewall' ),
				'lookup_label'        => __( 'IP Lookup', 'wp-simple-firewall' ),
				'lookup_placeholder'  => __( 'IPv4 or IPv6 address', 'wp-simple-firewall' ),
				'lookup_submit'       => __( 'Load IP Context', 'wp-simple-firewall' ),
				'back_to_investigate' => __( 'Back To Investigate', 'wp-simple-firewall' ),
				'no_subject_title'    => __( 'No IP Selected', 'wp-simple-firewall' ),
				'no_subject_text'     => __( 'Enter a valid IP address to load investigate context for that subject.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'analyse_ip'   => $lookup,
				'lookup_route' => [
					'page'    => $con->plugin_urls->rootAdminPageSlug(),
					'nav'     => PluginNavs::NAV_ACTIVITY,
					'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_IP,
				],
				'subject'      => $subject,
				'summary'      => $summary,
			],
			'content' => [
				'ip_analysis' => $ipAnalysis,
			],
		];
	}

	protected function buildSummaryCounts( string $ip ) :array {
		$sessions = $this->cachedCount( 'sessions', 'ip', $ip, function () use ( $ip ) :int {
			$total = 0;
			foreach ( ( new FindSessions() )->byIP( $ip ) as $byUser ) {
				$total += \count( $byUser );
			}
			return $total;
		} );

		$activity = $this->cachedCount( 'activity', 'ip', $ip, function () use ( $ip ) :int {
			$activityLoader = ( new LoadLogs() )->setIP( $ip );
			return $activityLoader->countAll();
		} );

		$requests = $this->cachedCount( 'requests', 'ip', $ip, function () use ( $ip ) :int {
			$requestLoader = ( new LoadRequestLogs() )->setIP( $ip );
			return $requestLoader->countAll();
		} );

		$offenses = $this->cachedCount( 'offenses', 'ip', $ip, function () use ( $ip ) :int {
			$offenseLoader = ( new LoadRequestLogs() )->setIP( $ip );
			$offenseLoader->wheres = [ '`req`.`offense`=1' ];
			return $offenseLoader->countAll();
		} );

		return [
			'sessions' => $sessions,
			'activity' => $activity,
			'requests' => $requests,
			'offenses' => $offenses,
		];
	}

}
