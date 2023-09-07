<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class Urls {

	use PluginControllerConsumer;

	public $includeTS = true;

	public function forCss( string $asset ) :string {
		$url = $this->lookupAssetUrlInSpec( $asset, 'css' );
		return empty( $url ) ? $this->forAsset( 'css/'.Paths::AddExt( $asset, 'css' ) ) : $url;
	}

	public function forImage( string $asset ) :string {
		return $this->forAsset( 'images/'.$asset );
	}

	public function svg( string $asset ) :string {
		return $this->forImage( 'bootstrap/'.Paths::AddExt( $asset, 'svg' ) );
	}

	public function forJs( string $asset ) :string {
		$url = $this->lookupAssetUrlInSpec( $asset, 'js' );
		return empty( $url ) ? $this->forAsset( 'js/'.Paths::AddExt( $asset, 'js' ) ) : $url;
	}

	public function forAsset( string $asset ) :string {
		$url = $this->forPluginItem( self::con()->cfg->paths[ 'assets' ].'/'.$asset );
		return $this->includeTS ?
			Services::Includes()->addIncludeModifiedParam( $url, self::con()->paths->forAsset( $asset ) )
			: $url;
	}

	public function forPluginItem( string $path = '' ) :string {
		return URL::Build( plugins_url( $path, self::con()->getRootFile() ), [ 'ver' => self::con()->cfg->version() ] );
	}

	protected function lookupAssetUrlInSpec( string $asset, string $type ) :?string {
		$asset = $this->lookupAssetInSpec( $asset, $type );
		return empty( $asset[ 'url' ] ) ? null : $asset[ 'url' ];
	}

	protected function lookupAssetInSpec( string $asset, string $type ) :array {
		return self::con()->cfg->includes[ 'register' ][ $type ][ $asset ] ?? [];
	}
}
