<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Config;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

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

	private $pathToCfg = '';

	private $isBuiltFromFile = false;

	/**
	 * @throws \Exception
	 */
	public function run() :ModConfigVO {
		$this->pathToCfg = $this->con()->paths->forModuleConfig( $this->slug );
		if ( !Services::WpFs()->exists( $this->pathToCfg ) ) {
			throw new \Exception( sprintf( 'Configuration file "%s" does not exist.', $this->pathToCfg ) );
		}

		$rebuild = $this->con()->cfg->rebuilt
				   || !$this->cfg instanceof ModConfigVO
				   || ( Services::WpFs()->getModifiedTime( $this->pathToCfg ) > $this->cfg->meta[ 'ts_mod' ] );
		if ( $rebuild ) {
			$this->con()->cfg->rebuilt = true;
		}
		return $rebuild ? ( new ModConfigVO() )->applyFromArray( $this->fromFile() ) : $this->cfg;
	}

	/**
	 * @throws \Exception
	 */
	private function fromFile() :array {
		$raw = $this->loadRawFromFile();

		$cfg = json_decode( $raw, true );
		if ( empty( $cfg ) || !is_array( $cfg ) ) {
			throw new \Exception( sprintf( "Couldn't parse JSON from file '%s'.", $this->pathToCfg ) );
		}

		$keyedOptions = [];
		foreach ( $cfg[ 'options' ] ?? [] as $option ) {
			if ( !empty( $option[ 'key' ] ) ) {
				$keyedOptions[ $option[ 'key' ] ] = $option;
			}
		}
		$cfg[ 'options' ] = $keyedOptions;

		$cfg[ 'meta' ] = [
			'ts_mod' => Services::WpFs()->getModifiedTime( $this->pathToCfg ),
		];

		if ( empty( $cfg[ 'slug' ] ) ) {
			$cfg[ 'slug' ] = $cfg[ 'properties' ][ 'slug' ];
		}

		$cfg[ 'properties' ] = array_merge( [
			'namespace'             => str_replace( ' ', '', ucwords( str_replace( '_', ' ', $cfg[ 'slug' ] ) ) ),
			'storage_key'           => $cfg[ 'slug' ],
			'tagline'               => '',
			'premium'               => false,
			'access_restricted'     => true,
			'auto_enabled'          => false,
			'auto_load_processor'   => false,
			'skip_processor'        => false,
			'show_module_options'   => false,
			'run_if_whitelisted'    => true,
			'run_if_verified_bot'   => true,
			'run_if_wpcli'          => true,
			'tracking_exclude'      => false,
			'sidebar_name'          => $cfg[ 'properties' ][ 'name' ],
			'menu_title'            => $cfg[ 'properties' ][ 'name' ],
			'menu_priority'         => 100,
			'highlight_menu_item'   => false,
			'show_module_menu_item' => false,
		], $cfg[ 'properties' ] );

		$cfg[ 'menus' ] = array_merge( [
			'config_menu_priority' => 100,
		], $cfg[ 'menus' ] ?? [] );

		if ( empty( $cfg[ 'properties' ][ 'storage_key' ] ) ) {
			$cfg[ 'properties' ][ 'storage_key' ] = $cfg[ 'properties' ][ 'slug' ];
		}

		return $cfg;
	}

	/**
	 * @throws \Exception
	 */
	private function loadRawFromFile() :string {
		$contents = Services::Data()->readFileWithInclude( $this->pathToCfg );
		if ( empty( $contents ) ) {
			throw new \Exception( sprintf( 'Configuration file "%s" contents were empty or could not be read.', $this->pathToCfg ) );
		}
		return $contents;
	}
}