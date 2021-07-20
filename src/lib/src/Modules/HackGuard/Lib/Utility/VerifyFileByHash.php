<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Core\CoreFileHashes;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\{
	Plugin,
	Theme
};

class VerifyFileByHash {

	use ModConsumer;

	private $fullPath;

	private $coreFileHashes;

	public function verify( string $fullPath ) :bool {
		$this->fullPath = wp_normalize_path( $fullPath );
		$verified = false;

		$normalAbspath = wp_normalize_path( ABSPATH );

		if ( $this->isValidCoreFile() ) {
			return true;
		}
		elseif ( $this->isInCoreDir() ) {
			return false;
		}
		elseif ( $this->isInPluginsDir() ) {
			$asset = ( new Plugin\Files() )->findPluginFromFile( $this->fullPath );
			if ( !empty( $asset ) ) {

			}
		}
		elseif ( $this->isInThemesDir() ) {
			$asset = ( new Theme\Files() )->findThemeFromFile( $this->fullPath );
			if ( !empty( $asset ) ) {

			}
		}

		return $verified;
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