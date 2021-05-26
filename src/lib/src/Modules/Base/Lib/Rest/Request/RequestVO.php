<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request;

/**
 * Class RequestVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request
 * @property string $action
 * @property string $type
 */
class RequestVO extends \FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass {

	public function getCacheFileSlug() :string {
		$aD = $this->getRawData();
		ksort( $aD );
		return md5( serialize( $aD ) );
	}
}