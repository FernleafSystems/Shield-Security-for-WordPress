<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogMessageBuilder;
use FernleafSystems\Wordpress\Services\Services;

class Activity extends Base {

	public const SLUG = 'ipanalyse_activity_log';
	public const TEMPLATE = '/wpadmin/components/ip_analyse/ip_audittrail.twig';

	protected function getRenderData() :array {
		$logLoader = ( new LoadLogs() )->setIP( $this->action_data[ 'ip' ] );
		$logLoader->limit = 100;

		$logs = [];
		foreach ( $logLoader->run() as $key => $record ) {
			if ( self::con()->comps->events->eventExists( $record->event_slug ) ) {
				$asArray = $record->getRawData();

				$asArray[ 'event' ] = \implode( ' ', ActivityLogMessageBuilder::BuildFromLogRecord( $record ) );
				$asArray[ 'created_at' ] = Services::WpGeneral()->getTimeStringForDisplay( $record->created_at );
				$asArray[ 'created_at_ago' ] = $this->getTimeAgo( $record->created_at );

				$user = empty( $record->meta_data[ 'uid' ] ) ? null
					: Services::WpUsers()->getUserById( $record->meta_data[ 'uid' ] );
				$asArray[ 'user' ] = empty( $user ) ? '-' : $user->user_login;
				$logs[ $key ] = $asArray;
			}
		}

		return [
			'strings' => [
				'title'      => __( 'Recent Activity Logs', 'wp-simple-firewall' ),
				'no_logs'    => __( 'No activity logged for this IP address', 'wp-simple-firewall' ),
				'username'   => __( 'Username', 'wp-simple-firewall' ),
				'sec_admin'  => __( 'Security Admin', 'wp-simple-firewall' ),
				'event'      => __( 'Event', 'wp-simple-firewall' ),
				'created_at' => __( 'Logged At', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'logs'       => $logs,
				'total_logs' => \count( $logs ),
			],
		];
	}
}