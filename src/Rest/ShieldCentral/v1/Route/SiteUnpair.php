<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Route;

class SiteUnpair extends BaseShieldCentral {

	public function getRoutePath() :string {
		return '/site/unpair';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'site_uuid'           => $this->stringArg( 'ShieldCentral site UUID.' ),
			'connection_material' => [
				'description' => 'ShieldCentral connection material.',
				'type'        => 'object',
				'required'    => true,
			],
		];
	}
}
