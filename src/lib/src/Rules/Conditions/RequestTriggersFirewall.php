<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
};

class RequestTriggersFirewall extends Base {

	use Traits\TypeRequest;

	public function getDescription() :string {
		return __( 'Do any parameters in the request match the given set of parameters to test.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		$con = self::con();

		$paramConditions = [];

		foreach ( $con->cfg->configuration->def( 'firewall_patterns' ) as $key => $group ) {
			if ( $con->opts->optIs( 'block_'.$key, 'Y' ) ) {
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