<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Route;

class PairCleanup extends BaseShieldCentral {

	public function getRoutePath() :string {
		return '/pair/cleanup';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'site_uuid'           => $this->stringArg( 'ShieldCentral site UUID.', false ),
			'pairing_session'     => $this->stringArg( 'Pairing session to clean up.', false ),
			'connection_material' => [
				'description' => 'ShieldCentral connection material.',
				'type'        => 'object',
				'required'    => false,
			],
		];
	}
}
