<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;

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
				'match_type' => EnumMatchTypes::MATCH_TYPE_REGEX,
				'match_path' => sprintf( '#^%s/.+/.+#', \rtrim( (string)wp_parse_url( plugins_url(), \PHP_URL_PATH ), '/' ) ),
			],
		];
	}
}