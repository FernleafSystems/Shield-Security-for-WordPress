<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\{
	MatchRequestParamFileUploads,
	RequestBypassesAllRestrictions
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

class FirewallExeFileUploads extends BuildFirewallBase {

	public const SLUG = 'shield/firewall_exe_file_uploads';
	public const SCAN_CATEGORY = 'exe_file_uploads';

	protected function getConditions() :array {
		$conditions = [
			'logic' => static::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => RequestBypassesAllRestrictions::class,
					'logic'      => Constants::LOGIC_INVERT
				],
			]
		];

		$matchGroup = [
			'logic' => static::LOGIC_OR,
			'conditions' => [],
		];

		$simple = $this->getFirewallPatterns_Simple();
		if ( !empty( $simple ) ) {
			$matchGroup[ 'conditions' ][] = [
				'conditions' => MatchRequestParamFileUploads::class,
				'params'    => [
					'is_match_regex' => false,
					'match_patterns' => $simple,
					'match_category' => static::SCAN_CATEGORY,
				],
			];
		}

		$regex = $this->getFirewallPatterns_Regex();
		if ( !empty( $regex ) ) {
			$matchGroup[ 'conditions' ][] = [
				'conditions' => MatchRequestParamFileUploads::class,
				'params'    => [
					'is_match_regex' => true,
					'match_patterns' => $regex,
					'match_category' => static::SCAN_CATEGORY,
				],
			];
		}

		$conditions[ 'conditions' ][] = $matchGroup;

		return $conditions;
	}
}