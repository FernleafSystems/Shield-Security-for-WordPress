<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

class SetBulk extends Base {

	/**
	 * @inheritDoc
	 */
	protected function process() :array {
		$con = $this->getCon();
		$req = $this->getRequestVO();

		$filterKeys = [];
		foreach ( $req->options as $option ) {
			$def = $this->getOptionData( $option[ 'key' ] );
			if ( !empty( $def ) ) {
				$filterKeys[] = $option[ 'key' ];
				$opts = $con->modules[ $def[ 'module' ] ]->getOptions();
				if ( is_null( $option[ 'value' ] ) ) {
					$opts->resetOptToDefault( $option[ 'key' ] );
				}
				else {
					$opts->setOpt( $option[ 'key' ], $option[ 'value' ] );
				}
			}
		}
		$req->filter_keys = $filterKeys;
		return $this->getAllOptions();
	}
}