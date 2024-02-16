<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Exceptions\PluginConfigInvalidException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LoadModuleConfigs {

	use PluginControllerConsumer;

	/**
	 * @return ModConfigVO[]
	 * @throws PluginConfigInvalidException
	 */
	public function run() :array {
		$conCfg = self::con()->cfg;

		$slugs = \array_combine( $conCfg->modules, $conCfg->modules );
		if ( !\is_array( $slugs ) ) {
			throw new PluginConfigInvalidException( 'invalid specification of modules' );
		}

		$conCfg->rebuilt = $conCfg->rebuilt || $this->isRebuildNecessary( $slugs );

		return \array_map(
			function ( string $slug ) use ( $conCfg ) {
				/** @var ModConfigVO $cfg */
				$cfg = apply_filters(
					'shield/load_mod_cfg',
					$conCfg->rebuilt ? ( new ModConfigVO() )->applyFromArray( $this->fromCfgFile( $slug ) ) : $conCfg->mods_cfg[ $slug ],
					$slug
				);

				if ( !$cfg instanceof ModConfigVO || !isset( $cfg->properties ) || !\is_array( $cfg->properties ) ) {
					throw new PluginConfigInvalidException( sprintf( "Loading config for module '%s' failed.", $slug ) );
				}

				return $cfg;
			},
			\array_filter( $slugs, function ( $slug ) {
				return Services::WpFs()->isAccessibleFile( self::con()->paths->forModuleConfig( $slug ) );
			} )
		);
	}

	private function isRebuildNecessary( array $slugs ) :bool {
		$con = self::con();
		$rebuild = false;
		foreach ( $slugs as $slug ) {
			$path = $con->paths->forModuleConfig( $slug );
			$priorCfg = $con->cfg->mods_cfg[ $slug ] ?? null;
			if ( !$priorCfg instanceof ModConfigVO
				 || ( Services::WpFs()->getModifiedTime( $path ) > $priorCfg->meta[ 'ts_mod' ] ) ) {
				$rebuild = true;
				break;
			}
		}
		return $rebuild;
	}

	/**
	 * @throws PluginConfigInvalidException
	 */
	private function fromCfgFile( string $slug ) :array {
		$path = self::con()->paths->forModuleConfig( $slug );

		$raw = Services::Data()->readFileWithInclude( $path );
		if ( empty( $raw ) ) {
			throw new PluginConfigInvalidException( sprintf( 'Configuration file "%s" contents were empty or could not be read.', $path ) );
		}

		$cfg = \json_decode( $raw, true );
		if ( empty( $cfg ) || !\is_array( $cfg ) ) {
			throw new PluginConfigInvalidException( sprintf( "Couldn't parse JSON from file '%s'.", $path ) );
		}

		$keyedOptions = [];
		foreach ( $cfg[ 'options' ] ?? [] as $option ) {
			if ( !empty( $option[ 'key' ] ) ) {
				$keyedOptions[ $option[ 'key' ] ] = $option;
			}
		}
		$cfg[ 'options' ] = $keyedOptions;

		$cfg[ 'meta' ] = [
			'ts_mod' => Services::WpFs()->getModifiedTime( $path ),
		];

		if ( empty( $cfg[ 'slug' ] ) ) {
			$cfg[ 'slug' ] = $cfg[ 'properties' ][ 'slug' ];
		}

		$cfg[ 'properties' ] = \array_merge( [
			'storage_key'           => $cfg[ 'slug' ],
			'tagline'               => '',
			'show_module_options'   => true,
			'tracking_exclude'      => false,
			'menu_priority'         => 100,
		], $cfg[ 'properties' ] );

		$cfg[ 'menus' ] = \array_merge( [
			'config_menu_priority' => 100,
		], $cfg[ 'menus' ] ?? [] );

		if ( empty( $cfg[ 'properties' ][ 'storage_key' ] ) ) {
			$cfg[ 'properties' ][ 'storage_key' ] = $cfg[ 'properties' ][ 'slug' ];
		}

		return $cfg;
	}
}