<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Resources;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Dynamic
 * @package FernleafSystems\Wordpress\Plugin\Shield\Utilities\Resources
 * @deprecated 11.4
 */
class Dynamic {

	const RESOURCES_DIR = 'resources';
	use PluginControllerConsumer;

	public function getBaseDir() :string {
		return path_join( $this->getCon()->getPluginCachePath(), self::RESOURCES_DIR );
	}

	public function getResourcePath( string $resource ) :string {
		$path = path_join( $this->getBaseDir(), $resource );
		Services::WpFs()->mkdir( dirname( $path ) );
		return $path;
	}

	public function getResourceUrl( string $resource ) :string {
		$this->getResourcePath( $resource );
		$url = content_url(
			sprintf( '%s/%s/%s',
				$this->getCon()->cfg->paths[ 'cache' ],
				self::RESOURCES_DIR,
				$resource
			)
		);
		if ( $this->resourceExists( $resource ) ) {
			$url = add_query_arg(
				[ 'mtime' => Services::WpFs()->getModifiedTime( $this->getResourcePath( $resource ) ) ],
				$url
			);
		}
		return $url;
	}

	public function getModifiedTime( string $res ) :int {
		return (int)Services::WpFs()->getModifiedTime( $this->getResourcePath( $res ) );
	}

	public function resourceCreate( string $res, string $content ) {
		Services::WpFs()->putFileContent( $this->getResourcePath( $res ), $content );
	}

	public function resourceDelete( string $res ) {
		Services::WpFs()->deleteFile( $this->getResourcePath( $res ) );
	}

	public function resourceExists( string $res ) :bool {
		return Services::WpFs()->exists( $this->getResourcePath( $res ) );
	}
}