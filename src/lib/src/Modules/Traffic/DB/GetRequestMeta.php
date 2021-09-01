<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class GetRequestMeta {

	use ModConsumer;

	private $reqID;

	public function retrieve( string $reqID ) :string {
		$this->reqID = sanitize_key( $reqID );

		$metaDefs = [
			'rid'  => [
				'name'      => __( 'Request ID', 'wp-simple-firewall' ),
				'formatter' => function ( $metaDatum ) {
					return sprintf( '<code>%s</code>', $metaDatum );
				},
			],
			'uid'  => [
				'name' => __( 'User ID', 'wp-simple-firewall' ),
			],
			'ts'   => [
				'name' => __( 'Timestamp', 'wp-simple-firewall' ),
			],
			'verb' => [
				'name'      => __( 'Method', 'wp-simple-firewall' ),
				'formatter' => function ( $metaDatum ) {
					return strtoupper( $metaDatum );
				}
			],
			'path' => [
				'name' => __( 'Path', 'wp-simple-firewall' ),
			],
			'ua'   => [
				'name' => __( 'User Agent', 'wp-simple-firewall' ),
			],
		];

		$lines = [];
		$meta = $this->getRawMeta();
		foreach ( $metaDefs as $metaKey => $metaDef ) {

			if ( !empty( $meta[ $metaKey ] ) ) {
				$lines[] = sprintf(
					'<li><strong>%s</strong>: <span>%s</span></li>',
					( $metaDef[ 'name' ] ?? $metaKey ),
					isset( $metaDef[ 'formatter' ] ) ? $metaDef[ 'formatter' ]( $meta[ $metaKey ] ) : $meta[ $metaKey ]
				);
			}
		}
		return empty( $lines ) ? 'No Meta' : sprintf( '<ul>%s</ul>', implode( '', $lines ) );
	}

	/**
	 * @return array[]
	 */
	private function getRawMeta() :array {
		$meta = [
			'rid' => $this->reqID,
		];
		foreach ( $this->selectRaw() as $rawMeta ) {
			$meta[ $rawMeta[ 'meta_key' ] ] = $rawMeta[ 'meta_value' ];
		}
		return $meta;
	}

	/**
	 * @return array[]
	 */
	private function selectRaw() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$results = Services::WpDb()->selectCustom(
			sprintf( 'SELECT req_log.req_id as rid,
							req_meta.meta_key, req_meta.meta_value
						FROM `%s` as req_log
						INNER JOIN `%s` as req_meta
							ON req_log.id = req_meta.log_ref 
						%s
						ORDER BY req_log.created_at DESC;',
				$mod->getDbH_ReqLogs()->getTableSchema()->table,
				$mod->getDbH_ReqMeta()->getTableSchema()->table,
				sprintf( 'WHERE `req_log`.req_id="%s"', $this->reqID )
			)
		);
		return is_array( $results ) ? $results : [];
	}
}