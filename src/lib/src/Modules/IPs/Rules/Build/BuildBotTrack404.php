<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Rules;

class BuildBotTrack404 {

	use Shield\Modules\ModConsumer;

	public function build() {
		$rules = new Rules\RuleVO();
		$rules->slug = Rules\Conditions\IsBotProbe404::SLUG;
		$rules->flags = [
			'is_logged_in'
		];
	}
}