<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditMessageBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\GeoIp\Lookup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\DeleteIp;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\LookupIpOnList;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Strings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class BuildDisplay {

	use IpAddressConsumer;
	use ModConsumer;

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function run() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$ip = $this->getIP();
		if ( !Services::IP()->isValidIp( $ip ) ) {
			throw new \Exception( "A valid IP address wasn't provided." );
		}

		return $mod->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_analyse/ip_info.twig',
			[
				'strings' => [
					'title'        => sprintf( __( 'Info For IP Address %s', 'wp-simple-firewall' ), $ip ),
					'nav_signals'  => __( 'Bot Signals', 'wp-simple-firewall' ),
					'nav_general'  => __( 'General Info', 'wp-simple-firewall' ),
					'nav_sessions' => __( 'User Sessions', 'wp-simple-firewall' ),
					'nav_audit'    => __( 'Audit Trail', 'wp-simple-firewall' ),
					'nav_traffic'  => __( 'Recent Traffic', 'wp-simple-firewall' ),
				],
				'vars'    => [
					'ip' => $ip,
				],
				'content' => [
					'general'     => $this->renderForGeneral(),
					'signals'     => $this->renderForBotSignals(),
					'sessions'    => $this->renderForSessions(),
					'audit_trail' => $this->renderForAuditTrail(),
					'traffic'     => $this->renderForTraffic(),
				],
			],
			true
		);
	}

	private function renderForGeneral() :string {
		$con = $this->getCon();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$ip = $this->getIP();

		$blockIP = ( new LookupIpOnList() )
			->setDbHandler( $mod->getDbHandler_IPs() )
			->setListTypeBlock()
			->setIP( $ip )
			->lookup( true );

		$bypassIP = ( new LookupIpOnList() )
			->setDbHandler( $mod->getDbHandler_IPs() )
			->setListTypeBypass()
			->setIP( $ip )
			->lookup( true );

		$geo = ( new Lookup() )
			->setDbHandler( $con->getModule_Plugin()->getDbHandler_GeoIp() )
			->setIP( $ip )
			->lookupIp();
		$validGeo = $geo instanceof Databases\GeoIp\EntryVO;

		$sRDNS = gethostbyaddr( $ip );

		try {
			list( $ipKey, $ipName ) = ( new IpID( $ip ) )
				->setIgnoreUserAgent( true )
				->run();
			// We do a "repair" and unblock previously blocked search providers:
			if ( $blockIP instanceof Databases\IPs\EntryVO
				 && in_array( $ipKey, Services::ServiceProviders()->getSearchProviders() ) ) {
				( new DeleteIp() )
					->setMod( $mod )
					->setIP( $ip )
					->fromBlacklist();
				unset( $blockIP );
			}
		}
		catch ( \Exception $e ) {
			$ipKey = IpID::UNKNOWN;
			$ipName = 'Unknown';
		}

		if ( $ipKey === IpID::UNKNOWN ) {
			$ipEntry = ( new LookupIpOnList() )
				->setDbHandler( $mod->getDbHandler_IPs() )
				->setIP( $ip )
				->setListTypeBypass()
				->lookup();
			if ( $ipEntry instanceof Databases\IPs\EntryVO ) {
				$ipName = $ipEntry->label;
			}
		}

		$botScore = ( new CalculateVisitorBotScores() )
			->setMod( $mod )
			->setIP( $ip )
			->probability();
		$isBot = $mod->getBotSignalsController()->isBot( $ip, false );

		return $this->getMod()->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_analyse/ip_general.twig',
			[
				'strings' => [
					'title_general' => __( 'Identifying Info', 'wp-simple-firewall' ),
					'title_status'  => __( 'IP Status', 'wp-simple-firewall' ),

					'block_ip'      => __( 'Block IP', 'wp-simple-firewall' ),
					'unblock_ip'    => __( 'Unblock IP', 'wp-simple-firewall' ),
					'bypass_ip'     => __( 'Add IP Bypass', 'wp-simple-firewall' ),
					'unbypass_ip'   => __( 'Remove IP Bypass', 'wp-simple-firewall' ),
					'delete_notbot' => __( 'Reset For This IP', 'wp-simple-firewall' ),

					'status' => [
						'is_you'       => __( 'Is It You?', 'wp-simple-firewall' ),
						'offenses'     => __( 'Number of offenses', 'wp-simple-firewall' ),
						'is_blocked'   => __( 'Is Blocked', 'wp-simple-firewall' ),
						'is_bypass'    => __( 'Is Bypass IP', 'wp-simple-firewall' ),
						'notbot_score' => __( 'NotBot Score', 'wp-simple-firewall' ),
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
						'is_you'       => Services::IP()->checkIp( $ip, Services::IP()->getRequestIp() ),
						'offenses'     => $blockIP instanceof Databases\IPs\EntryVO ? $blockIP->transgressions : 0,
						'is_blocked'   => $blockIP instanceof Databases\IPs\EntryVO ? $blockIP->blocked_at > 0 : false,
						'is_bypass'    => $bypassIP instanceof Databases\IPs\EntryVO,
						'notbot_score' => $botScore,
						'is_bot'       => $isBot,
					],
					'identity' => [
						'who_is_it'    => $ipName,
						'rdns'         => $sRDNS === $ip ? __( 'Unavailable', 'wp-simple-firewall' ) : $sRDNS,
						'country_name' => $validGeo ? $geo->getCountryName() : __( 'Unknown', 'wp-simple-firewall' ),
						'timezone'     => $validGeo ? $geo->getTimezone() : __( 'Unknown', 'wp-simple-firewall' ),
						'coordinates'  => $validGeo ? sprintf( '%s: %s; %s: %s;',
							__( 'Latitude', 'wp-simple-firewall' ), $geo->getLatitude(),
							__( 'Longitude', 'wp-simple-firewall' ), $geo->getLongitude() )
							: 'Unknown'
					],
					'extras'   => [
						'ip_whois' => sprintf( 'https://whois.domaintools.com/%s', $ip ),
					],
				],
				'flags'   => [
					'has_geo' => $validGeo,
				],
			],
			true
		);
	}

	private function renderForSessions() :string {
		/** @var Databases\Session\Select $sel */
		$sel = $this->getCon()
					->getModule_Sessions()
					->getDbHandler_Sessions()
					->getQuerySelector();
		/** @var Databases\Session\EntryVO[] $sessions */
		$sessions = $sel->filterByIp( $this->getIP() )
						->query();

		foreach ( $sessions as $key => $session ) {
			$asArray = $session->getRawData();
			$asArray[ 'logged_in_at' ] = $this->formatTimestampField( (int)$session->logged_in_at );
			$asArray[ 'last_activity_at' ] = $this->formatTimestampField( (int)$session->last_activity_at );
			$asArray[ 'is_sec_admin' ] = $session->secadmin_at > 0;
			$sessions[ $key ] = $asArray;
		}

		return $this->getMod()->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_analyse/ip_sessions.twig',
			[
				'strings' => [
					'title'            => __( 'User Sessions', 'wp-simple-firewall' ),
					'no_sessions'      => __( 'No sessions at this IP', 'wp-simple-firewall' ),
					'username'         => __( 'Username', 'wp-simple-firewall' ),
					'sec_admin'        => __( 'Security Admin', 'wp-simple-firewall' ),
					'logged_in_at'     => __( 'Logged-In At', 'wp-simple-firewall' ),
					'last_activity_at' => __( 'Last Seen At', 'wp-simple-firewall' ),
				],
				'vars'    => [
					'sessions'       => $sessions,
					'total_sessions' => count( $sessions ),
				],
			],
			true
		);
	}

	private function renderForTraffic() :string {
		/** @var Databases\Traffic\Select $sel */
		$sel = $this->getCon()
					->getModule_Traffic()
					->getDbHandler_Traffic()
					->getQuerySelector();
		/** @var Databases\Traffic\EntryVO[] $requests */
		$requests = $sel->filterByIp( inet_pton( $this->getIP() ) )
						->query();

		foreach ( $requests as $key => $request ) {
			$asArray = $request->getRawData();
			$asArray[ 'created_at' ] = $this->formatTimestampField( (int)$request->created_at );
			if ( strpos( $request->path, '?' ) === false ) {
				$request->path .= '?';
			}
			list( $asArray[ 'path' ], $asArray[ 'query' ] ) = array_map( 'esc_js', explode( '?', $request->path, 2 ) );
			$asArray[ 'trans' ] = (bool)$asArray[ 'trans' ];
			$requests[ $key ] = $asArray;
		}

		return $this->getMod()->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_analyse/ip_traffic.twig',
			[
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
			],
			true
		);
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
			$signals = [];
		}

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

		return $this->getMod()->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_analyse/ip_botsignals.twig',
			[
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
			],
			true
		);
	}

	private function renderForAuditTrail() :string {
		$con = $this->getCon();
		/** @var Databases\AuditTrail\Select $sel */
		$sel = $con->getModule_AuditTrail()
				   ->getDbHandler_AuditTrail()
				   ->getQuerySelector();
		/** @var Databases\AuditTrail\EntryVO[] $logs */
		$logs = $sel->filterByIp( $this->getIP() )
					->query();

		foreach ( $logs as $key => $entry ) {
			$asArray = $entry->getRawData();

			$module = $con->getModule( $entry->context );
			if ( empty( $module ) ) {
				$module = $con->getModule_AuditTrail();
			}
			$strings = $module->getStrings();

			$asArray[ 'event' ] = AuditMessageBuilder::Build( $entry, $strings->getAuditMessage( $entry->event ) );
			$asArray[ 'created_at' ] = $this->formatTimestampField( (int)$entry->created_at );

			$logs[ $key ] = $asArray;
		}

		return $this->getMod()->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_analyse/ip_audittrail.twig',
			[
				'strings' => [
					'title'      => __( 'Audit Log Entries', 'wp-simple-firewall' ),
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
			],
			true
		);
	}

	/**
	 * copied from Table Builder
	 * @param int $nTimestamp
	 * @return string
	 */
	protected function formatTimestampField( int $nTimestamp ) {
		return Services::Request()
					   ->carbon()
					   ->setTimestamp( $nTimestamp )
					   ->diffForHumans()
			   .'<br/><span class="timestamp-small">'
			   .Services::WpGeneral()->getTimeStringForDisplay( $nTimestamp ).'</span>';
	}
}