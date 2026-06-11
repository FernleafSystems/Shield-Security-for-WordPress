<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Route;

class PairFinalize extends BaseShieldCentral {

	public function getRoutePath() :string {
		return '/pair/finalize';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'pairing_session' => $this->stringArg( 'Pairing session ID.' ),
			'site_uuid'       => $this->stringArg( 'ShieldCentral site UUID.' ),
		];
	}
}
