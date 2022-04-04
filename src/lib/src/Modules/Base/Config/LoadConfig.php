<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Config;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class LoadConfig {

	use PluginControllerConsumer;

	private $slug;

	/**
	 * @var ModConfigVO|null
	 */
	private $cfg;

	public function __construct( string $slug, $cfg = null ) {
		$this->slug = $slug;
		$this->cfg = $cfg;
	}

	private $configSourceFile = '';

	private $isBuiltFromFile = false;

	public function getConfigSourceFile() :string {
		return empty( $this->configSourceFile ) ? $this->getPathCfg() : $this->configSourceFile;
	}

	public function isBuiltFromFile() :bool {
		return $this->isBuiltFromFile;
	}

	/**
	 * @throws \Exception
	 */
	public function run() :ModConfigVO {
		$rebuild = $this->getCon()->cfg->rebuilt
				   || !$this->cfg instanceof ModConfigVO
				   || ( Services::WpFs()
								->getModifiedTime( $this->getConfigSourceFile() ) > $this->cfg->meta[ 'ts_mod' ] );
		return $rebuild ? ( new ModConfigVO() )->applyFromArray( $this->fromFile() ) : $this->cfg;
	}

	/**
	 * @throws \Exception
	 * @deprecated 15.0
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
		return 'shield_mod_config_'.$this->slug;
	}

	/**
	 * @throws \Exception
	 */
	public function fromFile() :array {
		$path = $this->getPathCfg();
		try {
			$raw = $this->loadRawFromFile( $path );
			$this->configSourceFile = $path;
		}
		catch ( \Exception $e ) {
			$path = $this->getCon()->paths->forModuleConfig( $this->slug, false );
			$raw = $this->loadRawFromFile( $path );
			$this->configSourceFile = $path;
		}

		$cfg = json_decode( $raw, true );
		if ( empty( $cfg ) || !is_array( $cfg ) ) {
			throw new \Exception( sprintf( "Couldn't parse JSON from (%s) file '%s'.", $this->configSourceFile, $path ) );
		}

		$keyedOptions = [];
		foreach ( $cfg[ 'options' ] ?? [] as $option ) {
			if ( !empty( $option[ 'key' ] ) ) {
				$keyedOptions[ $option[ 'key' ] ] = $option;
			}
		}
		$cfg[ 'options' ] = $keyedOptions;

		$cfg[ 'meta' ] = [
			'ts_mod' => Services::WpFs()->getModifiedTime( $this->getConfigSourceFile() ),
		];

		return $cfg;
	}

	private function getPathCfg() :string {
		return $this->getCon()->paths->forModuleConfig( $this->slug, true );
	}

	/**
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