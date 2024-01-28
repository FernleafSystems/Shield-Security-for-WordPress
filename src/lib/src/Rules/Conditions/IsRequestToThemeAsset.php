<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;

class IsRequestToThemeAsset extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'is_request_to_theme_asset';

	public function getDescription() :string {
		return __( 'Is the request to a path within a potentially installed WordPress theme.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'conditions' => MatchRequestPath::class,
			'params'     => [
				'match_type' => EnumMatchTypes::MATCH_TYPE_REGEX,
				'match_path' => sprintf( '#^%s/.+/.+#', \dirname( (string)wp_parse_url( get_stylesheet_directory_uri(), \PHP_URL_PATH ) ) ),
			],
		];
	}
}