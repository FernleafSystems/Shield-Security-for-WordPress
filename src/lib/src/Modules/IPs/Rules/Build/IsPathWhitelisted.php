<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\RuleTraits,
	Conditions
};

/**
 * @deprecated 18.5.8
 */
class IsPathWhitelisted extends BuildRuleIpsBase {

	use RuleTraits\InstantExec;

	public const SLUG = 'shield/is_path_whitelisted';

	protected function getName() :string {
		return 'Is Path Whitelisted';
	}

	protected function getDescription() :string {
		return 'Test whether the current Request Path is whitelisted.';
	}

	protected function getConditions() :array {
		return [
			'conditions' => Conditions\MatchRequestPaths::class,
			'params'     => [
				'match_paths' => $this->buildPaths(),
			]
		];
	}

	private function buildPaths() :array {
		return [];
	}
}