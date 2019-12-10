<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build\BuildHashesFromDir;
use FernleafSystems\Wordpress\Services\Core\VOs\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\WpThemeVo;

/**
 * Class BuildHashesForAsset
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Helpers
 */
class BuildHashesForAsset {

	/**
	 * @var string
	 */
	private $sHashAlgo = 'md5';

	/**
	 * All file keys are their normalised file paths, with the ABSPATH stripped from it.
	 * @param WpPluginVo|WpThemeVo $oAsset
	 * @return string[]
	 */
	public function build( $oAsset ) {
		return ( new BuildHashesFromDir() )
			->setHashAlgo( $this->getHashAlgo() )
			->setDepth( 0 )
			->setFileExts( [] )
			->build( $oAsset->getInstallDir() );
	}

	/**
	 * @return string
	 */
	public function getHashAlgo() {
		return empty( $this->sHashAlgo ) ? 'md5' : $this->sHashAlgo;
	}

	/**
	 * @param string $sHashAlgo
	 * @return static
	 */
	public function setHashAlgo( $sHashAlgo ) {
		$this->sHashAlgo = $sHashAlgo;
		return $this;
	}
}