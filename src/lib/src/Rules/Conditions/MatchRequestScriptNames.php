<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

/**
 * @property bool     $is_match_regex
 * @property string[] $match_script_names
 */
class MatchRequestScriptNames extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_script_names';

	public function getDescription() :string {
		return __( 'Does the request script name match the given set of names.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Constants::LOGIC_OR,
			'conditions' => \array_map(
				function ( $name ) {
					return [
						'conditions' => MatchRequestScriptName::class,
						'params'     => [
							'match_script_name' => $name,
							'is_match_regex'    => $this->is_match_regex
						],
					];
				},
				$this->match_script_names
			),
		];
	}

	public function getParamsDef() :array {
		return [
			'match_script_names' => [
				'type'  => 'array',
				'label' => __( 'Match Script Names', 'wp-simple-firewall' ),
			],
			'is_match_regex'     => [
				'type'    => 'bool',
				'label'   => __( 'Is Match Regex', 'wp-simple-firewall' ),
				'default' => true,
			],
		];
	}
}