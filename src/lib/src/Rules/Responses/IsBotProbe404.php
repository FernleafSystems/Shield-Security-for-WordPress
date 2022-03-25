<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Services\Services;

class IsBotProbe404 extends Base {

	const SLUG = 'is_bot_probe_404';

	protected function execResponse() :bool {
		return true;
	}
}