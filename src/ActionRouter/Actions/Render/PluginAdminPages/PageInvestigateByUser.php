<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\Investigation\InvestigationTableContract,
	Actions\InvestigationTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\{
	LoadLogs,
	LogRecord as ActivityLogRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\{
	LoadRequestLogs,
	LogRecord as RequestLogRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\{
	ResolveUserLookup,
	Session\FindSessions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\{
	BaseInvestigationTable,
	ForActivityLog as InvestigationActivityTableBuilder,
	ForSessions as InvestigationSessionsTableBuilder,
	ForTraffic as InvestigationTrafficTableBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class PageInvestigateByUser extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_investigate_by_user';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_by_user.twig';
	private const LIMIT_SESSIONS = 50;
	private const LIMIT_ACTIVITY_LOGS = 75;
	private const LIMIT_REQUEST_LOGS = 75;

	protected function getRenderData() :array {
		$con = self::con();
		$lookup = $this->getTextInputFromRequestOrActionData( 'user_lookup' );
		$subject = $this->resolveSubject( $lookup );
		$hasLookup = !empty( $lookup );
		$hasSubject = $subject instanceof \WP_User;
		$subjectNotFound = $hasLookup && !$hasSubject;

		$subjectData = [];
		$summaryStats = [];
		$railNavItems = [];
		$tables = [];
		$relatedIps = [];

		if ( $hasSubject ) {
			$sessions = $this->buildSessions( $subject );
			$activityLogs = $this->buildActivityLogs( $subject );
			$requestLogs = $this->buildRequestLogs( $subject );
			$relatedIps = $this->buildRelatedIps( $sessions, $activityLogs, $requestLogs );
			$summaryStats = $this->buildSummaryStats( $sessions, $activityLogs, $requestLogs, $relatedIps );
			$railNavItems = $this->buildRailNavItems( $summaryStats );
			$tables = $this->buildTableContractsForUser( (int)$subject->ID );
			$subjectData = $this->buildSubjectHeaderData( $subject );
		}

		return [
			'flags'   => [
				'has_subject'       => $hasSubject,
				'has_lookup'        => $hasLookup,
				'subject_not_found' => $subjectNotFound,
			],
			'hrefs'   => [
				'back_to_investigate' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ),
				'by_user'             => $con->plugin_urls->investigateByUser(),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'person-lines-fill' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Investigate By User', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Select a user to review recent sessions, activity, and requests.', 'wp-simple-firewall' ),
				'lookup_label'        => __( 'User Lookup', 'wp-simple-firewall' ),
				'lookup_placeholder'  => __( 'User ID, username, or email', 'wp-simple-firewall' ),
				'lookup_submit'       => __( 'Load User Context', 'wp-simple-firewall' ),
				'back_to_investigate' => __( 'Back To Investigate', 'wp-simple-firewall' ),
				'no_subject_title'    => __( 'No User Selected', 'wp-simple-firewall' ),
				'no_subject_text'     => __( 'Enter a user ID, email, or username to load user-scoped investigate data.', 'wp-simple-firewall' ),
				'not_found_title'     => __( 'No Matching User Found', 'wp-simple-firewall' ),
				'not_found_text'      => __( 'No WordPress user matched your lookup value. Check the ID, username, or email and try again.', 'wp-simple-firewall' ),
				'related_ips_title'   => __( 'Related IP Addresses', 'wp-simple-firewall' ),
				'related_ips_empty'   => __( 'No related IP addresses were found.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'user_lookup'    => $lookup,
				'lookup_route'   => [
					'page'    => $con->plugin_urls->rootAdminPageSlug(),
					'nav'     => PluginNavs::NAV_ACTIVITY,
					'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_USER,
				],
				'subject'        => $subjectData,
				'summary'        => $summaryStats,
				'rail_nav_items' => $railNavItems,
				'tables'         => $tables,
				'related_ips'    => $relatedIps,
			],
		];
	}

	protected function resolveSubject( string $lookup ) :?\WP_User {
		return ( new ResolveUserLookup() )->resolve( $lookup );
	}

	protected function buildSubjectHeaderData( \WP_User $subject ) :array {
		$displayName = \trim( (string)$subject->display_name );
		$title = empty( $displayName ) ? (string)$subject->user_login : $displayName;
		return [
			'status'      => 'info',
			'title'       => $title,
			'avatar_icon' => self::con()->svgs->iconClass( 'person-fill' ),
			'avatar_text' => \strtoupper( \substr( $title, 0, 1 ) ?: 'U' ),
			'meta'        => [
				[
					'label' => __( 'ID', 'wp-simple-firewall' ),
					'value' => (string)$subject->ID,
				],
				[
					'label' => __( 'Login', 'wp-simple-firewall' ),
					'value' => (string)$subject->user_login,
				],
				[
					'label' => __( 'Email', 'wp-simple-firewall' ),
					'value' => (string)$subject->user_email,
				],
				[
					'label' => __( 'Display', 'wp-simple-firewall' ),
					'value' => (string)$subject->display_name,
				],
			],
			'change_href' => self::con()->plugin_urls->investigateByUser(),
			'change_text' => __( 'Change User', 'wp-simple-firewall' ),
		];
	}

	protected function buildSummaryStats( array $sessions, array $activityLogs, array $requestLogs, array $relatedIps ) :array {
		$sessionsCount = \count( $sessions );
		$activityCount = \count( $activityLogs );
		$requestsCount = \count( $requestLogs );
		$relatedIpsCount = \count( $relatedIps );

		$hasRequestOffense = false;
		foreach ( $requestLogs as $row ) {
			if ( !empty( $row[ 'offense' ] ) ) {
				$hasRequestOffense = true;
				break;
			}
		}

		$ipsStatus = StatusPriority::highest(
			\array_map(
				static fn( array $card ) :string => (string)( $card[ 'status' ] ?? 'info' ),
				$relatedIps
			),
			'info'
		);

		return [
			'sessions' => [
				'label'  => __( 'Sessions', 'wp-simple-firewall' ),
				'count'  => $sessionsCount,
				'status' => $sessionsCount > 0 ? 'good' : 'info',
			],
			'activity' => [
				'label'  => __( 'Activity', 'wp-simple-firewall' ),
				'count'  => $activityCount,
				'status' => $activityCount > 0 ? 'warning' : 'info',
			],
			'requests' => [
				'label'  => __( 'Requests', 'wp-simple-firewall' ),
				'count'  => $requestsCount,
				'status' => $hasRequestOffense ? 'critical' : ( $requestsCount > 0 ? 'warning' : 'info' ),
			],
			'ips'      => [
				'label'  => __( 'IP Addresses', 'wp-simple-firewall' ),
				'count'  => $relatedIpsCount,
				'status' => $ipsStatus,
			],
		];
	}

	protected function buildRailNavItems( array $summaryStats ) :array {
		$sessionSummary = $summaryStats[ 'sessions' ] ?? [
			'label' => __( 'Sessions', 'wp-simple-firewall' ),
			'count' => 0
		];
		$activitySummary = $summaryStats[ 'activity' ] ?? [
			'label' => __( 'Activity', 'wp-simple-firewall' ),
			'count' => 0
		];
		$requestSummary = $summaryStats[ 'requests' ] ?? [
			'label' => __( 'Requests', 'wp-simple-firewall' ),
			'count' => 0
		];
		$ipSummary = $summaryStats[ 'ips' ] ?? [ 'label' => __( 'IP Addresses', 'wp-simple-firewall' ), 'count' => 0 ];

		return [
			[
				'target'   => '#tabInvestigateUserOverview',
				'id'       => 'tab-navlink-user-overview',
				'controls' => 'tabInvestigateUserOverview',
				'label'    => __( 'Overview', 'wp-simple-firewall' ),
				'is_focus' => true,
			],
			[
				'target'   => '#tabInvestigateUserSessions',
				'id'       => 'tab-navlink-user-sessions',
				'controls' => 'tabInvestigateUserSessions',
				'label'    => \sprintf( '%s (%d)', (string)$sessionSummary[ 'label' ], (int)$sessionSummary[ 'count' ] ),
				'is_focus' => false,
			],
			[
				'target'   => '#tabInvestigateUserActivity',
				'id'       => 'tab-navlink-user-activity',
				'controls' => 'tabInvestigateUserActivity',
				'label'    => \sprintf( '%s (%d)', (string)$activitySummary[ 'label' ], (int)$activitySummary[ 'count' ] ),
				'is_focus' => false,
			],
			[
				'target'   => '#tabInvestigateUserRequests',
				'id'       => 'tab-navlink-user-requests',
				'controls' => 'tabInvestigateUserRequests',
				'label'    => \sprintf( '%s (%d)', (string)$requestSummary[ 'label' ], (int)$requestSummary[ 'count' ] ),
				'is_focus' => false,
			],
			[
				'target'   => '#tabInvestigateUserIps',
				'id'       => 'tab-navlink-user-ips',
				'controls' => 'tabInvestigateUserIps',
				'label'    => \sprintf( '%s (%d)', (string)$ipSummary[ 'label' ], (int)$ipSummary[ 'count' ] ),
				'is_focus' => false,
			],
		];
	}

	protected function buildTableContractsForUser( int $uid ) :array {
		$subjectType = InvestigationTableContract::SUBJECT_TYPE_USER;
		$tableAction = ActionData::Build( InvestigationTableAction::class );
		$searchToken = \sprintf( 'user_id:%d', $uid );

		$specs = [
			'sessions' => [
				'title'      => __( 'Recent Sessions', 'wp-simple-firewall' ),
				'status'     => 'good',
				'table_type' => InvestigationTableContract::TABLE_TYPE_SESSIONS,
				'builder'    => new InvestigationSessionsTableBuilder(),
				'href'       => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_SESSIONS ),
			],
			'activity' => [
				'title'      => __( 'Recent Activity Logs', 'wp-simple-firewall' ),
				'status'     => 'warning',
				'table_type' => InvestigationTableContract::TABLE_TYPE_ACTIVITY,
				'builder'    => new InvestigationActivityTableBuilder(),
				'href'       => $this->buildFullLogHrefWithSearch( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS, $searchToken ),
			],
			'requests' => [
				'title'      => __( 'Recent Request Logs', 'wp-simple-firewall' ),
				'status'     => 'warning',
				'table_type' => InvestigationTableContract::TABLE_TYPE_TRAFFIC,
				'builder'    => new InvestigationTrafficTableBuilder(),
				'href'       => $this->buildFullLogHrefWithSearch( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS, $searchToken ),
			],
		];

		$contracts = [];
		foreach ( $specs as $key => $spec ) {
			$contracts[ $key ] = $this->buildUserInvestigationTableContract(
				$uid,
				$subjectType,
				$tableAction,
				$spec[ 'title' ],
				$spec[ 'status' ],
				$spec[ 'table_type' ],
				$spec[ 'builder' ],
				$spec[ 'href' ]
			);
		}
		return $contracts;
	}

	private function buildUserInvestigationTableContract(
		int $uid,
		string $subjectType,
		array $tableAction,
		string $title,
		string $status,
		string $tableType,
		BaseInvestigationTable $tableBuilder,
		string $fullLogHref
	) :array {
		return [
			'title'           => $title,
			'status'          => $status,
			'table_type'      => $tableType,
			'subject_type'    => $subjectType,
			'subject_id'      => $uid,
			'datatables_init' => $tableBuilder
				->setSubject( $subjectType, $uid )
				->buildRaw(),
			'table_action'    => $tableAction,
			'full_log_href'   => $fullLogHref,
			'full_log_text'   => __( 'Full Log', 'wp-simple-firewall' ),
		];
	}

	protected function buildFullLogHrefWithSearch( string $nav, string $subNav, string $search ) :string {
		return URL::Build(
			self::con()->plugin_urls->adminTopNav( $nav, $subNav ),
			[
				'search' => $search,
			]
		);
	}

	protected function buildSessions( \WP_User $subject ) :array {
		$sessions = [];
		foreach ( ( new FindSessions() )->byUser( (int)$subject->ID ) as $userSessions ) {
			foreach ( $userSessions as $session ) {
				$loginAt = (int)( $session[ 'login' ] ?? 0 );
				$activityAt = (int)( $session[ 'shield' ][ 'last_activity_at' ] ?? $loginAt );
				$sessions[] = [
					'ip'               => (string)( $session[ 'shield' ][ 'ip' ] ?? '' ),
					'last_activity_ts' => $activityAt,
				];
			}
		}

		\uasort( $sessions, static function ( array $a, array $b ) :int {
			return $b[ 'last_activity_ts' ] <=> $a[ 'last_activity_ts' ];
		} );

		$sessions = \array_slice( $sessions, 0, self::LIMIT_SESSIONS );
		return \array_values( $sessions );
	}

	protected function buildActivityLogs( \WP_User $subject ) :array {
		$loader = ( new LoadLogs() )->forUserId( (int)$subject->ID );
		$loader->limit = self::LIMIT_ACTIVITY_LOGS;
		$loader->order_by = 'created_at';
		$loader->order_dir = 'DESC';

		return \array_values( \array_map(
			fn( ActivityLogRecord $record ) :array => [
				'created_at_ts' => (int)$record->created_at,
				'ip'            => (string)$record->ip,
			],
			$loader->run()
		) );
	}

	protected function buildRequestLogs( \WP_User $subject ) :array {
		$loader = ( new LoadRequestLogs() )->forUserId( (int)$subject->ID );
		$loader->limit = self::LIMIT_REQUEST_LOGS;
		$loader->order_by = 'created_at';
		$loader->order_dir = 'DESC';

		return \array_values( \array_map(
			static function ( RequestLogRecord $record ) :array {
				return [
					'created_at_ts' => (int)$record->created_at,
					'ip'            => (string)$record->ip,
					'offense'       => (bool)$record->offense,
				];
			},
			$loader->select()
		) );
	}

	private function buildRelatedIps( array $sessions, array $activityLogs, array $requestLogs ) :array {
		return $this->buildRelatedIpCards( $sessions, $activityLogs, $requestLogs );
	}

	private function buildRelatedIpCards( array $sessions, array $activityLogs, array $requestLogs ) :array {
		$byIp = [];

		foreach ( $sessions as $session ) {
			$ip = (string)( $session[ 'ip' ] ?? '' );
			if ( $ip !== '' ) {
				$ts = (int)( $session[ 'last_activity_ts' ] ?? 0 );
				$byIp[ $ip ] = $byIp[ $ip ] ?? $this->newIpCardSeed( $ip );
				$byIp[ $ip ][ 'sessions_count' ]++;
				$byIp[ $ip ][ 'last_seen_ts' ] = \max( $byIp[ $ip ][ 'last_seen_ts' ], $ts );
			}
		}
		foreach ( $activityLogs as $log ) {
			$ip = (string)( $log[ 'ip' ] ?? '' );
			if ( $ip !== '' ) {
				$ts = (int)( $log[ 'created_at_ts' ] ?? 0 );
				$byIp[ $ip ] = $byIp[ $ip ] ?? $this->newIpCardSeed( $ip );
				$byIp[ $ip ][ 'activity_count' ]++;
				$byIp[ $ip ][ 'last_seen_ts' ] = \max( $byIp[ $ip ][ 'last_seen_ts' ], $ts );
			}
		}
		foreach ( $requestLogs as $log ) {
			$ip = (string)( $log[ 'ip' ] ?? '' );
			if ( $ip !== '' ) {
				$ts = (int)( $log[ 'created_at_ts' ] ?? 0 );
				$byIp[ $ip ] = $byIp[ $ip ] ?? $this->newIpCardSeed( $ip );
				$byIp[ $ip ][ 'requests_count' ]++;
				$byIp[ $ip ][ 'has_offense' ] = $byIp[ $ip ][ 'has_offense' ] || !empty( $log[ 'offense' ] );
				$byIp[ $ip ][ 'last_seen_ts' ] = \max( $byIp[ $ip ][ 'last_seen_ts' ], $ts );
			}
		}

		foreach ( $byIp as &$card ) {
			$statuses = [];
			if ( $card[ 'sessions_count' ] > 0 ) {
				$statuses[] = 'good';
			}
			if ( $card[ 'requests_count' ] > 0 ) {
				$statuses[] = 'warning';
			}
			if ( $card[ 'has_offense' ] ) {
				$statuses[] = 'critical';
			}
			$card[ 'status' ] = StatusPriority::highest( $statuses );

			if ( $card[ 'last_seen_ts' ] > 0 ) {
				$card[ 'last_seen_at' ] = Services::WpGeneral()->getTimeStringForDisplay( $card[ 'last_seen_ts' ] );
				$card[ 'last_seen_ago' ] = $this->getTimeAgo( $card[ 'last_seen_ts' ] );
			}
			else {
				$card[ 'last_seen_at' ] = '';
				$card[ 'last_seen_ago' ] = '';
			}
			unset( $card[ 'has_offense' ] );
		}
		unset( $card );

		\uasort( $byIp, static fn( array $a, array $b ) :int => $b[ 'last_seen_ts' ] <=> $a[ 'last_seen_ts' ] );
		return \array_values( $byIp );
	}

	private function newIpCardSeed( string $ip ) :array {
		return [
			'ip'               => $ip,
			'href'             => self::con()->plugin_urls->ipAnalysis( $ip ),
			'investigate_href' => self::con()->plugin_urls->investigateByIp( $ip ),
			'last_seen_ts'     => 0,
			'last_seen_at'     => '',
			'last_seen_ago'    => '',
			'sessions_count'   => 0,
			'activity_count'   => 0,
			'requests_count'   => 0,
			'status'           => 'info',
			'has_offense'      => false,
		];
	}

	private function getTimeAgo( int $timestamp ) :string {
		if ( $timestamp <= 0 ) {
			return '';
		}
		return Services::Request()
					   ->carbon( true )
					   ->setTimestamp( $timestamp )
					   ->diffForHumans();
	}
}
