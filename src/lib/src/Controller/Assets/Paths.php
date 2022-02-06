<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Paths {

	use PluginControllerConsumer;

	public function forAsset( string $asset = '' ) :string {
		return $this->forPluginItem( $this->getCon()->cfg->paths[ 'assets' ].'/'.ltrim( $asset, '/' ) );
	}

	public function forModuleConfig( string $module, bool $fromJSONFile = false ) :string {
		return $this->forPluginItem( $this->getCon()->cfg->paths[ 'config' ].'/'.$module.( $fromJSONFile ? '.json' : '.php' ) );
	}

	public function forFlag( string $flag = '' ) :string {
		return $this->forPluginItem( $this->getCon()->cfg->paths[ 'flags' ].'/'.ltrim( $flag, '/' ) );
	}

	public function forImage( string $asset ) :string {
		return $this->forAsset( 'images/'.ltrim( $asset, '/' ) );
	}

	public function forJs( string $asset ) :string {
		return $this->forAsset( 'js/'.ltrim( $asset, '/' ) );
	}

	public function forPluginItem( string $item = '' ) :string {
		return path_join( $this->getCon()->getRootDir(), ltrim( $item, '/' ) );
	}

	public function forSource( string $source = '' ) :string {
		return $this->forPluginItem( $this->getCon()->cfg->paths[ 'source' ].'/'.ltrim( $source, '/' ) );
	}

	public function forTemplate( string $item = '' ) :string {
		return $this->forPluginItem( $this->getCon()->cfg->paths[ 'templates' ].'/'.ltrim( $item, '/' ) );
	}

	public function cacheDir() :string {
		$FS = Services::WpFs();
		$wpContent = path_join( ABSPATH, 'wp-content' );
		if ( !$FS->isDir( $wpContent ) ) {
			$wpContent = WP_CONTENT_DIR;
		}
		return wp_normalize_path( path_join( $wpContent, $this->getCon()->cfg->paths[ 'cache' ] ) );
	}

	public function forCacheItem( string $item = '' ) :string {
		return path_join( $this->cacheDir(), $item );
	}
}