<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Options;

class RequestTriggersFirewall extends Base {

	use Traits\TypeRequest;

	public function getDescription() :string {
		return __( 'Do any parameters in the request match the given set of parameters to test.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		/** @var Options $opts */
		$opts = self::con()->getModule_Firewall()->opts();

		$paramConditions = [];

		foreach ( $opts->getDef( 'firewall_patterns' ) as $key => $group ) {
			if ( $key !== 'exe_file_uploads' && $opts->isOpt( 'block_'.$key, 'Y' ) ) {
				foreach ( $group as $pattern ) {
					$paramConditions[] = [
						'conditions' => Conditions\FirewallPatternFoundInRequest::class,
						'params'     => [
							'pattern'    => $pattern,
							'match_type' => Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
						]
					];
				}
			}
		}

		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => Conditions\RequestBypassesAllRestrictions::class,
					'logic'      => Enum\EnumLogic::LOGIC_INVERT,
				],
				[
					'conditions' => Conditions\RequestHasAnyParameters::class,
				],
				[
					'conditions' => Conditions\IsRequestWhitelistedForFirewall::class,
					'logic'      => Enum\EnumLogic::LOGIC_INVERT,
				],
				[
					'logic'      => Enum\EnumLogic::LOGIC_OR,
					'conditions' => $paramConditions,
				]
			]
		];
	}
}