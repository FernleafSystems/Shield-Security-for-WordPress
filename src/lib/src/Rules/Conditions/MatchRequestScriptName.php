<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\ScriptNamesToMatchUnavailableException;

/**
 * @property bool   $is_match_regex
 * @property string $match_script_name
 */
class MatchRequestScriptName extends Base {

	use Traits\RequestScriptName;
	use Traits\TypeRequest;

	public const SLUG = 'match_request_script_name';

	public function getDescription() :string {
		return __( 'Does the request script name match the given name.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_script_name ) ) {
			throw new ScriptNamesToMatchUnavailableException();
		}

		$scriptName = $this->getRequestScriptName();

		// always add this in-case we need to invert_match
		$this->addConditionTriggerMeta( 'matched_script_name', $scriptName );

		return $this->is_match_regex ?
			(bool)\preg_match( sprintf( '#%s#i', \preg_quote( $this->match_script_name, '#' ) ), $scriptName )
			: $scriptName === $this->match_script_name;
	}

	public function getParamsDef() :array {
		return [
			'match_script_name' => [
				'type'  => 'string',
				'label' => __( 'Script Name To Match', 'wp-simple-firewall' ),
			],
			'is_match_regex'    => [
				'type'    => 'bool',
				'label'   => __( 'Is Match Regex', 'wp-simple-firewall' ),
				'default' => true,
			],
		];
	}
}