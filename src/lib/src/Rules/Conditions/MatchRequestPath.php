<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\PathsToMatchUnavailableException;

/**
 * @property bool     $is_match_regex
 * @property string[] $match_paths
 */
class MatchRequestPath extends Base {

	use Traits\RequestPath;

	const SLUG = 'match_request_path';

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_paths ) ) {
			throw new PathsToMatchUnavailableException();
		}
		$matched = false;
		$path = $this->getRequestPath();
		$this->addConditionTriggerMeta( 'matched_path', $path );
		foreach ( $this->match_paths as $matchPath ) {
			$matched = $this->is_match_regex ?
				(bool)preg_match( sprintf( '#%s#i', $matchPath ), $path ) : $matchPath == $path;

			if ( $matched ) {
				break;
			}
		}
		return $matched;
	}
}