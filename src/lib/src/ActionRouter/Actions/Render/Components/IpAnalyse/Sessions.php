<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\FindSessions;
use FernleafSystems\Wordpress\Services\Services;

class Sessions extends Base {

	public const SLUG = 'ipanalyse_sessions';
	public const TEMPLATE = '/wpadmin/components/ip_analyse/ip_sessions.twig';

	protected function getRenderData() :array {
		$WP = Services::WpGeneral();

		$allSessions = [];
		foreach ( ( new FindSessions() )->byIP( $this->action_data[ 'ip' ] ) as /* $userID => */ $sessions ) {
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

		\uasort( $allSessions, function ( $a, $b ) {
			if ( $a[ 'last_activity_at' ] == $b[ 'last_activity_at' ] ) {
				return 0;
			}
			return ( $a[ 'last_activity_at' ] < $b[ 'last_activity_at' ] ) ? 1 : -1;
		} );

		return [
			'strings' => [
				'title'            => __( 'User Sessions', 'wp-simple-firewall' ),
				'no_sessions'      => __( 'No sessions recorded for this IP address', 'wp-simple-firewall' ),
				'username'         => __( 'Username', 'wp-simple-firewall' ),
				'sec_admin'        => __( 'Security Admin', 'wp-simple-firewall' ),
				'logged_in_at'     => __( 'Logged-In At', 'wp-simple-firewall' ),
				'last_activity_at' => __( 'Last Seen At', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'sessions'       => $allSessions,
				'total_sessions' => \count( $allSessions ),
			],
		];
	}
}