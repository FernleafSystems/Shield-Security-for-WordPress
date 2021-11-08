<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\OpenSslEncryptVo;

class DecryptFile extends BaseShieldNetApi {

	const API_ACTION = 'filelocker/decrypt';

	/**
	 * @param int $publicKeyId
	 */
	public function retrieve( OpenSslEncryptVo $openSslVO, $publicKeyId ) :string {
		$content = null;

		$this->request_method = 'post';
		$this->params_body = [
			'key_id'      => $publicKeyId,
			'sealed_data' => $openSslVO->sealed_data,
			'sealed_pass' => $openSslVO->sealed_password,
			'cipher'      => $openSslVO->cipher,
		];

		$raw = $this->sendReq();
		if ( is_array( $raw ) && !empty( $raw[ 'data' ] ) ) {
			$content = base64_decode( $raw[ 'data' ][ 'opened_data' ] );
		}
		return (string)$content;
	}
}