<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

class SetBulk extends Base {

	/**
	 * @inheritDoc
	 */
	protected function process() :array {
		$con = $this->getCon();
		$req = $this->getRequestVO();
		foreach ( $req->options as $option ) {
			$def = $this->getOptionData( $option[ 'key' ] );
			if ( !empty( $def ) ) {
				$con->modules[ $def[ 'module' ] ]
					->getOptions()
					->setOpt( $option[ 'key' ], $option[ 'value' ] );
			}
		}
		return $this->getAllOptions();
	}
}