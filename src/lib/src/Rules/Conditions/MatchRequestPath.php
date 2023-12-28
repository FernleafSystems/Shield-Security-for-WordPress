<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\PathsToMatchUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;

/**
 * @property string $match_type
 * @property string $match_path
 */
class MatchRequestPath extends Base {

	use Traits\RequestPath;
	use Traits\TypeRequest;

	public const SLUG = 'match_request_path';

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_path ) ) {
			throw new PathsToMatchUnavailableException();
		}

		$path = $this->getRequestPath();
		$this->addConditionTriggerMeta( 'matched_path', $path );

		return ( new PerformConditionMatch($path, $this->match_path,$this->match_type ) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the request path match the given path.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_type' => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForStrings(),
				'default'   => EnumMatchTypes::MATCH_TYPE_REGEX,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_path' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Path To Match', 'wp-simple-firewall' ),
			],
		];
	}
}