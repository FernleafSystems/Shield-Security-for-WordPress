<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits;

use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $request_path
 */
trait RequestPath {

	protected function getRequestPath() :string {
		$value = $this->request_path;
		if ( empty( $value ) ) {
			$value = Services::Request()->getPath();
		}
		if ( empty( $value ) ) {
			$value = '/';
		}
		return $value;
	}
}