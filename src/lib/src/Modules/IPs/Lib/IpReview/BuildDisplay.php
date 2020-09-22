<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpReview;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\GeoIp\Lookup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\LookupIpOnList;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildDisplay {

	use IpAddressConsumer;
	use ModConsumer;

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function run() :string {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $mod */
		$mod = $this->getMod();

		$ip = $this->getIP();
		if ( !Services::IP()->isValidIp( $ip ) ) {
			throw new \Exception( "A valid IP address was not provided." );
		}

		return $mod->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_review/ip_info.twig',
			[
				'strings' => [
					'title'        => sprintf( __( 'Info For IP Address %s', 'wp-simple-firewall' ), $ip ),
					'nav_general'  => __( 'General Info', 'wp-simple-firewall' ),
					'nav_sessions' => __( 'User Sessions', 'wp-simple-firewall' ),
					'nav_audit'    => __( 'Audit Trail', 'wp-simple-firewall' ),
					'nav_traffic'  => __( 'Recent Traffic', 'wp-simple-firewall' ),
				],
				'vars'    => [
					'ip' => $ip
				],
				'content' => [
					'general'     => $this->renderForGeneral(),
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
		$ip = $this->getIP();

		$dbh = $con->getModule_IPs()->getDbHandler_IPs();

		$oBlockIP = ( new LookupIpOnList() )
			->setDbHandler( $dbh )
			->setListTypeBlack()
			->setIP( $ip )
			->lookup( true );

		$oBypassIP = ( new LookupIpOnList() )
			->setDbHandler( $dbh )
			->setListTypeWhite()
			->setIP( $ip )
			->lookup( true );

		$geo = ( new Lookup() )
			->setDbHandler( $con->getModule_Plugin()->getDbHandler_GeoIp() )
			->setIP( $ip )
			->lookupIp();
		$validGeo = $geo instanceof Databases\GeoIp\EntryVO;

		$sRDNS = gethostbyaddr( $ip );

		return $this->getMod()->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_review/ip_general.twig',
			[
				'strings' => [
					'title_general' => __( 'Identifying Info', 'wp-simple-firewall' ),
					'title_status'  => __( 'IP Status', 'wp-simple-firewall' ),

					'status' => [
						'is_you'     => __( 'Is It You?', 'wp-simple-firewall' ),
						'offenses'   => __( 'Number of offenses', 'wp-simple-firewall' ),
						'is_blocked' => __( 'Is Blocked', 'wp-simple-firewall' ),
						'is_bypass'  => __( 'Is By-Pass IP', 'wp-simple-firewall' ),
					],

					'yes' => __( 'Yes', 'wp-simple-firewall' ),
					'no'  => __( 'No', 'wp-simple-firewall' ),

					'country'     => __( 'Country', 'wp-simple-firewall' ),
					'timezone'    => __( 'Timezone', 'wp-simple-firewall' ),
					'coordinates' => __( 'Coordinates', 'wp-simple-firewall' ),
					'rdns'        => 'rDNS',
				],
				'vars'    => [
					'ip'     => $ip,
					'status' => [
						'is_you'     => Services::IP()->checkIp( $ip, Services::IP()->getRequestIp() ),
						'offenses'   => $oBlockIP instanceof Databases\IPs\EntryVO ? $oBlockIP->transgressions : 0,
						'is_blocked' => $oBlockIP instanceof Databases\IPs\EntryVO ? $oBlockIP->blocked_at > 0 : false,
						'is_bypass'  => $oBypassIP instanceof Databases\IPs\EntryVO,
					],
					'geo'    => [
						'rdns'         => $sRDNS === $ip ? __( 'Unavailable', 'wp-simple-firewall' ) : $sRDNS,
						'country_name' => $validGeo ? $geo->getCountryName() : __( 'Unknown', 'wp-simple-firewall' ),
						'timezone'     => $validGeo ? $geo->getTimezone() : __( 'Unknown', 'wp-simple-firewall' ),
						'coordinates'  => $validGeo ? sprintf( '%s: %s; %s: %s;',
							__( 'Latitude', 'wp-simple-firewall' ), $geo->getLatitude(),
							__( 'Longitude', 'wp-simple-firewall' ), $geo->getLongitude() )
							: 'Unknown'
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
			$asArray = $session->getRawDataAsArray();
			$asArray[ 'logged_in_at' ] = $this->formatTimestampField( (int)$session->logged_in_at );
			$asArray[ 'last_activity_at' ] = $this->formatTimestampField( (int)$session->last_activity_at );
			$asArray[ 'is_sec_admin' ] = $session->secadmin_at > 0;
			$sessions[ $key ] = $asArray;
		}

		return $this->getMod()->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_review/ip_sessions.twig',
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
			$asArray = $request->getRawDataAsArray();
			$asArray[ 'created_at' ] = $this->formatTimestampField( (int)$request->created_at );
			list( $asArray[ 'path' ], $asArray[ 'query' ] ) = array_map( 'esc_js', explode( '?', $request->path, 2 ) );
			$asArray[ 'trans' ] = (bool)$asArray[ 'trans' ];
			$requests[ $key ] = $asArray;
		}

		return $this->getMod()->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_review/ip_traffic.twig',
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

	private function renderForAuditTrail() :string {
		$con = $this->getCon();
		/** @var Databases\AuditTrail\Select $sel */
		$sel = $con->getModule_AuditTrail()
				   ->getDbHandler_AuditTrail()
				   ->getQuerySelector();
		/** @var Databases\AuditTrail\EntryVO[] $logs */
		$logs = $sel->filterByIp( $this->getIP() )
					->query();

		foreach ( $logs as $key => $log ) {
			$asArray = $log->getRawDataAsArray();

			$module = $con->getModule( $log->context );
			if ( empty( $module ) ) {
				$module = $con->getModule_AuditTrail();
			}
			$oStrings = $module->getStrings();

			$asArray[ 'event' ] = stripslashes( sanitize_textarea_field(
				vsprintf(
					implode( "\n", $oStrings->getAuditMessage( $log->event ) ),
					$log->meta
				)
			) );
			$asArray[ 'created_at' ] = $this->formatTimestampField( (int)$log->created_at );

			$logs[ $key ] = $asArray;
		}

		return $this->getMod()->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_review/ip_audittrail.twig',
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