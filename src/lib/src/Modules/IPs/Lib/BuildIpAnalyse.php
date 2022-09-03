<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP\Lookup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Strings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\GetIPInfo;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\GetIPReputation;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class BuildIpAnalyse {

	use IpAddressConsumer;
	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function run() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$ip = $this->getIP();
		if ( !Services::IP()->isValidIp( $ip ) ) {
			throw new \Exception( "A valid IP address wasn't provided." );
		}

		return $mod->renderTemplate( '/wpadmin_pages/insights/ips/ip_analyse/ip_info.twig', [
			'content' => [
				'general'     => $this->renderForGeneral(),
				'signals'     => $this->renderForBotSignals(),
				'sessions'    => $this->renderForSessions(),
				'audit_trail' => $this->renderForAuditTrail(),
				'traffic'     => $this->renderForTraffic(),
			],
			'strings' => [
				'title'        => sprintf( __( 'Info For IP Address %s', 'wp-simple-firewall' ), $ip ),
				'nav_signals'  => __( 'Bot Signals', 'wp-simple-firewall' ),
				'nav_general'  => __( 'General Info', 'wp-simple-firewall' ),
				'nav_sessions' => __( 'User Sessions', 'wp-simple-firewall' ),
				'nav_audit'    => __( 'Activity Log', 'wp-simple-firewall' ),
				'nav_traffic'  => __( 'Recent Traffic', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'ip' => $ip,
			],
		] );
	}

	private function renderForGeneral() :string {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$ip = $this->getIP();

		$geo = ( new Lookup() )
			->setCon( $con )
			->setIP( $ip )
			->lookup();

		try {
			list( $ipKey, $ipName ) = ( new IpID( $ip ) )
				->setIgnoreUserAgent()
				->setVerifyDNS( false )
				->run();
		}
		catch ( \Exception $e ) {
			$ipKey = IpID::UNKNOWN;
			$ipName = 'Unknown';
		}

		if ( $ipKey === IpID::UNKNOWN && !empty( $bypassIP ) ) {
			$ipName = $bypassIP->label ?? '';
		}

		$botScore = ( new CalculateVisitorBotScores() )
			->setMod( $mod )
			->setIP( $ip )
			->probability();
		$isBot = $mod->getBotSignalsController()->isBot( $ip, false );

		$shieldNetScore = ( new GetIPReputation() )
							  ->setMod( $con->getModule_Plugin() )
							  ->setIP( $ip )
							  ->retrieve()[ 'reputation_score' ] ?? '-';
		$info = ( new GetIPInfo() )
			->setMod( $con->getModule_Plugin() )
			->setIP( $ip )
			->retrieve();
		error_log( var_export( $info, true ) );
		$ruleStatus = ( new IpRuleStatus( $ip ) )->setMod( $this->getMod() );
		return $this->getMod()->renderTemplate( '/wpadmin_pages/insights/ips/ip_analyse/ip_general.twig', [
			'flags'   => [
				'has_geo' => !empty( $geo->getRawData() ),
			],
			'hrefs'   => [
				'snapi_reputation_details' => add_query_arg(
					[ 'ip' => $ip ], 'https://shsec.io/botornot'
				)
			],
			'strings' => [
				'title_general' => __( 'Identifying Info', 'wp-simple-firewall' ),
				'title_status'  => __( 'IP Status', 'wp-simple-firewall' ),

				'reset_offenses' => __( 'Reset', 'wp-simple-firewall' ),
				'block_ip'       => __( 'Block IP', 'wp-simple-firewall' ),
				'unblock_ip'     => __( 'Unblock IP', 'wp-simple-firewall' ),
				'bypass_ip'      => __( 'Add IP Bypass', 'wp-simple-firewall' ),
				'unbypass_ip'    => __( 'Remove IP Bypass', 'wp-simple-firewall' ),
				'delete_notbot'  => __( 'Reset For This IP', 'wp-simple-firewall' ),
				'see_details'    => __( 'See Details', 'wp-simple-firewall' ),

				'status' => [
					'is_you'              => __( 'Is It You?', 'wp-simple-firewall' ),
					'offenses'            => __( 'Number of offenses', 'wp-simple-firewall' ),
					'is_blocked'          => __( 'IP Blocked', 'wp-simple-firewall' ),
					'is_bypass'           => __( 'Bypass IP', 'wp-simple-firewall' ),
					'ip_reputation'       => __( 'IP Reputation Score', 'wp-simple-firewall' ),
					'snapi_ip_reputation' => __( 'ShieldNET IP Reputation Score', 'wp-simple-firewall' ),
					'block_type'          => $ruleStatus->isBlocked() ? Handler::GetTypeName( $ruleStatus->getBlockType() ) : ''
				],

				'yes' => __( 'Yes', 'wp-simple-firewall' ),
				'no'  => __( 'No', 'wp-simple-firewall' ),

				'identity' => [
					'who_is_it'   => __( 'Is this a known IP address?', 'wp-simple-firewall' ),
					'rdns'        => 'rDNS',
					'country'     => __( 'Country', 'wp-simple-firewall' ),
					'timezone'    => __( 'Timezone', 'wp-simple-firewall' ),
					'coordinates' => __( 'Coordinates', 'wp-simple-firewall' ),
				],

				'extras' => [
					'title'          => __( 'Extras', 'wp-simple-firewall' ),
					'ip_whois'       => __( 'IP Whois', 'wp-simple-firewall' ),
					'query_ip_whois' => __( 'Query IP Whois', 'wp-simple-firewall' ),
				],
			],
			'vars'    => [
				'ip'       => $ip,
				'status'   => [
					'is_you'                 => Services::IP()->checkIp( $ip, $con->this_req->ip ),
					'offenses'               => $ruleStatus->getOffenses(),
					'is_blocked'             => $ruleStatus->isBlocked(),
					'is_bypass'              => $ruleStatus->isBypass(),
					'is_crowdsec'            => $ruleStatus->isBlockedByCrowdsec(),
					'ip_reputation_score'    => $botScore,
					'snapi_reputation_score' => is_numeric( $shieldNetScore ) ? $shieldNetScore : 'Unavailable',
					'is_bot'                 => $isBot,
				],
				'identity' => [
					'who_is_it'    => $ipName,
					'rdns'         => empty( $info[ 'rdns' ][ 'hostname' ] ) ? __( 'Unavailable', 'wp-simple-firewall' ) : $info[ 'rdns' ][ 'hostname' ],
					'country_name' => $geo->countryName ?? __( 'Unknown', 'wp-simple-firewall' ),
					'timezone'     => $geo->timeZone ?? __( 'Unknown', 'wp-simple-firewall' ),
					'coordinates'  => $geo->latitude ? sprintf( '%s: %s; %s: %s;',
						__( 'Latitude', 'wp-simple-firewall' ), $geo->latitude,
						__( 'Longitude', 'wp-simple-firewall' ), $geo->longitude )
						: __( 'Unknown', 'wp-simple-firewall' )
				],
				'extras'   => [
					'ip_whois' => sprintf( 'https://whois.domaintools.com/%s', $ip ),
				],
			],
		] );
	}

	private function renderForSessions() :string {
		$WP = Services::WpGeneral();

		$finder = ( new FindSessions() )->setMod( $this->getMod() );
		$allSessions = [];
		foreach ( $finder->byIP( $this->getIP() ) as $userID => $sessions ) {
			foreach ( $sessions as $session ) {
				$loginAt = $session[ 'login' ];
				$activityAt = $session[ 'shield' ][ 'last_activity_at' ] ?? $loginAt;
				$session[ 'logged_in_at' ] = $WP->getTimeStringForDisplay( $loginAt );
				$session[ 'logged_in_at_ago' ] = $this->getTimeAgo( $loginAt );
				$session[ 'last_activity_at' ] = $WP->getTimeStringForDisplay( $activityAt );
				$session[ 'last_activity_at_ago' ] = $this->getTimeAgo( $activityAt );
				$session[ 'is_sec_admin' ] = (bool)( $session[ 'shield' ][ 'secadmin_at' ] ?? false );
				$allSessions[] = $session;
			}
		}

		uasort( $allSessions, function ( $a, $b ) {
			if ( $a[ 'last_activity_at' ] == $b[ 'last_activity_at' ] ) {
				return 0;
			}
			return ( $a[ 'last_activity_at' ] < $b[ 'last_activity_at' ] ) ? 1 : -1;
		} );

		return $this->getMod()->renderTemplate( '/wpadmin_pages/insights/ips/ip_analyse/ip_sessions.twig', [
			'strings' => [
				'title'            => __( 'User Sessions', 'wp-simple-firewall' ),
				'no_sessions'      => __( 'No sessions at this IP', 'wp-simple-firewall' ),
				'username'         => __( 'Username', 'wp-simple-firewall' ),
				'sec_admin'        => __( 'Security Admin', 'wp-simple-firewall' ),
				'logged_in_at'     => __( 'Logged-In At', 'wp-simple-firewall' ),
				'last_activity_at' => __( 'Last Seen At', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'sessions'       => $allSessions,
				'total_sessions' => count( $allSessions ),
			],
		] );
	}

	private function renderForTraffic() :string {
		$WP = Services::WpGeneral();
		try {
			$ip = ( new IPRecords() )
				->setMod( $this->getCon()->getModule_Data() )
				->loadIP( $this->getIP(), false );
			/** @var ReqLogs\Ops\Select $selector */
			$selector = $this->getCon()
							 ->getModule_Data()
							 ->getDbH_ReqLogs()
							 ->getQuerySelector();
			/** @var ReqLogs\Ops\Record[] $requests */
			$requests = $selector->filterByIP( $ip->id )->queryWithResult();
		}
		catch ( \Exception $e ) {
			$requests = [];
		}

		foreach ( $requests as $key => $req ) {
			$asArray = $req->getRawData();
			$asArray[ 'created_at' ] = $WP->getTimeStringForDisplay( $req->created_at );
			$asArray[ 'created_at_ago' ] = $this->getTimeAgo( $req->created_at );

			$asArray = array_merge(
				[
					'path'    => $req->path,
					'code'    => '-',
					'verb'    => '-',
					'query'   => '',
					'offense' => false,
				],
				$asArray,
				$req->meta
			);

			if ( empty( $asArray[ 'code' ] ) ) {
				$asArray[ 'code' ] = '-';
			}
			$asArray[ 'query' ] = esc_js( $asArray[ 'query' ] );
			$asArray[ 'trans' ] = (bool)$asArray[ 'offense' ];
			$requests[ $key ] = $asArray;
		}

		return $this->getMod()->renderTemplate( '/wpadmin_pages/insights/ips/ip_analyse/ip_traffic.twig', [
			'strings' => [
				'title'        => __( 'Visitor Requests', 'wp-simple-firewall' ),
				'no_requests'  => __( 'No requests logged for this IP', 'wp-simple-firewall' ),
				'path'         => __( 'Path', 'wp-simple-firewall' ),
				'query'        => __( 'Query', 'wp-simple-firewall' ),
				'verb'         => __( 'Verb', 'wp-simple-firewall' ),
				'requested_at' => __( 'Requested At', 'wp-simple-firewall' ),
				'response'     => __( 'Response', 'wp-simple-firewall' ),
				'http_code'    => __( 'Code', 'wp-simple-firewall' ),
				'offense'      => __( 'Offense', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'requests'       => $requests,
				'total_requests' => count( $requests ),
			],
		] );
	}

	private function renderForBotSignals() :string {
		/** @var Strings $strings */
		$strings = $this->getMod()->getStrings();
		$names = $strings->getBotSignalNames();

		$signals = [];
		$scores = ( new CalculateVisitorBotScores() )
			->setMod( $this->getMod() )
			->setIP( $this->getIP() )
			->scores();
		try {
			$record = ( new BotSignalsRecord() )
				->setMod( $this->getMod() )
				->setIP( $this->getIP() )
				->retrieve();
		}
		catch ( \Exception $e ) {
			$record = null;
		}

		if ( !empty( $record ) ) {
			foreach ( $scores as $scoreKey => $scoreValue ) {
				$column = $scoreKey.'_at';
				if ( $scoreValue !== 0 ) {
					if ( empty( $record ) || empty( $record->{$column} ) ) {
						if ( in_array( $scoreKey, [ 'known', 'created' ] ) ) {
							$signals[ $scoreKey ] = __( 'N/A', 'wp-simple-firewall' );
						}
						else {
							$signals[ $scoreKey ] = __( 'Never Recorded', 'wp-simple-firewall' );
						}
					}
					else {
						$signals[ $scoreKey ] = Services::Request()
														->carbon()
														->setTimestamp( $record->{$column} )->diffForHumans();
					}
				}
			}
		}

		return $this->getMod()->renderTemplate( '/wpadmin_pages/insights/ips/ip_analyse/ip_botsignals.twig', [
			'strings' => [
				'title'            => __( 'Bot Signals', 'wp-simple-firewall' ),
				'signal'           => __( 'Signal', 'wp-simple-firewall' ),
				'score'            => __( 'Score', 'wp-simple-firewall' ),
				'total_score'      => __( 'Total Reputation Score', 'wp-simple-firewall' ),
				'when'             => __( 'When', 'wp-simple-firewall' ),
				'bot_probability'  => __( 'Bad Bot Probability', 'wp-simple-firewall' ),
				'botsignal_delete' => __( 'Delete All Bot Signals', 'wp-simple-firewall' ),
				'signal_names'     => $names,
				'no_signals'       => __( 'There are no bot signals for this IP address.', 'wp-simple-firewall' ),
			],
			'ajax'    => [
				'has_signals' => !empty( $signals ),
			],
			'flags'   => [
				'has_signals' => !empty( $signals ),
			],
			'vars'    => [
				'signals'       => $signals,
				'total_signals' => count( $signals ),
				'scores'        => $scores,
				'total_score'   => array_sum( $scores ),
				'minimum'       => array_sum( $scores ),
				'probability'   => 100 - (int)max( 0, min( 100, array_sum( $scores ) ) )
			],
		] );
	}

	private function renderForAuditTrail() :string {
		$WP = Services::WpGeneral();
		$logLoader = ( new LoadLogs() )
			->setMod( $this->getCon()->getModule_AuditTrail() )
			->setIP( $this->getIP() );
		$logLoader->limit = 100;

		$logs = [];
		$srvEvents = $this->getCon()->loadEventsService();
		foreach ( $logLoader->run() as $key => $record ) {
			if ( $srvEvents->eventExists( $record->event_slug ) ) {
				$asArray = $record->getRawData();

				$asArray[ 'event' ] = implode( ' ', AuditMessageBuilder::BuildFromLogRecord( $record ) );
				$asArray[ 'created_at' ] = $WP->getTimeStringForDisplay( $record->created_at );
				$asArray[ 'created_at_ago' ] = $this->getTimeAgo( $record->created_at );

				$user = empty( $record->meta_data[ 'uid' ] ) ? null
					: Services::WpUsers()->getUserById( $record->meta_data[ 'uid' ] );
				$asArray[ 'user' ] = empty( $user ) ? '-' : $user->user_login;
				$logs[ $key ] = $asArray;
			}
		}

		return $this->getMod()->renderTemplate( '/wpadmin_pages/insights/ips/ip_analyse/ip_audittrail.twig', [
			'strings' => [
				'title'      => __( 'Recent Activity Log', 'wp-simple-firewall' ),
				'no_logs'    => __( 'No logs at this IP', 'wp-simple-firewall' ),
				'username'   => __( 'Username', 'wp-simple-firewall' ),
				'sec_admin'  => __( 'Security Admin', 'wp-simple-firewall' ),
				'event'      => __( 'Event', 'wp-simple-firewall' ),
				'created_at' => __( 'Logged At', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'logs'       => $logs,
				'total_logs' => count( $logs ),
			],
		] );
	}

	protected function getTimeAgo( int $ts ) :string {
		return Services::Request()
					   ->carbon()
					   ->setTimestamp( $ts )
					   ->diffForHumans();
	}
}