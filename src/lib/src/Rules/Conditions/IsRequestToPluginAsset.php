<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsRequestToPluginAsset extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'is_request_to_plugin_asset';

	public function getDescription() :string {
		return __( 'Is the request to a path within a potentially installed WordPress plugin.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'conditions' => MatchRequestPath::class,
			'params'     => [
				'is_match_regex' => true,
				'match_path'     => sprintf( '^%s/.+/.+', \rtrim( wp_parse_url( plugins_url(), \PHP_URL_PATH ), '/' ) ),
			],
		];
	}
}