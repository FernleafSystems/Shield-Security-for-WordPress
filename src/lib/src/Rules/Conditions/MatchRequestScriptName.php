<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestScriptName;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\ScriptNamesToMatchUnavailableException;

/**
 * @property bool     $is_match_regex
 * @property string[] $match_script_names
 */
class MatchRequestScriptName extends Base {

	use RequestScriptName;

	public const SLUG = 'match_request_script_name';

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_script_names ) ) {
			throw new ScriptNamesToMatchUnavailableException();
		}

		$matched = false;
		$scriptName = $this->getRequestScriptName();

		if ( $this->is_match_regex ) {
			foreach ( $this->match_script_names as $matchScriptName ) {
				if ( preg_match( sprintf( '#%s#i', preg_quote( $matchScriptName, '#' ) ), $scriptName ) ) {
					$matched = true;
					break;
				}
			}
		}
		else {
			$matched = \in_array( $scriptName, $this->match_script_names );
		}

		// always add this in-case we need to invert_match
		$this->addConditionTriggerMeta( 'matched_script_name', $scriptName );
		return $matched;
	}
}