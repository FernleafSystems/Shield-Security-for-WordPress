<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Option;

class Set extends Base {

	/**
	 * @inheritDoc
	 */
	protected function process() :array {
		$req = $this->getRequestVO();
		$this->getCon()
			->modules[ $req->option[ 'mod' ] ]
			->getOptions()
			->setOpt( $req->option[ 'key' ], $req->option[ 'value' ] );
		return [
			'option' => $this->getOptionData( $req->option[ 'mod' ], $req->option[ 'key' ] )
		];
	}
}