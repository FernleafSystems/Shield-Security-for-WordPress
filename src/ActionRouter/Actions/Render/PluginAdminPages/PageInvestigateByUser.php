<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\{
	LoadLogs,
	LogRecord as ActivityLogRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\{
	LoadRequestLogs,
	LogRecord as RequestLogRecord
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
use FernleafSystems\Wordpress\Services\Services;

class PageInvestigateByUser extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_investigate_by_user';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_by_user.twig';

	private const LIMIT_SESSIONS = 50;
	private const LIMIT_ACTIVITY_LOGS = 75;
	private const LIMIT_REQUEST_LOGS = 75;

	protected function getRenderData() :array {
		$con = self::con();
		$request = Services::Request();
		$lookup = \trim( sanitize_text_field( (string)$request->query( 'user_lookup', '' ) ) );
		$subject = $this->resolveSubject( $lookup );
		$hasLookup = !empty( $lookup );
		$hasSubject = $subject instanceof \WP_User;
		$subjectNotFound = $hasLookup && !$hasSubject;

		$sessions = [];
		$activityLogs = [];
		$requestLogs = [];
		$relatedIps = [];
		$subjectVars = [];

		if ( $hasSubject ) {
			$sessions = $this->buildSessions( $subject );
			$activityLogs = $this->buildActivityLogs( $subject );
			$requestLogs = $this->buildRequestLogs( $subject );
			$relatedIps = $this->buildRelatedIps( $sessions, $activityLogs, $requestLogs );
			$subjectVars = [
				'id'      => $subject->ID,
				'login'   => $subject->user_login,
				'email'   => $subject->user_email,
				'display' => $subject->display_name,
			];
		}

		return [
			'flags'   => [
				'has_subject'       => $hasSubject,
				'has_lookup'        => $hasLookup,
				'subject_not_found' => $subjectNotFound,
			],
			'hrefs'   => [
				'back_to_investigate' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_OVERVIEW ),
				'by_user'             => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_ACTIVITY_BY_USER ),
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
				'summary_title'       => __( 'User Summary', 'wp-simple-firewall' ),
				'sessions_title'      => __( 'Recent Sessions', 'wp-simple-firewall' ),
				'sessions_empty'      => __( 'No sessions found for this user.', 'wp-simple-firewall' ),
				'sessions_user'       => __( 'User', 'wp-simple-firewall' ),
				'sessions_login_at'   => __( 'Logged-In At', 'wp-simple-firewall' ),
				'sessions_last_at'    => __( 'Last Activity', 'wp-simple-firewall' ),
				'sessions_ip'         => __( 'IP Address', 'wp-simple-firewall' ),
				'activity_title'      => __( 'Recent Activity Logs', 'wp-simple-firewall' ),
				'activity_empty'      => __( 'No activity logs found for this user.', 'wp-simple-firewall' ),
				'activity_event'      => __( 'Event', 'wp-simple-firewall' ),
				'activity_when'       => __( 'Logged At', 'wp-simple-firewall' ),
				'activity_ip'         => __( 'IP Address', 'wp-simple-firewall' ),
				'requests_title'      => __( 'Recent Request Logs', 'wp-simple-firewall' ),
				'requests_empty'      => __( 'No request logs found for this user.', 'wp-simple-firewall' ),
				'requests_path'       => __( 'Request', 'wp-simple-firewall' ),
				'requests_response'   => __( 'Response', 'wp-simple-firewall' ),
				'requests_when'       => __( 'Logged At', 'wp-simple-firewall' ),
				'requests_ip'         => __( 'IP Address', 'wp-simple-firewall' ),
				'related_ips_title'   => __( 'Related IP Addresses', 'wp-simple-firewall' ),
				'related_ips_empty'   => __( 'No related IP addresses were found.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'user_lookup'   => $lookup,
				'subject'       => $subjectVars,
				'sessions'      => $sessions,
				'activity_logs' => $activityLogs,
				'request_logs'  => $requestLogs,
				'related_ips'   => $relatedIps,
			],
		];
	}

	private function resolveSubject( string $lookup ) :?\WP_User {
		$user = null;
		if ( !empty( $lookup ) ) {
			$wpUsers = Services::WpUsers();
			if ( \ctype_digit( $lookup ) ) {
				$user = $wpUsers->getUserById( (int)$lookup );
			}
			elseif ( Services::Data()->validEmail( $lookup ) ) {
				$user = $wpUsers->getUserByEmail( $lookup );
			}
			else {
				$user = $wpUsers->getUserByUsername( $lookup );
			}
		}
		return $user instanceof \WP_User ? $user : null;
	}

	private function buildSessions( \WP_User $subject ) :array {
		$wp = Services::WpGeneral();

		$sessions = [];
		foreach ( ( new FindSessions() )->byUser( (int)$subject->ID ) as $userSessions ) {
			foreach ( $userSessions as $session ) {
				$loginAt = (int)( $session[ 'login' ] ?? 0 );
				$activityAt = (int)( $session[ 'shield' ][ 'last_activity_at' ] ?? $loginAt );
				$ip = (string)( $session[ 'shield' ][ 'ip' ] ?? '' );
				$sessions[] = [
					'user_login'           => (string)( $session[ 'user_login' ] ?? $subject->user_login ),
					'logged_in_at'         => $wp->getTimeStringForDisplay( $loginAt ),
					'logged_in_at_ago'     => $this->getTimeAgo( $loginAt ),
					'last_activity_at'     => $wp->getTimeStringForDisplay( $activityAt ),
					'last_activity_at_ago' => $this->getTimeAgo( $activityAt ),
					'is_sec_admin'         => (bool)( $session[ 'shield' ][ 'secadmin_at' ] ?? false ),
					'ip'                   => $ip,
					'ip_href'              => empty( $ip ) ? '' : self::con()->plugin_urls->ipAnalysis( $ip ),
					'last_activity_ts'     => $activityAt,
				];
			}
		}

		\uasort( $sessions, static function ( array $a, array $b ) :int {
			return $b[ 'last_activity_ts' ] <=> $a[ 'last_activity_ts' ];
		} );

		$sessions = \array_slice( $sessions, 0, self::LIMIT_SESSIONS );
		return \array_map( function ( array $session ) :array {
			unset( $session[ 'last_activity_ts' ] );
			return $session;
		}, $sessions );
	}

	private function buildActivityLogs( \WP_User $subject ) :array {
		$wp = Services::WpGeneral();
		$loader = ( new LoadLogs() )->forUserId( (int)$subject->ID );
		$loader->limit = self::LIMIT_ACTIVITY_LOGS;
		$loader->order_by = 'created_at';
		$loader->order_dir = 'DESC';

		return \array_values( \array_map(
			fn( ActivityLogRecord $record ) :array => [
				'event'          => ActivityLogMessageBuilder::Build( $record->event_slug, $record->meta_data ?? [], ' ' ),
				'created_at'     => $wp->getTimeStringForDisplay( $record->created_at ),
				'created_at_ago' => $this->getTimeAgo( $record->created_at ),
				'ip'             => (string)$record->ip,
				'ip_href'        => self::con()->plugin_urls->ipAnalysis( (string)$record->ip ),
			],
			$loader->run()
		) );
	}

	private function buildRequestLogs( \WP_User $subject ) :array {
		$wp = Services::WpGeneral();
		$loader = new LoadRequestLogs();
		$loader->wheres = [ \sprintf( '`req`.`uid`=%d', $subject->ID ) ];
		$loader->limit = self::LIMIT_REQUEST_LOGS;
		$loader->order_by = 'created_at';
		$loader->order_dir = 'DESC';

		return \array_values( \array_map(
			function ( RequestLogRecord $record ) use ( $wp ) :array {
				$ip = (string)$record->ip;
				$meta = \is_array( $record->meta ) ? $record->meta : [];
				return [
					'path'           => (string)$record->path,
					'query'          => (string)( $meta[ 'query' ] ?? '' ),
					'verb'           => \strtoupper( (string)$record->verb ),
					'code'           => (int)$record->code,
					'offense'        => (bool)$record->offense,
					'created_at'     => $wp->getTimeStringForDisplay( $record->created_at ),
					'created_at_ago' => $this->getTimeAgo( $record->created_at ),
					'ip'             => $ip,
					'ip_href'        => self::con()->plugin_urls->ipAnalysis( $ip ),
				];
			},
			$loader->select()
		) );
	}

	private function buildRelatedIps( array $sessions, array $activityLogs, array $requestLogs ) :array {
		$ips = [];

		foreach ( $sessions as $session ) {
			$ip = (string)( $session[ 'ip' ] ?? '' );
			if ( !empty( $ip ) ) {
				$ips[ $ip ] = true;
			}
		}
		foreach ( $activityLogs as $log ) {
			$ip = (string)( $log[ 'ip' ] ?? '' );
			if ( !empty( $ip ) ) {
				$ips[ $ip ] = true;
			}
		}
		foreach ( $requestLogs as $log ) {
			$ip = (string)( $log[ 'ip' ] ?? '' );
			if ( !empty( $ip ) ) {
				$ips[ $ip ] = true;
			}
		}

		return \array_map(
			fn( string $ip ) :array => [
				'ip'   => $ip,
				'href' => self::con()->plugin_urls->ipAnalysis( $ip ),
			],
			\array_keys( $ips )
		);
	}

	private function getTimeAgo( int $timestamp ) :string {
		return Services::Request()
					   ->carbon( true )
					   ->setTimestamp( $timestamp )
					   ->diffForHumans();
	}
}
