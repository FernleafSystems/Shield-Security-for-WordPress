<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\Exceptions\AssetHashesNotFound;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\CrowdSourcedHashes\Query;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class Retrieve {

	use ModConsumer;

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
	 * @param array                $hashes
	 */
	private function addItemHashesToCache( $vo, array $hashes ) {
		if ( $vo->asset_type == 'plugin' ) {
			self::$hashes[ 'plugins' ][ $vo->slug ] = $hashes;
		}
		else {
			self::$hashes[ 'themes' ][ $vo->slug ] = $hashes;
		}
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @return  array
	 */
	private function getAssetHashesFromCache( $vo ) {
		$key = ( $vo->asset_type == 'plugin' ) ? 'plugins' : 'themes';
		return self::$hashes[ $key ][ $vo->slug ] ?? null;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @throws \Exception
	 */
	private function fromLocalStore( $vo ) :array {
		return ( new StoreAction\Load() )
			->setMod( $this->getMod() )
			->setAsset( $vo )
			->run()
			->getSnapData();
	}

	/**
	 * @param string $slug
	 * @return array
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
	 * @return array
	 * @throws AssetHashesNotFound|\Exception
	 */
	public function byVO( $vo ) :array {
		$hashes = $this->getAssetHashesFromCache( $vo );

		if ( is_null( $hashes ) ) {
			$hashes = $this->fromCsHashes( $vo );
			if ( empty( $hashes ) ) {
				try {
					$hashes = $this->fromLocalStore( $vo );
				}
				catch ( \Exception $e ) {
					$hashes = [];
				}
			}
			$this->addItemHashesToCache( $vo, $hashes );
		}

		if ( empty( $hashes ) ) {
			throw new AssetHashesNotFound( sprintf( 'Could not locate hashes for VO: %s', $vo->slug ) );
		}
		return $hashes;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $vo
	 * @return array
	 */
	public function fromCsHashes( $vo ) :array {
		$query = ( $vo->asset_type == 'plugin' ) ? new Query\Plugin() : new Query\Theme();
		return $query->getHashesFromVO( $vo );
	}
}