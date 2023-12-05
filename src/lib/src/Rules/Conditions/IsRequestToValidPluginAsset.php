<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class IsRequestToValidPluginAsset extends Base {

	public const SLUG = 'is_request_to_valid_plugin_asset';

	protected function getSubConditions() :array {
		return [
			'conditions' => MatchRequestPath::class,
			'params'     => [
				'is_match_regex' => true,
				'match_paths'    => [
					sprintf( '^%s/(%s)/',
						\rtrim( wp_parse_url( plugins_url(), \PHP_URL_PATH ), '/' ),
						\implode( '|', \array_filter( \array_map(
							function ( $pluginFile ) {
								return \preg_quote( \dirname( (string)$pluginFile ), '#' );
							},
							Services::WpPlugins()->getInstalledPluginFiles()
						) ) )
					)
				],
			],
		];
	}
}