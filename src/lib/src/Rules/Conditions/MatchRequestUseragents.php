<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

/**
 * @property string[] $match_useragents
 */
class MatchRequestUseragents extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_useragents';

	public function getDescription() :string {
		return __( 'Does the request useragent match the given useragents.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Constants::LOGIC_OR,
			'conditions' => \array_map(
				function ( $agent ) {
					return [
						'conditions' => MatchRequestUseragent::class,
						'params'     => [
							'match_useragent' => $agent,
						],
					];
				},
				$this->match_useragents
			),
		];
	}

	public function getParamsDef() :array {
		return [
			'match_useragents' => [
				'type'  => 'array',
				'label' => __( 'Match Useragents', 'wp-simple-firewall' ),
			],
		];
	}
}