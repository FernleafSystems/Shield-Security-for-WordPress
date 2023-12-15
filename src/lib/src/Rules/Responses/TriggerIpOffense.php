<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class TriggerIpOffense extends Base {

	public const SLUG = 'trigger_ip_offense';

	public function execResponse() :bool {
		self::con()
			->getModule_IPs()
			->loadOffenseTracker()
			->incrementCount( $this->params[ 'count' ] );
		return true;
	}

	public function getParamsDef() :array {
		return [
			'count' => [
				'type'    => EnumParameters::TYPE_INT,
				'label'   => __( 'Increment Offenses By', 'wp-simple-firewall' ),
				'default' => 1,
			],
		];
	}
}