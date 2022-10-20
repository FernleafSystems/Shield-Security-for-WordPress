<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditMessageBuilder;
use FernleafSystems\Wordpress\Services\Services;

class Activity extends Base {

	const SLUG = 'ipanalyse_activity_log';
	const TEMPLATE = '/wpadmin_pages/insights/ips/ip_analyse/ip_audittrail.twig';

	protected function getRenderData() :array {
		$WP = Services::WpGeneral();
		$logLoader = ( new LoadLogs() )
			->setMod( $this->getCon()->getModule_AuditTrail() )
			->setIP( $this->action_data[ 'ip' ] );
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

		return [
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
		];
	}
}