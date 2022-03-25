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

	const SLUG = 'match_request_script_name';

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_script_names ) ) {
			throw new ScriptNamesToMatchUnavailableException();
		}
		$matched = false;
		$scriptName = $this->getRequestScriptName();
		foreach ( $this->match_script_names as $matchPath ) {
			if ( $this->is_match_regex ) {
				$matched = (bool)preg_match( sprintf( '#%s#i', $matchPath ), $scriptName );
				if ( $matched ) {
					$this->addConditionTriggerMeta( 'matched_script_name', $matchPath );
					break;
				}
			}
			else {
				$matched = stripos( $scriptName, $scriptName ) !== false;
			}
		}
		return $matched;
	}
}