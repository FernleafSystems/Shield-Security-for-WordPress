<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Route;

class PairHealth extends BaseShieldCentral {

	public function getRoutePath() :string {
		return '/pair/health';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'pairing_session' => $this->stringArg( 'Pairing session ID.' ),
		];
	}
}
