<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Tools;

class DetectNotBot extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi {

	public const API_ACTION = 'tools/detect/notbot';

	public function run( string $urlToFind ) :bool {
		$this->shield_net_params_required = true;
		$this->params_query = [
			'to_find' => $urlToFind,
		];
		$raw = $this->sendReq();
		return \is_array( $raw ) && empty( $raw[ 'error' ] ) && $raw[ 'data' ][ 'success' ];
	}
}