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

	private static $hashes;

	public function __construct() {
		if ( !isset( self::$hashes ) ) {
			self::$hashes = [
				'plugins' => [],
				'themes'  => [],
			];
		}
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @throws \Exception
	 */
	private function fromLocalStore( $vo ) :array {
		return ( new StoreAction\Load() )
			->setAsset( $vo )
			->run()
			->getSnapData();
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
		$hashes = self::$hashes[ $vo->asset_type === 'plugin' ? 'plugins' : 'themes' ][ $vo->slug ] ?? null;

		if ( \is_null( $hashes ) ) {
			try {
				$hashes = $this->fromCsHashes( $vo );
			}
			catch ( \Exception $e ) {
				try {
					$hashes = $this->fromLocalStore( $vo );
				}
				catch ( \Exception $e ) {
					$hashes = [];
				}
			}

			// cache it.
			self::$hashes[ $vo->asset_type === 'plugin' ? 'plugins' : 'themes' ][ $vo->slug ] = $hashes;
		}

		if ( empty( $hashes ) ) {
			throw new AssetHashesNotFound( sprintf( 'Could not locate hashes for VO: %s', $vo->slug ) );
		}
		return $hashes;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @throws \Exception
	 */
	private function fromCsHashes( $vo ) :array {
		if ( !self::con()->caps->canScanPluginsThemesRemote() && !$vo->isWpOrg() ) {
			throw new \Exception( 'Insufficient permissions to use CS Hashes for premium plugins/themes.' );
		}
		$hashes = ( $vo->asset_type == 'plugin' ? new Query\Plugin() : new Query\Theme() )->getHashesFromVO( $vo );
		if ( empty( $hashes ) ) {
			throw new \Exception( 'No CS Hashes available.' );
		}
		return $hashes;
	}
}