<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

/**
 * @property string $install_ids
 * @property string $url
 * @property string $nonce
 * @property array  $meta
 */
class Lookup extends \FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless\Base {

	public const API_ACTION = 'licenses';

	/**
	 * @throws Exceptions\FailedLicenseRequestHttpException
	 */
	public function lookup() :array {
		$raw = $this->sendReq();
		if ( !\is_array( $raw ) || ( $raw[ 'error_code' ] ?? 0 ) !== 0 ) {
			throw new Exceptions\FailedLicenseRequestHttpException( 'HTTP Request Failed' );
		}
		return $raw[ 'licenses' ] ?? [];
	}

	/**
	 * @return string[]
	 */
	protected function getRequestBodyParamKeys() :array {
		return [
			'url',
			'nonce',
			'install_ids',
			'meta',
		];
	}
}