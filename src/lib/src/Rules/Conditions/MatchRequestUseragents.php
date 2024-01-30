<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

/**
 * @deprecated 18.6
 */
class MatchRequestUseragents extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_useragents';

	public function getDescription() :string {
		return __( 'Does the request useragent match the given useragents.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_OR,
			'conditions' => \array_map(
				function ( $agent ) {
					return [
						'conditions' => MatchRequestUseragent::class,
						'params'     => [
							'match_type'      => '',
							'match_useragent' => $agent,
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