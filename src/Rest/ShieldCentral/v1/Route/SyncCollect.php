<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\v1\Route;

class SyncCollect extends BaseShieldCentral {

	public function getRoutePath() :string {
		return '/sync/collect';
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
			'daily_totals_since'  => $this->stringArg( 'The first day, Y-m-d, of the daily security totals to send.', false ),
		];
	}
}
