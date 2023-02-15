<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request\Options;

class GetSingle extends Base {

	protected function process() :array {
		$req = $this->getRequestVO();

		$theOption = null;
		foreach ( $this->getAllOptions() as $option ) {
			if ( $option[ 'key' ] === $req->key ) {
				$theOption = $option;
				break;
			}
		}

		return [
			'options' => $theOption
		];
	}
}