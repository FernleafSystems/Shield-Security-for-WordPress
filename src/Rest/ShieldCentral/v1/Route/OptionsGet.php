<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Route;

class OptionsGet extends BaseShieldCentral {

	public function getRoutePath() :string {
		return '/options/get';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'site_uuid'            => $this->stringArg( 'ShieldCentral site UUID.' ),
			'auth_mode'            => $this->stringArg( 'ShieldCentral auth mode.', false ),
			'connection_material'  => [
				'description' => 'ShieldCentral connection material.',
				'type'        => 'object',
				'required'    => true,
			],
			'known_catalogue_hash' => $this->stringArg( 'The catalogue hash ShieldCentral holds; the catalogue is sent when it differs.', false ),
		];
	}
}
