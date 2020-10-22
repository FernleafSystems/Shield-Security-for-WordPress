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
		$aDirs = array_filter(
			$this->getCustomTemplateDirs(),
			function ( $sDir ) {
				return Services::WpFs()->isDir( $sDir );
			}
		);
		$aDirs[] = path_join( $this->getCon()->getPath_Templates(), 'twig' );
		return $aDirs;
	}

	/**
	 * @return string[]
	 */
	protected function getCustomTemplateDirs() :array {
		$dir = $this->getCon()->getPluginSpec_Path( 'custom_templates' );
		$dirs = array_unique( [
			path_join( get_stylesheet_directory(), $dir ),
			path_join( get_template_directory(), $dir ),
		] );
		return $this->getCon()->isPremiumActive() ? $dirs : [];
	}
}
