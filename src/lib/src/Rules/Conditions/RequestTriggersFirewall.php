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

		$patterns = $con->comps === null ? self::con()->getModule_Firewall()->opts()->getDef( 'firewall_patterns' )
			: $con->cfg->configuration->def( 'firewall_patterns' );

		$paramConditions = [];

		foreach ( $patterns as $key => $group ) {
			$isOpt = $con->comps === null ? self::con()->getModule_Firewall()->opts()->isOpt( 'block_'.$key, 'Y' )
				: $con->opts->optIs( 'block_'.$key, 'Y' );
			if ( $key !== 'exe_file_uploads' && $isOpt ) {
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