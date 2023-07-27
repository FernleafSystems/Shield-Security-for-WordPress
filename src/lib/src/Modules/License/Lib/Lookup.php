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

	public function lookup() :array {
		$raw = $this->sendReq();
		return ( \is_array( $raw ) && $raw[ 'error_code' ] === 0 ) ? $raw[ 'licenses' ] : [];
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