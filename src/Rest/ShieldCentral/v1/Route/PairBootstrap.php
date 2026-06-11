<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Route;

class PairBootstrap extends BaseShieldCentral {

	public function getRoutePath() :string {
		return '/pair/bootstrap';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'pairing_token' => $this->stringArg( 'ShieldCentral pairing token.' ),
			'site_url'      => $this->stringArg( 'Submitted site URL.', false ),
		];
	}
}
