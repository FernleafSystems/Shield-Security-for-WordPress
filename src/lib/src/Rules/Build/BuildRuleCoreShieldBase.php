<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

abstract class BuildRuleCoreShieldBase extends BuildRuleBase {

	protected function getFlags() :array {
		return [
			'is_core_shield' => true
		];
	}
}