<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\Common\BaseShieldSecurityApi;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldSecurityApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Encrypt\OpenSslEncryptVo;

class DecryptFile extends BaseShieldSecurityApi {

	use ModConsumer;
	const API_ACTION = 'filelocker/decrypt';

	/**
	 * @param OpenSslEncryptVo $oOpenSslVO
	 * @param int              $nPublicKeyId
	 * @return string|null
	 */
	public function retrieve( OpenSslEncryptVo $oOpenSslVO, $nPublicKeyId ) {
		$sContent = null;

		$this->params_body = array_merge( $this->getBaseParams(), [
			'key_id'      => $nPublicKeyId,
			'sealed_data' => $oOpenSslVO->sealed_data,
			'sealed_pass' => $oOpenSslVO->sealed_password,
		] );
		$this->request_method = 'post';
		$aRaw = $this->sendReq();
		if ( is_array( $aRaw ) && !empty( $aRaw[ 'decrypted' ] ) ) {
			$sContent = base64_decode( $aRaw[ 'decrypted' ][ 'opened_data' ] );
		}
		return $sContent;
	}
}