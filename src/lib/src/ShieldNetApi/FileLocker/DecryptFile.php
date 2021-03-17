<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApi;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\OpenSslEncryptVo;

class DecryptFile extends BaseShieldNetApi {

	const API_ACTION = 'filelocker/decrypt';

	/**
	 * @param OpenSslEncryptVo $oOpenSslVO
	 * @param int              $nPublicKeyId
	 * @return string|null
	 */
	public function retrieve( OpenSslEncryptVo $oOpenSslVO, $nPublicKeyId ) {
		$content = null;

		$this->request_method = 'post';
		$this->params_body = [
			'key_id'      => $nPublicKeyId,
			'sealed_data' => $oOpenSslVO->sealed_data,
			'sealed_pass' => $oOpenSslVO->sealed_password,
		];

		$raw = $this->sendReq();
		if ( is_array( $raw ) && !empty( $raw[ 'data' ] ) ) {
			$content = base64_decode( $raw[ 'data' ][ 'opened_data' ] );
		}
		return $content;
	}
}