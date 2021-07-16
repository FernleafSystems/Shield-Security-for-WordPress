<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Tools;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

class GenerateGoogleAuthQrCode extends Common\BaseShieldNetApi {

	const API_ACTION = 'tools/google_auth_qr';

	public function getCode( string $secret, string $issuer, string $label, string $format = 'png' ) :string {
		$this->shield_net_params_required = false;
		$this->params_query = [
			'secret' => $secret,
			'issuer' => $issuer,
			'label'  => $label,
			'format' => $format,
		];
		$raw = $this->sendReq();

		$qrCode = '';
		if ( is_array( $raw ) && empty( $raw[ 'error' ] ) ) {
			$qrCode = $raw[ 'data' ][ 'code' ];
		}
		return $qrCode;
	}
}