<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsRequestToPluginAsset extends Base {

	public const SLUG = 'is_request_to_plugin_asset';

	protected function getSubConditions() :array {
		return [
			'conditions' => MatchRequestPath::class,
			'params'     => [
				'is_match_regex' => true,
				'match_paths'    => [
					sprintf( '^%s/.+/.+', \rtrim( wp_parse_url( plugins_url(), \PHP_URL_PATH ), '/' ) )
				],
			],
		];
	}
}