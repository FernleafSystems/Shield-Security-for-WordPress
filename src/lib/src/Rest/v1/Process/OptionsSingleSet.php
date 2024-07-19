<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

class OptionsSingleSet extends OptionsBase {

	protected function process() :array {
		$this->setOptFromRequest(
			$this->getWpRestRequest()->get_param( 'key' ),
			$this->getWpRestRequest()->get_param( 'value' )
		);
		return $this->getAllOptions();
	}
}