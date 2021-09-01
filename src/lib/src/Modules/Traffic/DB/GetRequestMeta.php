<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class GetRequestMeta {

	use ModConsumer;

	public function retrieve( string $reqID ) :string {

		$metaDefs = [
			'uid'        => [
				'name' => __( 'User ID', 'wp-simple-firewall' ),
			],
			'ts'         => [
				'name' => __( 'Timestamp', 'wp-simple-firewall' ),
			],
			'req_method' => [
				'name'      => __( 'Method', 'wp-simple-firewall' ),
				'formatter' => function ( $metaDatum ) {
					return strtoupper( $metaDatum );
				}
			],
			'req_path'   => [
				'name' => __( 'Path', 'wp-simple-firewall' ),
			],
			'req_ua'     => [
				'name' => __( 'User Agent', 'wp-simple-firewall' ),
			],
		];


		$lines = [];
		foreach ( $this->selectRaw( sanitize_key( $reqID ) ) as $meta ) {
			$metaKey = $meta[ 'meta_key' ];
			$metaValue = $meta[ 'meta_value' ];

			if ( !empty( $metaDefs[ $metaKey ] ) ) {
				$lines[] = sprintf(
					'<li><strong>%s</strong>: <span>%s</span></li>',
					( $metaDef[ 'name' ] ?? $metaKey ),
					isset( $metaDef[ 'formatter' ] ) ? $metaDef[ 'formatter' ]( $metaValue ) : $metaValue
				);
			}
		}
		return empty( $lines ) ? 'No Meta' : sprintf( '<ul>%s</ul>', implode( '', $lines ) );
	}

	/**
	 * @return array[]
	 */
	private function selectRaw( string $reqID ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return Services::WpDb()->selectCustom(
			sprintf( 'SELECT req_log.req_id as rid,
							req_meta.meta_key, req_meta.meta_value
						FROM `%s` as req_log
						INNER JOIN `%s` as req_meta
							ON req_log.id = req_meta.log_ref 
						%s
						ORDER BY req_log.created_at DESC;',
				$mod->getDbH_ReqLogs()->getTableSchema()->table,
				$mod->getDbH_ReqMeta()->getTableSchema()->table,
				sprintf( 'WHERE `req_log`.req_id="%s"', $reqID )
			)
		);
	}
}