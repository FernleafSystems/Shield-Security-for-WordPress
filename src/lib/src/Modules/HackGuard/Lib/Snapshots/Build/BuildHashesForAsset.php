<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets;

class BuildHashesForAsset {

	/**
	 * @var string
	 */
	private $hashAlgo = 'md5';

	/**
	 * All file keys are their normalised file paths, with the ABSPATH stripped from it.
	 * @param Assets\WpPluginVo|Assets\WpThemeVo $asset
	 * @return string[]
	 */
	public function build( $asset ) :array {
		return ( new BuildHashesFromDir() )
			->setHashAlgo( $this->getHashAlgo() )
			->setDepth( 0 )
			->setFileExts( [] )
			->build( $asset->getInstallDir() );
	}

	public function getHashAlgo() :string {
		return empty( $this->hashAlgo ) ? 'md5' : $this->hashAlgo;
	}

	/**
	 * @return static
	 */
	public function setHashAlgo( string $hashAlgo ) :self {
		$this->hashAlgo = $hashAlgo;
		return $this;
	}
}