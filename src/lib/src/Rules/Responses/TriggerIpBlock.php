<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class TriggerIpBlock extends Base {

	public const SLUG = 'trigger_ip_block';

	public function execResponse() :void {
		self::con()->comps->offense_tracker->setIsBlocked( true );
	}
}