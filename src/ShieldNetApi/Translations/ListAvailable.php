<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Translations;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2;

class ListAvailable extends BaseShieldNetApiV2 {

	public const API_ACTION = 'translations/list';

	/**
	 * Fetch the list of available translations with their hashes.
	 *
	 * @return array|null Array of locale data with hashes, or null on error
	 *   Format: ['de_DE' => ['hash' => 'abc123...', 'hash_type' => 'sha1', 'size' => 123, 'updated_at' => 123], ...]
	 */
	public function retrieve() :?array {
		$this->request_method = 'get';
		$response = $this->sendReq();
		if ( !\is_array( $response ) || ( $response[ 'error_code' ] ?? 1 ) !== 0 ) {
			return null;
		}
		return $response[ 'locales' ] ?? null;
	}
}
