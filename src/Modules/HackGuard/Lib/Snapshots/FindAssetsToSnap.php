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
		foreach ( [
			'plugin' => Services::WpPlugins()->getPluginsAsVo(),
			'theme'  => Services::WpThemes()->getThemesAsVo(),
		] as $type => $candidates ) {
			foreach ( $this->collectValidKeys( $candidates, $type ) as $key ) {
				try {
					$asset = $type === 'plugin'
						? Services::WpPlugins()->getPluginAsVo( $key, true )
						: Services::WpThemes()->getThemeAsVo( $key, true );
					if ( !$this->isValidAsset( $asset, $type, $key ) ) {
						$this->logInvalid( $type );
						continue;
					}
					$assets[] = $asset;
				}
				catch ( \Throwable $e ) {
					$this->logInvalid( $type );
				}
			}
		}

		return $assets;
	}

	/**
	 * @param mixed[] $candidates
	 * @return string[]
	 */
	private function collectValidKeys( array $candidates, string $type ) :array {
		$keys = [];
		foreach ( $candidates as $candidate ) {
			try {
				if ( !$this->isValidAsset( $candidate, $type ) ) {
					$this->logInvalid( $type );
					continue;
				}
				$key = $type === 'plugin' ? $candidate->file : $candidate->stylesheet;
				$keys[ $key ] = true;
			}
			catch ( \Throwable $e ) {
				$this->logInvalid( $type );
			}
		}
		return \array_keys( $keys );
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
