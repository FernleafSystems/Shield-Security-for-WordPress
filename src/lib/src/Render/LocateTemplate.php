<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LocateTemplate {

	use PluginControllerConsumer;

	/**
	 * @var string
	 */
	private $sTemplatePart;

	/**
	 * @return string|null
	 */
	public function run() {
		$sTemplatePath = null;
		$oFS = Services::WpFs();
		foreach ( $this->getPossibleDirs() as $sDir ) {
			$sFullPath = path_join( $sDir, $this->getTemplatePart() );
			if ( $oFS->isFile( $sFullPath ) ) {
				$sTemplatePath = $sFullPath;
				break;
			}
		}
		return empty( $sTemplatePath ) ? $this->getTemplatePart() : $sTemplatePath;
	}

	/**
	 * @return string[]
	 */
	protected function getPossibleDirs() {
		$sDir = $this->getCon()->getPluginSpec_Path( 'custom_templates' );
		$aDirs = array_unique( [
			path_join( get_stylesheet_directory(), $sDir ),
			path_join( get_template_directory(), $sDir ),
		] );
		return $this->getCon()->isPremiumActive() ? $aDirs : [];
	}

	/**
	 * @return string
	 */
	public function getTemplatePart() {
		return ltrim(
			Services::Data()->addExtensionToFilePath( $this->sTemplatePart, 'twig' ),
			'/'
		);
	}

	/**
	 * @param string $sTemplatePart
	 * @return $this
	 */
	public function setTemplatePart( $sTemplatePart ) {
		$this->sTemplatePart = $sTemplatePart;
		return $this;
	}
}
