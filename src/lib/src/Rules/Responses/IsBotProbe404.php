<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class IsBotProbe404 extends Base {

	public const SLUG = 'is_bot_probe_404';

	protected function execResponse() :bool {
		return true;
	}
}