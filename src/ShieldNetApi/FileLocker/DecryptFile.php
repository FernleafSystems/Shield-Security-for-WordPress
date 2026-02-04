<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\FileLocker;

use FernleafSystems\Wordpress\Services\Utilities\Encrypt\OpenSslEncryptVo;

class DecryptFile extends \FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2 {

	public const API_ACTION = 'filelocker/decrypt';

	public function retrieve( OpenSslEncryptVo $openSslVO, int $publicKeyId ) :?string {
		$content = null;

		$this->request_method = 'post';
		$this->params_body = [
			'key_id'      => $publicKeyId,
			'sealed_data' => $openSslVO->sealed_data,
			'sealed_pass' => $openSslVO->sealed_password,
			'cipher'      => $openSslVO->cipher,
		];

		$raw = $this->sendReq();
		if ( \is_array( $raw ) && ( $raw[ 'error_code' ] ?? null ) === 0 && !empty( $raw[ 'opened_data' ] ) ) {
			$content = \base64_decode( $raw[ 'opened_data' ] );
		}
		return \is_string( $content ) ? $content : null;
	}
}