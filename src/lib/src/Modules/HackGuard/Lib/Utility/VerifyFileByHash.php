<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Core\CoreFileHashes;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpThemeVo;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\CrowdSourcedHashes\Query;

class VerifyFileByHash {

	use ModConsumer;

	private $fullPath;

	private $coreFileHashes;

	private $tmpItem;

	private $tmpItemHashes;

	/**
	 * TODO
	 */
	public function verify( string $fullPath ) :bool {
		$this->fullPath = wp_normalize_path( $fullPath );

		return $this->isValidCoreFile();
		/*
				$verified = false;

				if ( $this->isValidCoreFile() ) {
					$verified = true;
				}
				elseif ( $this->isInCoreDir() ) {
					$verified = false;
				}
				elseif ( false && $this->isInPluginsDir() ) {
				}
				elseif ( false && $this->isInThemesDir() ) {
				}

				return $verified;
				*/
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 */
	private function getAssetHashes( $asset ) :array {
		if ( empty( $this->tmpItem ) || $this->tmpItem !== $asset->unique_id ) {
			$this->tmpItem = null;
			$this->tmpItemHashes = null;
		}

		if ( !is_array( $this->tmpItemHashes ) ) {
			$hashes = ( $asset->asset_type === 'plugin' ? new Query\Plugin() : new Query\Theme() )
				->getHashesFromVO( $asset );
			$this->tmpItem = $asset->unique_id;
			$this->tmpItemHashes = is_array( $hashes ) ? $hashes : [];
		}

		return $this->tmpItemHashes;
	}

	private function isValidCoreFile() :bool {
		return $this->getCoreFileHashes()->isCoreFileHashValid( $this->fullPath );
	}

	private function isInCoreDir() :bool {
		return strpos( $this->fullPath, wp_normalize_path( path_join( ABSPATH, 'wp-admin' ) ) ) === 0
			   || strpos( $this->fullPath, wp_normalize_path( path_join( ABSPATH, 'wp-includes' ) ) ) === 0;
	}

	private function isInPluginsDir() :bool {
		return strpos( $this->fullPath, wp_normalize_path( WP_PLUGIN_DIR ) ) === 0;
	}

	private function isInThemesDir() :bool {
		return strpos( $this->fullPath, wp_normalize_path( path_join( WP_CONTENT_DIR, 'themes' ) ) ) === 0;
	}

	private function getCoreFileHashes() :CoreFileHashes {
		if ( empty( $this->coreFileHashes ) ) {
			$this->coreFileHashes = new CoreFileHashes();
		}
		return $this->coreFileHashes;
	}
}