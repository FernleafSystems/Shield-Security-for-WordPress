<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestPath;
use FernleafSystems\Wordpress\Services\Services;

class IsRequestToValidThemeAsset extends Base {

	use RequestPath;

	const SLUG = 'is_request_to_valid_theme_asset';

	protected function execConditionCheck() :bool {
		$pathMatcher = ( new MatchRequestPath() )->setCon( $this->getCon() );
		$pathMatcher->request_path = $this->getRequestPath();
		$pathMatcher->is_match_regex = true;
		$pathMatcher->match_paths = [
			sprintf( '^%s/(%s)/',
				rtrim( dirname( wp_parse_url( get_stylesheet_directory_uri(), PHP_URL_PATH ) ), '/' ),
				implode( '|', array_filter( array_map(
					function ( $themeDir ) {
						return preg_quote( $themeDir, '#' );
					},
					Services::WpThemes()->getInstalledStylesheets()
				) ) )
			)
		];
		return $pathMatcher->run();
	}

	public static function RequiredConditions() :array {
		return [
			MatchRequestPath::SLUG
		];
	}
}