<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LocateTemplateDirs {

	use PluginControllerConsumer;

	/**
	 * @return string[]
	 */
	public function run() :array {
		$dirs = \array_filter(
			$this->getCustomTemplateDirs(),
			function ( $dir ) {
				return Services::WpFs()->isDir( $dir );
			}
		);
		$dirs[] = path_join( self::con()->getPath_Templates(), 'twig' );
		return $dirs;
	}

	/**
	 * @return string[]
	 */
	protected function getCustomTemplateDirs() :array {
		$dir = self::con()->cfg->paths[ 'custom_templates' ];
		$dirs = apply_filters( 'shield/custom_template_dirs',
			\array_unique( [
				path_join( get_stylesheet_directory(), $dir ),
				path_join( get_template_directory(), $dir ),
			] )
		);
		return self::con()->isPremiumActive() ? $dirs : [];
	}
}
