<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestPath;
use FernleafSystems\Wordpress\Services\Services;

class IsRequestToValidPluginAsset extends Base {

	use RequestPath;

	public const SLUG = 'is_request_to_valid_plugin_asset';

	protected function execConditionCheck() :bool {
		$pathMatcher = new MatchRequestPath();
		$pathMatcher->request_path = $this->getRequestPath();
		$pathMatcher->is_match_regex = true;
		$pathMatcher->match_paths = [
			sprintf( '^%s/(%s)/',
				rtrim( wp_parse_url( plugins_url(), PHP_URL_PATH ), '/' ),
				\implode( '|', \array_filter( \array_map(
					function ( $pluginFile ) {
						return \preg_quote( \dirname( (string)$pluginFile ), '#' );
					},
					Services::WpPlugins()->getInstalledPluginFiles()
				) ) )
			)
		];
		return $pathMatcher->run();
	}

	public static function RequiredConditions() :array {
		return [
			MatchRequestPath::class
		];
	}
}