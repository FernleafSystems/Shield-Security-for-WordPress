<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

class GetSingle extends Base {

	/**
	 * @inheritDoc
	 */
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