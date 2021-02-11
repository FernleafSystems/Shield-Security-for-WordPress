<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Urls {

	use PluginControllerConsumer;

	public function forCss( string $asset ) :string {
		$url = $this->lookupAssetUrlInSpec( $asset, 'css' );
		return empty( $url ) ?
			$this->forAsset( 'css/'.Services::Data()->addExtensionToFilePath( $asset, 'css' ) )
			: $url;
	}

	public function forImage( string $asset ) :string {
		return $this->forAsset( 'images/'.$asset );
	}

	public function forJs( string $asset ) :string {
		$url = $this->lookupAssetUrlInSpec( $asset, 'js' );
		return empty( $url ) ?
			$this->forAsset( 'js/'.Services::Data()->addExtensionToFilePath( $asset, 'js' ) )
			: $url;
	}

	public function forAsset( string $asset ) :string {
		$con = $this->getCon();

		$path = $con->paths->forAsset( $asset );
		if ( Services::WpFs()->exists( $path ) ) {
			$url = Services::Includes()->addIncludeModifiedParam(
				$this->forPluginItem( $con->cfg->paths[ 'assets' ].'/'.$asset ),
				$path
			);
		}
		else {
			$url = '';
		}

		return $url;
	}

	public function forPluginItem( string $path = '' ) :string {
		$con = $this->getCon();
		return add_query_arg( [ 'ver' => $con->getVersion() ], plugins_url( $path, $con->getRootFile() ) );
	}

	/**
	 * @param string $asset
	 * @param string $type
	 * @return mixed|null
	 */
	protected function lookupAssetUrlInSpec( string $asset, string $type ) {
		$registrations = $this->getCon()->cfg->includes[ 'register' ][ $type ];
		if ( isset( $registrations[ $asset ] ) && !empty( $registrations[ $asset ][ 'url' ] ) ) {
			return $registrations[ $asset ][ 'url' ];
		}
		return null;
	}
}
