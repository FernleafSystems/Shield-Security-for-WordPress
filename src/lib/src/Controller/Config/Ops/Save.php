<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\ConfigVO;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class Save {

	public static function ToWp( ConfigVO $cfg, string $key ) :bool {
		return Transient::Set( $key, $cfg->getRawData() );
	}
}