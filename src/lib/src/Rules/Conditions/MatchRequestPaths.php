<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

/**
 * @property bool     $is_match_regex
 * @property string[] $match_paths
 */
class MatchRequestPaths extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_path';

	public function getDescription() :string {
		return __( 'Does the request path match the given set of paths.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Constants::LOGIC_OR,
			'conditions' => \array_map(
				function ( $path ) {
					return [
						'conditions' => MatchRequestPath::class,
						'params'     => [
							'match_path'     => $path,
							'is_match_regex' => $this->is_match_regex
						],
					];
				},
				$this->match_paths
			),
		];
	}

	public function getParamsDef() :array {
		return [
			'match_paths'    => [
				'type'  => 'array',
				'label' => __( 'Match Paths', 'wp-simple-firewall' ),
			],
			'is_match_regex' => [
				'type'    => 'bool',
				'label'   => __( 'Is Match Regex', 'wp-simple-firewall' ),
				'default' => true,
			],
		];
	}
}