<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

/**
 * @property string   $match_type
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
							'match_path' => $path,
							'match_type' => $this->match_type,
						],
					];
				},
				$this->match_paths
			),
		];
	}

	public function getParamsDef() :array {
		return [
			'match_type'     => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForStrings(),
				'default'   => EnumMatchTypes::MATCH_TYPE_REGEX,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_paths'    => [
				'type'  => EnumParameters::TYPE_ARRAY,
				'label' => __( 'Match Paths', 'wp-simple-firewall' ),
			],
		];
	}
}