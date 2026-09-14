<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots;

use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class FindAssetsToSnap {

	/**
	 * @return array<int,WpPluginVo|WpThemeVo>
	 */
	public function run() :array {
		$assets = [];
		$providers = [
			'plugin' => Services::WpPlugins(),
			'theme'  => Services::WpThemes(),
		];
		foreach ( $providers as $type => $provider ) {
			$candidates = $type === 'plugin'
				? $provider->getPluginsAsVo()
				: $provider->getThemesAsVo();
			$byKey = [];
			$conflicts = [];
			foreach ( $candidates as $candidate ) {
				try {
					if ( !$this->isValidAsset( $candidate, $type ) ) {
						$this->logInvalid( $type );
						continue;
					}
					$key = $type === 'plugin' ? $candidate->file : $candidate->stylesheet;
					if ( !isset( $byKey[ $key ] ) ) {
						$byKey[ $key ] = $candidate;
					}
					elseif ( $byKey[ $key ]->version !== $candidate->version ) {
						$conflicts[ $key ] = true;
					}
				}
				catch ( \Throwable $e ) {
					$this->logInvalid( $type );
				}
			}

			foreach ( \array_keys( $conflicts ) as $key ) {
				try {
					$resolved = $type === 'plugin'
						? $provider->getPluginAsVo( $key, true )
						: $provider->getThemeAsVo( $key, true );
					if ( !$this->isValidAsset( $resolved, $type, $key ) ) {
						$this->logInvalid( $type );
						unset( $byKey[ $key ] );
						continue;
					}
					$byKey[ $key ] = $resolved;
				}
				catch ( \Throwable $e ) {
					$this->logInvalid( $type );
					unset( $byKey[ $key ] );
				}
			}

			$assets = \array_merge( $assets, \array_values( $byKey ) );
		}

		return $assets;
	}

	/**
	 * @param mixed $asset
	 */
	private function isValidAsset( $asset, string $type, ?string $expectedKey = null ) :bool {
		$validClass = $type === 'plugin'
			? $asset instanceof WpPluginVo
			: $asset instanceof WpThemeVo;
		if ( !$validClass || $asset->asset_type !== $type ) {
			return false;
		}

		$key = $type === 'plugin' ? $asset->file : $asset->stylesheet;
		$version = $asset->version;
		return \is_string( $key )
			   && trim( $key ) !== ''
			   && \strpos( $key, "\0" ) === false
			   && ( $expectedKey === null || $key === $expectedKey )
			   && \is_string( $version )
			   && trim( $version ) !== '';
	}

	private function logInvalid( string $type ) :void {
		error_log( \sprintf( 'Shield snapshot inventory skipped an invalid %s asset.', $type ) );
	}
}
