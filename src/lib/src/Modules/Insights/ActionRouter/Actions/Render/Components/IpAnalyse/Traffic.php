<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Services\Services;

class Traffic extends Base {

	const SLUG = 'ipanalyse_traffic_log';
	const TEMPLATE = '/wpadmin_pages/insights/ips/ip_analyse/ip_traffic.twig';

	protected function getRenderData() :array {
		$WP = Services::WpGeneral();
		try {
			$ip = ( new IPRecords() )
				->setMod( $this->getCon()->getModule_Data() )
				->loadIP( $this->action_data[ 'ip' ], false );
			/** @var ReqLogs\Ops\Select $selector */
			$selector = $this->getCon()
							 ->getModule_Data()
							 ->getDbH_ReqLogs()
							 ->getQuerySelector();
			/** @var ReqLogs\Ops\Record[] $logs */
			$logs = $selector->filterByIP( $ip->id )->queryWithResult();
		}
		catch ( \Exception $e ) {
			$logs = [];
		}

		foreach ( $logs as $key => $req ) {
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
			$logs[ $key ] = $asArray;
		}

		return [
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
				'requests'       => $logs,
				'total_requests' => count( $logs ),
			],
		];
	}
}