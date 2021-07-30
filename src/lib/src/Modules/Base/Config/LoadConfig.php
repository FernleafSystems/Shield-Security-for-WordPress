<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Config;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class LoadConfig {

	use ModConsumer;

	private $configSourceFile = '';

	private $isBuiltFromFile = false;

	public function getConfigSourceFile() :string {
		return empty( $this->configSourceFile ) ? $this->getPathCfg() : $this->configSourceFile;
	}

	public function isBuiltFromFile() :bool {
		return $this->isBuiltFromFile;
	}

	/**
	 * @param bool $forceRebuild
	 * @return array
	 * @throws \Exception
	 */
	public function run( bool $forceRebuild = false ) :array {
		try {
			if ( $forceRebuild ) {
				throw new \Exception( 'Force rebuild from file' );
			}
			$cfg = $this->fromWP();
			$this->isBuiltFromFile = false;
		}
		catch ( \Exception $e ) {
			$cfg = $this->fromFile();
			$this->isBuiltFromFile = true;
		}
		return $cfg;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function fromWP() :array {
		$FS = Services::WpFs();
		$cfg = Transient::Get( $this->storeKey() );

		if ( empty( $cfg ) || !is_array( $cfg ) || ( $FS->getModifiedTime( $this->getConfigSourceFile() ) > $cfg[ 'meta' ][ 'ts_mod' ] ) ) {
			throw new \Exception( 'WP store is expired or non-existent' );
		}
		return $cfg;
	}

	public function storeKey() :string {
		return 'shield_mod_config_'.$this->getMod()->getSlug();
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function fromFile() :array {
		$path = $this->getPathCfg();
		try {
			$raw = $this->loadRawFromFile( $path );
			$this->configSourceFile = $path;
		}
		catch ( \Exception $e ) {
			$path = $this->getCon()->paths->forModuleConfig( $this->getMod()->getSlug(), false );
			$raw = $this->loadRawFromFile( $path );
			$this->configSourceFile = $path;
		}

		$cfg = json_decode( $raw, true );
		if ( empty( $cfg ) || !is_array( $cfg ) ) {
			throw new \Exception( sprintf( "Couldn't part JSON from (%s) file '%s'.", $this->configSourceFile, $path ) );
		}

		$keyedOptions = [];
		foreach ( $cfg[ 'options' ] as $option ) {
			if ( !empty( $option[ 'key' ] ) ) {
				$keyedOptions[ $option[ 'key' ] ] = $option;
			}
		}
		$cfg[ 'options' ] = $keyedOptions;

		$cfg[ 'meta' ] = [
			'ts_mod' => Services::WpFs()->getModifiedTime( $this->getConfigSourceFile() ),
		];

		Transient::Set( $this->storeKey(), $cfg, WEEK_IN_SECONDS );
		return $cfg;
	}

	private function getPathCfg() :string {
		return $this->getCon()->paths->forModuleConfig( $this->getMod()->getSlug(), true );
	}

	/**
	 * @param string $file
	 * @return string
	 * @throws \Exception
	 */
	private function loadRawFromFile( string $file ) :string {
		if ( !Services::WpFs()->exists( $file ) ) {
			throw new \Exception( sprintf( 'Configuration file "%s" does not exist.', $file ) );
		}
		$contents = Services::Data()->readFileWithInclude( $file );
		if ( empty( $contents ) ) {
			throw new \Exception( sprintf( 'Configuration file "%s" contents were empty or could not be read.', $file ) );
		}
		return $contents;
	}
}