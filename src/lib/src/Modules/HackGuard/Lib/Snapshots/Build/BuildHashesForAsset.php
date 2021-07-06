<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};

/**
 * Class BuildHashesForAsset
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build
 */
class BuildHashesForAsset {

	/**
	 * @var string
	 */
	private $hashAlgo = 'md5';

	/**
	 * All file keys are their normalised file paths, with the ABSPATH stripped from it.
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return string[]
	 */
	public function build( $asset ) {
		return ( new BuildHashesFromDir() )
			->setHashAlgo( $this->getHashAlgo() )
			->setDepth( 0 )
			->setFileExts( [] )
			->build( $asset->getInstallDir() );
	}

	/**
	 * All file keys are their normalised file paths, with the ABSPATH stripped from it.
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return string[]
	 */
	public function buildNormalised( $asset ) :array {
		try {
			$hashes = ( new BuildHashesFromDir() )
				->setHashAlgo( $this->getHashAlgo() )
				->setDepth( 0 )
				->setFileExts( [] )
				->buildNormalised( $asset->getInstallDir() );
		}
		catch ( \Exception $e ) {
			$hashes = [];
		}
		return $hashes;
	}

	/**
	 * @return string
	 */
	public function getHashAlgo() {
		return empty( $this->hashAlgo ) ? 'md5' : $this->hashAlgo;
	}

	/**
	 * @param string $sHashAlgo
	 * @return static
	 */
	public function setHashAlgo( $sHashAlgo ) {
		$this->hashAlgo = $sHashAlgo;
		return $this;
	}
}