<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class Urls {

	use PluginControllerConsumer;

	public $includeTS = true;

	public function forImage( string $asset ) :string {
		return $this->forAsset( 'images/'.$asset );
	}

	public function svg( string $asset ) :string {
		return $this->forImage( 'bootstrap/'.Paths::AddExt( $asset, 'svg' ) );
	}

	public function forDist( string $asset, string $type ) :string {
		return $this->forAsset( sprintf( 'dist/shield-%s.bundle.%s', $asset, $type ) );
	}

	public function forDistCSS( string $asset ) :string {
		return $this->forDist( $asset, 'css' );
	}

	public function forDistJS( string $asset ) :string {
		return $this->forDist( $asset, 'js' );
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

	public function forThirdParty( string $slug, string $type ) :string {
		return self::con()->cfg->includes[ 'tp' ][ $slug ][ $type ];
	}

	protected function lookupAssetUrlInSpec( string $asset, string $type ) :?string {
		$asset = $this->lookupAssetInSpec( $asset, $type );
		return empty( $asset[ 'url' ] ) ? null : $asset[ 'url' ];
	}

	protected function lookupAssetInSpec( string $asset, string $type ) :array {
		return self::con()->cfg->includes[ 'register' ][ $type ][ $asset ] ?? [];
	}
}
