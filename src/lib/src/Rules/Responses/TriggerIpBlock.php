<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class TriggerIpBlock extends Base {

	public const SLUG = 'trigger_ip_block';

	public function execResponse() :bool {
		self::con()
			->getModule_IPs()
			->loadOffenseTracker()
			->setIsBlocked( true );
		return true;
	}
}