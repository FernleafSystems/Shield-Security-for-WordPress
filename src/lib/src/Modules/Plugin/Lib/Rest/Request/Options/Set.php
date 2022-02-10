<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

class Set extends Base {

	/**
	 * @inheritDoc
	 */
	protected function process() :array {
		$req = $this->getRequestVO();
		foreach ( $req->options as $option ) {
			$this->getCon()
				->modules[ $option[ 'mod' ] ]
				->getOptions()
				->setOpt( $option[ 'key' ], $option[ 'value' ] );
		}
		return $this->getAllOptions();
	}
}