<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LocateTemplateDirs {

	use PluginControllerConsumer;

	/**
	 * @return string[]
	 */
	public function run() {
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
	protected function getCustomTemplateDirs() {
		$sDir = $this->getCon()->getPluginSpec_Path( 'custom_templates' );
		$aDirs = array_unique( [
			path_join( get_stylesheet_directory(), $sDir ),
			path_join( get_template_directory(), $sDir ),
		] );
		return $this->getCon()->isPremiumActive() ? $aDirs : [];
	}
}
