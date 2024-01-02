<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

/**
 * @deprecated 18.6
 */
class MatchRequestScriptNames extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_script_names';

	public function getDescription() :string {
		return __( 'Does the request script name match the given set of names.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_OR,
			'conditions' => \array_map(
				function ( $name ) {
					return [
						'conditions' => MatchRequestScriptName::class,
						'params'     => [
							'match_type'        => '',
							'match_script_name' => $name,
						],
					];
				},
				[]
			),
		];
	}

	public function getParamsDef() :array {
		return [];
	}
}