<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP\UpdateGeoData;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class UpdateIpGeoData extends Base {

	public function execResponse() :void {
		( new UpdateGeoData( $this->p->use_cloudflare ) )
			->setThisRequest( $this->req )
			->run();
	}

	public function getParamsDef() :array {
		return [
			'use_cloudflare' => [
				'type'    => EnumParameters::TYPE_BOOL,
				'default' => false,
				'label'   => __( 'Use CloudFlare As Geo Data Source?', 'wp-simple-firewall' ),
			],
		];
	}
}