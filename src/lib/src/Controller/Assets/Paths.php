<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities;

class Paths {

	use PluginControllerConsumer;

	public function forAsset( string $asset = '' ) :string {
		return $this->forPluginItem( self::con()->cfg->paths[ 'assets' ].'/'.\ltrim( $asset, '/' ) );
	}

	public function forModuleConfig( string $module ) :string {
		return $this->forPluginItem( self::con()->cfg->paths[ 'config' ].'/'.$module.'.json' );
	}

	public function forFlag( string $flag = '' ) :string {
		return $this->forPluginItem( self::con()->cfg->paths[ 'flags' ].'/'.\ltrim( $flag, '/' ) );
	}

	public function forImage( string $asset ) :string {
		return $this->forAsset( 'images/'.\ltrim( $asset, '/' ) );
	}

	public function forSVG( string $asset ) :string {
		return $this->forImage( 'bootstrap/'.Utilities\File\Paths::AddExt( \ltrim( $asset, '/' ), 'svg' ) );
	}

	public function forJs( string $asset ) :string {
		return $this->forAsset( 'js/'.\ltrim( $asset, '/' ) );
	}

	public function forPluginItem( string $item = '' ) :string {
		return path_join( self::con()->getRootDir(), \ltrim( $item, '/' ) );
	}

	public function forSource( string $source = '' ) :string {
		return $this->forPluginItem( self::con()->cfg->paths[ 'source' ].'/'.\ltrim( $source, '/' ) );
	}

	public function forTemplate( string $item = '' ) :string {
		return $this->forPluginItem( self::con()->cfg->paths[ 'templates' ].'/'.\ltrim( $item, '/' ) );
	}
}