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
	InvestigateUserLookupBuilder,
	ResolveUserLookup,
	Session\FindSessions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\{
	BaseInvestigationTable,
	ForActivityLog as InvestigationActivityTableBuilder,
	ForSessions as InvestigationSessionsTableBuilder,
	ForTraffic as InvestigationTrafficTableBuilder
};
use FernleafSystems\Wordpress\Services\Services;

class PageInvestigateByUser extends BasePluginAdminPage {

	use InvestigateRenderContracts;

	public const SLUG = 'plugin_admin_page_investigate_by_user';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_by_user.twig';
	private const CONTEXT_SESSIONS_LIMIT = 50;
	private const CONTEXT_ACTIVITY_LOGS_LIMIT = 75;
	private const CONTEXT_REQUEST_LOGS_LIMIT = 75;

	protected function getRenderData() :array {
		$con = self::con();
		$lookup = $this->getTextInputFromRequestOrActionData( 'user_lookup' );
		$userLookupBuilder = $this->getUserLookupBuilder();
		$subject = $this->resolveSubject( $lookup );
		$hasLookup = !empty( $lookup );
		$hasSubject = $subject instanceof \WP_User;
		$subjectNotFound = $hasLookup && !$hasSubject;
		$useStaticLookup = $userLookupBuilder->shouldUseStaticSelect();
		$lookupAjax = $useStaticLookup ? [] : $this->buildLookupAjaxContract( 'user', 1 );

		$tabs = [];
		$overviewRows = [];
		$railNavItems = [];
		$tables = [];
		$relatedIps = [];
		$subjectHeader = [];

		if ( $hasSubject ) {
			$sessions = $this->buildSessions( $subject );
			$activityLogs = $this->buildActivityLogs( $subject );
			$requestLogs = $this->buildRequestLogs( $subject );
			$relatedIps = $this->getRelatedIpCardsBuilder()->build( $sessions, $activityLogs, $requestLogs );
			$tabs = $this->buildUserTabsPayload();
			$overviewRows = ( new InvestigateOverviewRowsBuilder() )->forUser(
				$subject,
				$this->buildOverviewContext( $subject, $sessions, $relatedIps )
			);
			$railNavItems = $this->buildRailNavItemsFromTabs( $tabs );
			$tables = $this->buildTableContractsForUser( $subject->ID );
			$subjectHeader = $this->buildSubjectHeaderContract(
				(string)$subject->user_login,
				empty( $subject->user_email ) ? '' : (string)$subject->user_email
			);
		}

		return [
			'flags'   => [
				'has_subject'       => $hasSubject,
				'has_lookup'        => $hasLookup,
				'subject_not_found' => $subjectNotFound,
			],
			'hrefs'   => [
				'by_user' => $con->plugin_urls->investigateByUser(),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'person-lines-fill' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Investigate By User', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Select a user to review recent sessions, activity, and requests.', 'wp-simple-firewall' ),
				'lookup_label'        => __( 'User Lookup', 'wp-simple-firewall' ),
				'lookup_placeholder'  => __( 'Search for a user...', 'wp-simple-firewall' ),
				'lookup_submit'       => __( 'Load User Context', 'wp-simple-firewall' ),
				'lookup_helper'       => __( 'Type at least 1 character to search by username, display name, email, or user ID.', 'wp-simple-firewall' ),
				'change_subject'      => __( 'Change user', 'wp-simple-firewall' ),
				'not_found_title'     => __( 'No Matching User Found', 'wp-simple-firewall' ),
				'not_found_text'      => __( 'No WordPress user matched your lookup value. Try a different search term.', 'wp-simple-firewall' ),
				'related_ips_title'   => __( 'Related IP Addresses', 'wp-simple-firewall' ),
				'related_ips_empty'   => __( 'No related IP addresses were found.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'user_lookup'      => $lookup,
				'user_options'     => $useStaticLookup ? $userLookupBuilder->buildStaticOptions() : [],
				'user_lookup_label'=> $hasSubject ? $userLookupBuilder->formatLabel( $subject ) : $lookup,
				'lookup_route'     => $this->buildLookupRouteContract( PluginNavs::SUBNAV_ACTIVITY_BY_USER ),
				'lookup_behavior'  => $this->buildLookupBehaviorContract( true, true, true ),
				'lookup_ajax'      => $lookupAjax,
				'lookup_ajax_attr' => $this->buildLookupAjaxAttrValue( $lookupAjax ),
				'lookup_shortcuts' => $this->buildLookupShortcuts(),
				'offcanvas_history_mode' => '',
				'subject_header'   => $subjectHeader,
				'tabs'             => $tabs,
				'overview_rows'    => $overviewRows,
				'rail_nav_items'   => $railNavItems,
				'tables'           => $tables,
				'related_ips'      => $relatedIps,
			],
		];
	}

	protected function resolveSubject( string $lookup ) :?\WP_User {
		return ( new ResolveUserLookup() )->resolve( $lookup );
	}

	protected function buildOverviewContext( \WP_User $subject, array $sessions, array $relatedIps ) :array {
		$con = self::con();
		$meta = $con->user_metas->for( $subject );

		$roleText = __( 'Unknown', 'wp-simple-firewall' );
		$roles = \array_values( \array_filter(
			\array_map(
				static fn( string $role ) :string => \trim( \ucwords( \str_replace( '_', ' ', $role ) ) ),
				\is_array( $subject->roles ?? null ) ? $subject->roles : []
			)
		) );
		if ( !empty( $roles ) ) {
			$roleText = \implode( ', ', $roles );
		}

		$lastLoginIp = '';
		$ipRef = (int)( $meta->record->ip_ref ?? 0 );
		if ( $ipRef > 0 ) {
			$ipRecord = $con->db_con->ips->getQuerySelector()->byId( $ipRef );
			if ( \is_object( $ipRecord ) && \is_string( $ipRecord->ip ?? null ) ) {
				$lastLoginIp = $ipRecord->ip;
			}
		}
		if ( $lastLoginIp === '' ) {
			$session = \current( $sessions );
			if ( \is_array( $session ) && \is_string( $session[ 'ip' ] ?? null ) ) {
				$lastLoginIp = $session[ 'ip' ];
			}
		}

		$recentIps = \array_values( \array_unique( \array_filter(
			\array_map(
				static fn( array $card ) :string => (string)( $card[ 'ip' ] ?? '' ),
				$relatedIps
			)
		) ) );
		$recentIps = \array_slice( $recentIps, 0, 6 );

		$shieldStatus = ( (int)( $meta->record->hard_suspended_at ?? 0 ) > 0 )
			? __( 'Suspended', 'wp-simple-firewall' )
			: __( 'Active', 'wp-simple-firewall' );
		$wpProfileHref = '';
		if ( \function_exists( 'get_edit_user_link' ) ) {
			$wpProfileHref = (string)get_edit_user_link( $subject->ID );
		}
		if ( $wpProfileHref === '' && \function_exists( 'admin_url' ) ) {
			$wpProfileHref = (string)\admin_url( 'user-edit.php?user_id='.(int)$subject->ID );
		}

		return [
			'role'            => $roleText,
			'last_login_ip'   => $lastLoginIp === '' ? __( 'Unknown', 'wp-simple-firewall' ) : $lastLoginIp,
			'recent_ips'      => empty( $recentIps ) ? [] : $recentIps,
			'shield_status'   => $shieldStatus,
			'wp_profile_href' => $wpProfileHref,
		];
	}

	protected function buildUserTabsPayload() :array {
		$tabs = [
			'overview' => [
				'pane_id'   => 'tabInvestigateUserOverview',
				'nav_id'    => 'tab-navlink-user-overview',
				'label'     => __( 'Overview', 'wp-simple-firewall' ),
				'count'     => null,
				'is_active' => true,
			],
			'sessions' => [
				'pane_id'   => 'tabInvestigateUserSessions',
				'nav_id'    => 'tab-navlink-user-sessions',
				'label'     => __( 'Sessions', 'wp-simple-firewall' ),
				'count'     => null,
				'is_active' => false,
			],
			'activity' => [
				'pane_id'   => 'tabInvestigateUserActivity',
				'nav_id'    => 'tab-navlink-user-activity',
				'label'     => __( 'Activity', 'wp-simple-firewall' ),
				'count'     => null,
				'is_active' => false,
			],
			'requests' => [
				'pane_id'   => 'tabInvestigateUserRequests',
				'nav_id'    => 'tab-navlink-user-requests',
				'label'     => __( 'Requests', 'wp-simple-firewall' ),
				'count'     => null,
				'is_active' => false,
			],
			'ips'      => [
				'pane_id'   => 'tabInvestigateUserIps',
				'nav_id'    => 'tab-navlink-user-ips',
				'label'     => __( 'IP Addresses', 'wp-simple-firewall' ),
				'count'     => null,
				'is_active' => false,
			],
		];

		foreach ( $tabs as $key => $tab ) {
			$tabs[ $key ][ 'target' ] = '#'.$tab[ 'pane_id' ];
			$tabs[ $key ][ 'controls' ] = $tab[ 'pane_id' ];
		}

		return $tabs;
	}

	protected function buildTableContractsForUser( int $uid ) :array {
		$subjectType = InvestigationTableContract::SUBJECT_TYPE_USER;
		$tableAction = ActionData::Build( InvestigationTableAction::class );

		$specs = [
			'sessions' => [
				'title'      => __( 'Sessions', 'wp-simple-firewall' ),
				'status'     => 'good',
				'table_type' => InvestigationTableContract::TABLE_TYPE_SESSIONS,
				'builder'    => new InvestigationSessionsTableBuilder(),
			],
			'activity' => [
				'title'      => __( 'Activity', 'wp-simple-firewall' ),
				'status'     => 'warning',
				'table_type' => InvestigationTableContract::TABLE_TYPE_ACTIVITY,
				'builder'    => new InvestigationActivityTableBuilder(),
			],
			'requests' => [
				'title'      => __( 'Requests', 'wp-simple-firewall' ),
				'status'     => 'warning',
				'table_type' => InvestigationTableContract::TABLE_TYPE_TRAFFIC,
				'builder'    => new InvestigationTrafficTableBuilder(),
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
		BaseInvestigationTable $tableBuilder
	) :array {
		$table = $this->buildTableContainerContract(
			$title,
			$status,
			$tableType,
			$subjectType,
			(string)$uid,
			$tableBuilder
				->setSubject( $subjectType, $uid )
				->buildRaw(),
			$tableAction
		);

		$table[ 'is_flat' ] = true;
		$table[ 'show_header' ] = false;

		return $this->normalizeInvestigationTableContract( $table );
	}

	protected function buildSessions( \WP_User $subject ) :array {
		$sessions = [];
		foreach ( ( new FindSessions() )->byUser( (int)$subject->ID ) as $userSessions ) {
			foreach ( $userSessions as $session ) {
				$normalized = $this->normalizeUserSession( $session );
				if ( $normalized !== null ) {
					$sessions[] = $normalized;
				}
			}
		}

		\uasort( $sessions, static function ( array $a, array $b ) :int {
			return $b[ 'last_activity_ts' ] <=> $a[ 'last_activity_ts' ];
		} );

		$sessions = \array_slice( $sessions, 0, self::CONTEXT_SESSIONS_LIMIT );
		return \array_values( $sessions );
	}

	protected function buildActivityLogs( \WP_User $subject ) :array {
		$loader = ( new LoadLogs() )->forUserId( (int)$subject->ID );
		$loader->limit = self::CONTEXT_ACTIVITY_LOGS_LIMIT;
		$loader->order_by = 'created_at';
		$loader->order_dir = 'DESC';

		return \array_values( \array_map(
			fn( ActivityLogRecord $record ) :array => [
				'created_at_ts' => $record->created_at,
				'ip'            => $record->ip,
			],
			$loader->run()
		) );
	}

	protected function buildRequestLogs( \WP_User $subject ) :array {
		$loader = ( new LoadRequestLogs() )->forUserId( (int)$subject->ID );
		$loader->limit = self::CONTEXT_REQUEST_LOGS_LIMIT;
		$loader->order_by = 'created_at';
		$loader->order_dir = 'DESC';

		return \array_values( \array_map(
			static fn( RequestLogRecord $record ) :array => [
				'created_at_ts' => $record->created_at,
				'ip'            => $record->ip,
				'offense'       => (bool)$record->offense,
			],
			$loader->select()
		) );
	}

	/**
	 * @param array<string, mixed> $session
	 * @return array{ip:string, last_activity_ts:int}|null
	 */
	private function normalizeUserSession( array $session ) :?array {
		$loginAt = $session[ 'login' ] ?? null;
		$shield = $session[ 'shield' ] ?? null;
		if ( !\is_int( $loginAt ) || !\is_array( $shield ) ) {
			return null;
		}

		$ip = $shield[ 'ip' ] ?? null;
		$activityAt = $shield[ 'last_activity_at' ] ?? $loginAt;
		if ( !\is_string( $ip ) || !\is_int( $activityAt ) ) {
			return null;
		}

		return [
			'ip'               => $ip,
			'last_activity_ts' => $activityAt,
		];
	}

	protected function getRelatedIpCardsBuilder() :InvestigateByUserRelatedIpCardsBuilder {
		return new InvestigateByUserRelatedIpCardsBuilder();
	}

	protected function getUserLookupBuilder() :InvestigateUserLookupBuilder {
		return new InvestigateUserLookupBuilder();
	}

	/**
	 * @return list<array<string,string>>
	 */
	private function buildLookupShortcuts() :array {
		$currentUserId = (int)Services::WpUsers()->getCurrentWpUserId();
		if ( $currentUserId < 1 ) {
			return [];
		}

		return [
			$this->buildLookupShortcutContract(
				'self',
				self::con()->plugin_urls->investigateByUser( (string)$currentUserId ),
				__( 'Look up yourself', 'wp-simple-firewall' ),
				'navigate',
				'bi bi-person-fill'
			),
		];
	}
}
