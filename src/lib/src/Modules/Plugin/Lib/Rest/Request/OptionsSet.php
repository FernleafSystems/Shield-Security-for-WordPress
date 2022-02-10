<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\Process;

class OptionsSet extends Process {

	/**
	 * @inheritDoc
	 */
	protected function process() :array {
		$options = [];
		foreach ( $this->getCon()->modules as $mod ) {
			$options[ $mod->getSlug() ] = $mod->getOptions()->getAllOptionsValues();
		}
		return $options;
	}
}