<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Option;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\Process;

abstract class Base extends Process {

	protected function getOptionData( string $mod, string $key ) :array {
		$opts = $this->getCon()->modules[ $mod ]->getOptions();
		$optionData = $opts->getOptDefinition( $key );
		$optionData[ 'value' ] = $opts->getOpt( $key );
		return $optionData;
	}
}