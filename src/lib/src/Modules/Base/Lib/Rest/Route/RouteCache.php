<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Route;

use FernleafSystems\Utilities\Data\Adapter\DynProperties;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Cache\CacheDefVO;

/**
 * @property bool            $can_cache
 * @property string          $request_file
 * @property bool            $is_touch
 * @property int             $expiration
 * @property RouteBase|mixed $oRoute
 */
class RouteCache {

	use DynProperties;
	use RestRouteConsumer;

	/**
	 * RouteCache constructor.
	 * @param RouteBase|mixed $route
	 */
	public function __construct( $route ) {
		$this->setRestRoute( $route );
	}

	public function getCacheDefinition() :CacheDefVO {
		$def = new CacheDefVO();
		try {
			$def->dir = path_join( $this->oRoute->getWorkingDir(), 'cache' );
			$def->expiration = (int)$this->expiration;
			$def->touch_on_load = (bool)$this->is_touch;
			if ( !empty( $this->request_file ) ) {
				$def->file_fragment = Services::Data()->addExtensionToFilePath(
					$this->request_file, 'json'
				);
			}
		}
		catch ( \Exception $e ) {
		}
		return $def;
	}
}