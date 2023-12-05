<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\WildCardOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class RequestIsPathWhitelisted extends Base {

	use ModConsumer;

	public const SLUG = 'request_is_path_whitelisted';

	protected function getSubConditions() :array {
		return [
			'conditions' => MatchRequestPath::class,
			'params'    => [
				'is_match_regex' => true,
				'match_paths'    => $this->buildPaths(),
			]
		];
	}

	private function buildPaths() :array {
		$homeUrlPath = (string)wp_parse_url( Services::WpGeneral()->getHomeUrl(), \PHP_URL_PATH );
		if ( empty( $homeUrlPath ) ) {
			$homeUrlPath = '/';
		}
		return \array_map(
			function ( $value ) use ( $homeUrlPath ) {
				$regEx = ( new WildCardOptions() )->buildFullRegexValue( $value, WildCardOptions::URL_PATH, false );
				if ( \strpos( $regEx, $homeUrlPath ) !== 0 ) {
					$regEx = '/'.\ltrim( \rtrim( $homeUrlPath, '/' ).'/'.\ltrim( $regEx, '/' ), '/' );
				}
				return '^'.$regEx;
			},
			self::con()->isPremiumActive() ? $this->opts()->getOpt( 'request_whitelist', [] ) : []
		);
	}
}