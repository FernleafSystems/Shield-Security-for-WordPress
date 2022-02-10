<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\Process;

abstract class Base extends Process {

	protected function getAllOptions() :array{
		$options = [];
		foreach ( $this->getCon()->modules as $mod ) {
			$options[ $mod->getSlug() ] = $mod->getOptions()->getAllOptionsValues();
		}
		return $options;
	}
}