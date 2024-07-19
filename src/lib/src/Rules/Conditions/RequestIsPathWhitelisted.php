<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\WildCardOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum
};
use FernleafSystems\Wordpress\Services\Services;

class RequestIsPathWhitelisted extends Base {

	use PluginControllerConsumer;
	use Traits\TypeShield;

	public const SLUG = 'request_is_path_whitelisted';

	public function getDescription() :string {
		return __( 'Is the request path whitelisted by Shield.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_OR,
			'conditions' => \array_map(
				function ( string $path ) {
					return [
						'conditions' => Conditions\MatchRequestPath::class,
						'params'     => [
							'match_type' => Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
							'match_path' => $path,
						],
					];
				},
				$this->buildPaths()
			),
		];
	}

	private function buildPaths() :array {
		$homeUrlPath = wp_parse_url( Services::WpGeneral()->getHomeUrl(), \PHP_URL_PATH );
		if ( empty( $homeUrlPath ) ) {
			$homeUrlPath = '/';
		}
		return \array_map(
			function ( $value ) use ( $homeUrlPath ) {
				$regEx = ( new WildCardOptions() )->buildFullRegexValue( $value, WildCardOptions::URL_PATH, false );
				if ( \strpos( $regEx, $homeUrlPath ) !== 0 ) {
					$regEx = '/'.\ltrim( \rtrim( $homeUrlPath, '/' ).'/'.\ltrim( $regEx, '/' ), '/' );
				}
				return '#^'.$regEx.'#i';
			},
			self::con()->isPremiumActive() ? self::con()->opts->optGet( 'request_whitelist' ) : []
		);
	}
}