<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\Exceptions\AssetHashesNotFound;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\CrowdSourcedHashes\Query;

class Retrieve {

	use PluginControllerConsumer;

	private static array $hashes;

	private static array $trustedSources;

	public function __construct() {
		self::$hashes ??= [];
		self::$trustedSources ??= [];
	}

	/**
	 * @throws AssetHashesNotFound
	 * @throws \Exception
	 */
	public function bySlug( string $slug ) :array {
		$vo = Services::WpPlugins()->getPluginAsVo( $slug );
		if ( empty( $vo ) ) {
			$vo = Services::WpThemes()->getThemeAsVo( $slug );
			if ( empty( $vo ) ) {
				throw new \Exception( sprintf( 'Plugin or theme not installed for slug: %s', $slug ) );
			}
		}
		return $this->byVO( $vo );
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @throws AssetHashesNotFound|\Exception
	 */
	public function byVO( $vo ) :array {
		return $this->byVOWithSource( $vo )[ 'hashes' ];
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @return array{hashes:array, trusted_source:bool}
	 * @throws AssetHashesNotFound|\Exception
	 */
	public function byVOWithSource( $vo ) :array {
		$cacheKey = $this->buildCacheKey( $vo );
		$hashes = self::$hashes[ $cacheKey ] ?? null;
		$trustedSource = self::$trustedSources[ $cacheKey ] ?? false;

		if ( \is_null( $hashes ) ) {
			$trustedSource = false;
			try {
				$hashes = $this->fromCsHashes( $vo );
				$trustedSource = true;
			}
			catch ( \Exception $e ) {
				try {
					$localStore = $this->fromLocalStoreWithMeta( $vo );
					$hashes = $localStore[ 'hashes' ];
					$trustedSource = $localStore[ 'trusted_source' ];
				}
				catch ( \Exception $e ) {
					$hashes = [];
				}
			}

			// cache it.
			self::$hashes[ $cacheKey ] = $hashes;
			self::$trustedSources[ $cacheKey ] = $trustedSource;
		}

		if ( empty( $hashes ) ) {
			throw new AssetHashesNotFound( sprintf( __( 'Could not locate hashes for VO: %s', 'wp-simple-firewall' ), $vo->slug ) );
		}
		return [
			'hashes'         => $hashes,
			'trusted_source' => $trustedSource,
		];
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @return array{hashes:array, trusted_source:bool}
	 * @throws \Exception
	 */
	private function fromLocalStoreWithMeta( $vo ) :array {
		$store = ( new StoreAction\Load() )
			->setAsset( $vo )
			->run();
		return [
			'hashes'         => $store->getSnapData(),
			'trusted_source' => ( $store->getSnapMeta()[ 'live_hashes' ] ?? false ) === true,
		];
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @throws \Exception
	 */
	private function fromCsHashes( $vo ) :array {
		if ( !self::con()->caps->canScanPluginsThemesRemote() && !$vo->isWpOrg() ) {
			throw new \Exception( __( 'Insufficient permissions to use crowd-sourced hashes for premium plugins/themes.', 'wp-simple-firewall' ) );
		}
		$hashes = ( $vo->asset_type == 'plugin' ? new Query\Plugin() : new Query\Theme() )->getHashesFromVO( $vo );
		if ( empty( $hashes ) ) {
			throw new \Exception( __( 'No crowd-sourced hashes available.', 'wp-simple-firewall' ) );
		}
		return $hashes;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 */
	private function buildCacheKey( $vo ) :string {
		return \implode( '|', [
			(string)$vo->asset_type,
			(string)$vo->unique_id,
			(string)$vo->Version,
		] );
	}
}
