<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Exceptions\OptionDoesNotExistException;

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
		// TODO: Bug where we only deal with Transferable options here, but not in the Route.
		if ( empty( $theOption ) ) {
			throw new OptionDoesNotExistException( sprintf( "Option with key %s doesn't exist", $req->key ) );
		}

		return [
			'option' => $theOption
		];
	}
}