<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

class SetSingle extends Base {

	/**
	 * @inheritDoc
	 */
	protected function process() :array {
		$req = $this->getRequestVO();
		$def = $this->getOptionData( $req->key );
		$opts = $this->getCon()->modules[ $def[ 'module' ] ]->getOptions();

		if ( is_null( $req->value ) ) {
			$opts->resetOptToDefault( $req->key );
		}
		else {
			$opts->setOpt( $req->key, $req->value );
		}

		if ( serialize( $req->value ) !== serialize( $opts->getOpt( $req->key ) ) ) {
			throw new \Exception( 'Failed to update option. Value may be of an incorrect type.' );
		}

		return [
			'option' => $this->getOptionData( $req->key )
		];
	}
}