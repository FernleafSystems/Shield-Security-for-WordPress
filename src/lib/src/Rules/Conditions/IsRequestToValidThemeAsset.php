<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Services\Services;

class IsRequestToValidThemeAsset extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'is_request_to_valid_theme_asset';

	public function getDescription() :string {
		return __( 'Is the request to a path within a currently installed WordPress theme.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'conditions' => MatchRequestPath::class,
			'params'     => [
				'match_type' => EnumMatchTypes::MATCH_TYPE_REGEX,
				'match_path' => sprintf( '#^%s/(%s)/#',
					\rtrim( \dirname( wp_parse_url( get_stylesheet_directory_uri(), \PHP_URL_PATH ) ), '/' ),
					\implode( '|', \array_filter( \array_map(
						function ( $themeDir ) {
							return \preg_quote( $themeDir, '#' );
						},
						Services::WpThemes()->getInstalledStylesheets()
					) ) )
				),
			],
		];
	}
}