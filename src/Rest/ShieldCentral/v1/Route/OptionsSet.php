<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Route;

class OptionsSet extends BaseShieldCentral {

	public function getRoutePath() :string {
		return '/options/set';
	}

	protected function getRouteArgsCustom() :array {
		return [
			'site_uuid'           => $this->stringArg( 'ShieldCentral site UUID.' ),
			'auth_mode'           => $this->stringArg( 'ShieldCentral auth mode.', false ),
			'connection_material' => [
				'description' => 'ShieldCentral connection material.',
				'type'        => 'object',
				'required'    => true,
			],
			'catalogue_hash'      => $this->stringArg( 'The catalogue hash the options were prepared for.' ),
			'options'             => [
				'description' => 'The option values to set, keyed by option key.',
				'type'        => 'object',
				'required'    => true,
			],
		];
	}
}
