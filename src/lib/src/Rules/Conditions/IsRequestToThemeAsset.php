<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestPath;

class IsRequestToThemeAsset extends Base {

	use RequestPath;

	const SLUG = 'is_request_to_theme_asset';

	protected function execConditionCheck() :bool {
		$pathMatcher = ( new MatchRequestPath() )->setCon( $this->getCon() );
		$pathMatcher->request_path = $this->getRequestPath();
		$pathMatcher->is_match_regex = true;
		$pathMatcher->match_paths = [
			sprintf( '^%s/.+/.+', dirname( wp_parse_url( get_stylesheet_directory_uri(), PHP_URL_PATH ) ) )
		];
		return $pathMatcher->run();
	}

	public static function RequiredConditions() :array {
		return [
			MatchRequestPath::class
		];
	}
}