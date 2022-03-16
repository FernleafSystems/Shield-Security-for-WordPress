<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\{
	DB\ReqLogs\Ops,
	DB\ReqLogs\Ops\Handler,
	ModCon
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

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
			'type' => [
				'name'      => __( 'Request Type', 'wp-simple-firewall' ),
				'formatter' => function ( $metaDatum ) {
					return Handler::GetTypeName( $metaDatum );
				}
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
			'code' => [
				'name' => __( 'Response Code', 'wp-simple-firewall' ),
			],
			'ua'   => [
				'name' => __( 'User Agent', 'wp-simple-firewall' ),
			],
		];

		$lines = [];
		$meta = $this->getRawMeta();
		$meta[ 'rid' ] = $reqID;
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Ops\Select $selector */
		$selector = $mod->getDbH_ReqLogs()->getQuerySelector();
		$record = $selector->filterByReqID( $this->reqID )->first();
		return array_merge( $record->meta, $record->getRawData() );
	}
}