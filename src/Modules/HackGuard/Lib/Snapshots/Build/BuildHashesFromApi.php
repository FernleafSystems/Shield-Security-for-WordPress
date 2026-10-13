<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\Build;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Hashes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	FileHashAlgorithm,
	NormalizeHashMap
};

class BuildHashesFromApi {

	/**
	 * File keys are normalised paths relative to the asset root.
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return array<string,string>|null - keys are paths relative to the asset root
	 * @throws \Exception
	 */
	public function build( $asset, string $algorithm = 'md5' ) :?array {
		FileHashAlgorithm::validate( $algorithm );
		if ( $asset instanceof WpThemeVo
			 && ( (bool)$asset->is_child || (bool)$asset->is_inactive_child ) ) {
			throw new \Exception( __( 'Live hashes are not supported for child themes.', 'wp-simple-firewall' ) );
		}

		if ( !$asset->isWpOrg() ) {

			$apiSupport = false;

			$apiInfo = ( new Hashes\ApiInfo() )
				->setUseQueryCache( true )
				->getInfo();
			$items = $this->normalisePremiumCatalog( $apiInfo, $asset->asset_type === 'plugin' ? 'plugins' : 'themes' );
			if ( $items !== [] ) {
				if ( $asset->asset_type === 'plugin' ) {
					$slug = $asset->slug;
					$file = $asset->file;
					$name = $asset->Name;
				}
				else {
					$slug = $asset->stylesheet;
					$file = $asset->stylesheet;
					$name = $asset->wp_theme->get( 'Name' );
				}

				foreach ( $items as $maybeItem ) {

					if ( ( $maybeItem[ 'slug' ] !== null && $maybeItem[ 'slug' ] === $slug )
						 || ( $maybeItem[ 'name' ] !== null && $maybeItem[ 'name' ] === $name )
						 || ( $maybeItem[ 'file' ] !== null && $maybeItem[ 'file' ] === $file ) ) {
						$apiSupport = true;
						if ( $asset->asset_type === 'plugin' && empty( $asset->slug ) && $maybeItem[ 'slug' ] !== null ) {
							$asset->slug = $maybeItem[ 'slug' ];
						}
						break;
					}
				}
			}

			if ( !$apiSupport ) {
				throw new \Exception( __( 'Not a WordPress.org asset.', 'wp-simple-firewall' ) );
			}
		}
		return $this->retrieveForAsset( $asset, $algorithm );
	}

	/**
	 * @param mixed $apiInfo
	 * @return list<array{slug:?string,file:?string,name:?string}>
	 */
	private function normalisePremiumCatalog( $apiInfo, string $group ) :array {
		$premium = \is_array( $apiInfo ) ? ( $apiInfo[ 'supported_premium' ] ?? null ) : null;
		$items = \is_array( $premium ) ? ( $premium[ $group ] ?? null ) : null;
		if ( !\is_array( $items ) ) {
			return [];
		}

		$normalised = [];
		foreach ( $items as $item ) {
			if ( !\is_array( $item ) ) {
				continue;
			}
			$row = [ 'slug' => null, 'file' => null, 'name' => null ];
			foreach ( [ 'slug', 'file', 'name' ] as $field ) {
				$value = $item[ $field ] ?? null;
				if ( \is_string( $value ) && \trim( $value ) !== '' ) {
					$row[ $field ] = $value;
				}
			}
			$normalised[] = $row;
		}
		return $normalised;
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 * @return string[]|null
	 * @throws \Exception
	 */
	private function retrieveForAsset( $asset, string $algorithm ) :?array {

		if ( $asset->asset_type === 'plugin' ) {
			$hashes = ( new Hashes\Plugin() )
				->setUseQueryCache( true )
				->getHashes( $asset->slug, $asset->Version, $algorithm );
		}
		elseif ( $asset->asset_type === 'theme' ) {
			$hashes = ( new Hashes\Theme() )
				->setUseQueryCache( true )
				->getHashes( $asset->stylesheet, $asset->version, $algorithm );
		}
		else {
			throw new \Exception( __( 'Not a supported asset type.', 'wp-simple-firewall' ) );
		}

		$hashes = ( new NormalizeHashMap() )->toScalarMapForAlgorithm( $hashes, $algorithm );
		return empty( $hashes ) ? null : $hashes;
	}
}
