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

	public function forJs( string $asset ) :string {
		$url = $this->lookupAssetUrlInSpec( $asset, 'js' );
		return empty( $url ) ? $this->forAsset( 'js/'.Paths::AddExt( $asset, 'js' ) ) : $url;
	}

	public function forAsset( string $asset ) :string {
		$con = $this->getCon();
		$url = $this->forPluginItem( $con->cfg->paths[ 'assets' ].'/'.$asset );
		return $this->includeTS ?
			Services::Includes()->addIncludeModifiedParam( $url, $con->paths->forAsset( $asset ) )
			: $url;
	}

	public function forPluginItem( string $path = '' ) :string {
		$con = $this->getCon();
		return URL::Build( plugins_url( $path, $con->getRootFile() ), [ 'ver' => $con->getVersion() ] );
	}

	protected function lookupAssetUrlInSpec( string $asset, string $type ) :?string {
		$asset = $this->lookupAssetInSpec( $asset, $type );
		return empty( $asset[ 'url' ] ) ? null : $asset[ 'url' ];
	}

	protected function lookupAssetInSpec( string $asset, string $type ) :array {
		return $this->getCon()->cfg->includes[ 'register' ][ $type ][ $asset ] ?? [];
	}
}
