<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\Process;

abstract class Base extends Process {

	protected function getAllOptions() :array {
		$options = [];
		foreach ( $this->getCon()->modules as $mod ) {
			$opts = $mod->getOptions();
			$allOpts = $opts->getAllOptionsValues();
			$options[ $mod->getSlug() ] = array_intersect_key(
				$allOpts,
				array_flip( $opts->getOptionsForWpCli() )
			);
		}
		return $options;
	}

	/**
	 * Option key existence is checked in the Route.
	 */
	protected function getOptionData( string $key ) :array {
		$def = [];
		foreach ( $this->getCon()->modules as $module ) {
			$opts = $module->getOptions();
			$maybe = $opts->getOptDefinition( $key );
			if ( !empty( $maybe ) ) {
				$def = $maybe;
				$def[ 'module' ] = $module->getSlug();
				$def[ 'value' ] = $opts->getOpt( $key );
				break;
			}
		}
		return $def;
	}
}