<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

/**
 * @deprecated 18.5.8
 */
class ProcessIpBlockedCrowdsec extends Base {

	public const SLUG = 'process_ip_blocked_crowdsec';

	public function execResponse() :bool {
		return true;
	}
}