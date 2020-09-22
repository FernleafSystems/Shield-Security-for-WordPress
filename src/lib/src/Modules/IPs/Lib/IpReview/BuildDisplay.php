<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpReview;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
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

		$con = $this->getCon();

		// Traffic
//		/** @var Databases\Traffic\Select $sel */
//		$sel = $con->getModule_Traffic()
//				   ->getDbHandler_Traffic()
//				   ->getQuerySelector();
//		$ips = array_merge( $ips, $sel->getDistinctIps() );
//
//		// Audit Trail
//		/** @var Databases\AuditTrail\Select $sel */
//		$sel = $con->getModule_AuditTrail()
//				   ->getDbHandler_AuditTrail()
//				   ->getQuerySelector();
//		$ips = array_merge( $ips, $sel->getDistinctIps() );
//
//		// IP Addresses
//		/** @var Databases\IPs\Select $sel */
//		$sel = $con->getModule_IPs()
//				   ->getDbHandler_IPs()
//				   ->getQuerySelector();
//		$ips = array_merge( $ips, $sel->getDistinctForColumn( 'ip' ) );

		$renderData = Services::DataManipulation()->mergeArraysRecursive(
			[
				'strings' => [
					'title' => sprintf( __( 'Info For IP Address %s', 'wp-simple-firewall' ), $ip ),
				],
				'vars'    => [
					'ip' => $ip
				],
				'content' => [
					'users' => $this->renderForUsers()
				],
			],
			$this->renderForUsers()
		);
		return $mod->renderTemplate(
			'/wpadmin_pages/insights/ips/ip_review/ip_info.twig',
			$renderData,
			true
		);
	}

	private function renderForUsers() :string {
		// User Sessions
		/** @var Databases\Session\Select $sel */
		$sel = $this->getCon()
					->getModule_Sessions()
					->getDbHandler_Sessions()
					->getQuerySelector();
		/** @var Databases\Session\EntryVO[] $sessions */
		$sessions = $sel->filterByIp( $this->getIP() )
						->query();

		$c = Services::Request()->carbon( true );
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
					'sec_admin'   => __( 'Security Admin', 'wp-simple-firewall' ),
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