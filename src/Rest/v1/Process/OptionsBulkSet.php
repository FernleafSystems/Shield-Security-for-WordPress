<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

class OptionsBulkSet extends OptionsBase {

	protected function process() :array {
		foreach ( $this->getWpRestRequest()->get_param( 'options' ) as $opt ) {
			$this->setOptFromRequest( $opt[ 'key' ], $opt[ 'value' ] );
		}
		return $this->getAllOptions();
	}
}