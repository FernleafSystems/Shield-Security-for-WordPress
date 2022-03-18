<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\PathsToMatchUnavailableException;

/**
 * @property bool     $is_match_regex
 * @property string[] $match_paths
 */
class MatchRequestPath extends Base {

	use Traits\RequestPath;

	const CONDITION_SLUG = 'match_request_path';

	protected function execConditionCheck() :bool {
		return $this->matchRequestPath();
	}

	/**
	 * @throws PathsToMatchUnavailableException
	 */
	protected function matchRequestPath() :bool {
		if ( empty( $this->match_paths ) ) {
			throw new PathsToMatchUnavailableException();
		}
		$matched = false;
		$path = $this->getRequestPath();
		foreach ( $this->match_paths as $matchPath ) {
			if ( $this->is_match_regex ) {
				$matched = (bool)preg_match( sprintf( '#%s#i', $matchPath ), $path );
				if ( $matched ) {
					$this->addConditionTriggerMeta( 'matched_path', $matchPath );
					break;
				}
			}
		}
		return $matched;
	}
}