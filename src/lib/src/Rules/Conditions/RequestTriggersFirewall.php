<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum,
};

class RequestTriggersFirewall extends Base {

	use Traits\TypeRequest;

	public function getDescription() :string {
		return __( "Do any parameters in the request match the given set of parameters to test.", 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		$paramConditions = [];

		$patterns = self::con()->getModule_Firewall()->opts()->getDef( 'firewall_patterns' );
		foreach ( \array_diff_key( $patterns, \array_flip( [ 'exe_file_uploads' ] ) ) as $group ) {
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