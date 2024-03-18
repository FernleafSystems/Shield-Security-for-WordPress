<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	IPs\IPRecords,
	ReqLogs\Ops as ReqLogsDB
};
use FernleafSystems\Wordpress\Services\Services;

class Traffic extends Base {

	public const SLUG = 'ipanalyse_traffic_log';
	public const TEMPLATE = '/wpadmin/components/ip_analyse/ip_traffic.twig';

	protected function getRenderData() :array {
		$WP = Services::WpGeneral();
		$logLimit = (int)\max( 1, apply_filters( 'shield/ipanalyse_traffic_log_query_limit', 100 ) );
		try {
			$ip = ( new IPRecords() )->loadIP( $this->action_data[ 'ip' ], false );
			/** @var ReqLogsDB\Select $selector */
			$selector = self::con()->db_con->req_logs->getQuerySelector();
			/** @var ReqLogsDB\Record[] $logs */
			$logs = $selector->filterByIP( $ip->id )
							 ->setLimit( $logLimit )
							 ->queryWithResult();
		}
		catch ( \Exception $e ) {
			$logs = [];
		}

		foreach ( $logs as $key => $req ) {
			$asArray = $req->getRawData();
			$asArray[ 'created_at' ] = $WP->getTimeStringForDisplay( $req->created_at );
			$asArray[ 'created_at_ago' ] = $this->getTimeAgo( $req->created_at );

			$asArray = \array_merge(
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
			$logs[ $key ] = $asArray;
		}

		return [
			'flags'   => [
				'log_display_limit_reached' => \count( $logs ) === $logLimit,
			],
			'strings' => [
				'title'         => __( 'Recent Requests', 'wp-simple-firewall' ),
				'no_requests'   => __( 'No requests logged for this IP address', 'wp-simple-firewall' ),
				'path'          => __( 'Path', 'wp-simple-firewall' ),
				'query'         => __( 'Query', 'wp-simple-firewall' ),
				'verb'          => __( 'Verb', 'wp-simple-firewall' ),
				'requested_at'  => __( 'Requested At', 'wp-simple-firewall' ),
				'response'      => __( 'Response', 'wp-simple-firewall' ),
				'http_code'     => __( 'Code', 'wp-simple-firewall' ),
				'offense'       => __( 'Offense', 'wp-simple-firewall' ),
				'display_limit' => sprintf( __( 'To view all logs from this IP address use the Traffic Log tool, as logs here are limited to %s entries.', 'wp-simple-firewall' ), $logLimit ),
			],
			'vars'    => [
				'requests'       => $logs,
				'display_limit'  => $logLimit,
				'total_requests' => \count( $logs ),
			],
		];
	}
}