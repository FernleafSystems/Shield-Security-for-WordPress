<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\ScriptNamesToMatchUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;

/**
 * @property string $match_type
 * @property string $match_script_name
 */
class MatchRequestScriptName extends Base {

	use Traits\RequestScriptName;
	use Traits\TypeRequest;

	public const SLUG = 'match_request_script_name';

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_script_name ) ) {
			throw new ScriptNamesToMatchUnavailableException();
		}

		$scriptName = $this->getRequestScriptName();
		// always add this in-case we need to invert_match
		$this->addConditionTriggerMeta( 'matched_script_name', $scriptName );
		return ( new PerformConditionMatch( $scriptName, $this->match_script_name, $this->match_type ) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the request script name match the given name.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_type'        => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForStrings(),
				'default'   => EnumMatchTypes::MATCH_TYPE_REGEX,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_script_name' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Script Name To Match', 'wp-simple-firewall' ),
			],
		];
	}
}