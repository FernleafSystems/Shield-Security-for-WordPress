<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;

class FirewallExeFileUploads extends BuildFirewallBase {

	const SLUG = 'shield/firewall_exe_file_uploads';
	const SCAN_CATEGORY = 'exe_file_uploads';

	protected function getConditions() :array {
		$conditions = [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'rule'         => Shield\Modules\Plugin\Rules\Build\RequestBypassesAllRestrictions::SLUG,
					'invert_match' => true
				],
			]
		];

		$matchGroup = [
			'logic' => static::LOGIC_OR,
			'group' => [],
		];

		$simple = $this->getFirewallPatterns_Simple();
		if ( !empty( $simple ) ) {
			$matchGroup[ 'group' ][] = [
				'action' => Shield\Rules\Conditions\MatchRequestParamFileUploads::SLUG,
				'params' => [
					'is_match_regex' => false,
					'match_patterns' => $simple,
					'match_category' => static::SCAN_CATEGORY,
				],
			];
		}

		$regex = $this->getFirewallPatterns_Regex();
		if ( !empty( $regex ) ) {
			$matchGroup[ 'group' ][] = [
				'action' => Shield\Rules\Conditions\MatchRequestParamFileUploads::SLUG,
				'params' => [
					'is_match_regex' => true,
					'match_patterns' => $regex,
					'match_category' => static::SCAN_CATEGORY,
				],
			];
		}

		$conditions[ 'group' ][] = $matchGroup;

		return $conditions;
	}
}