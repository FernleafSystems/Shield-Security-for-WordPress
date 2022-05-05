<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\WildCardOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Build\BuildRuleCoreShieldBase,
	Conditions
};
use FernleafSystems\Wordpress\Services\Services;

class IsPathWhitelisted extends BuildRuleCoreShieldBase {

	const SLUG = 'shield/is_path_whitelisted';

	protected function getName() :string {
		return 'Is Path Whitelisted';
	}

	protected function getDescription() :string {
		return 'Test whether the current Request Path is whitelisted.';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_AND,
			'group' => [
				[
					'condition' => Conditions\MatchRequestPath::SLUG,
					'params'    => [
						'is_match_regex' => true,
						'match_paths'    => $this->buildPaths(),
					]
				],
			]
		];
	}

	private function buildPaths() :array {
		$homeUrlPath = (string)wp_parse_url( Services::WpGeneral()->getHomeUrl(), PHP_URL_PATH );
		if ( empty( $homeUrlPath ) ) {
			$homeUrlPath = '/';
		}
		return array_map(
			function ( $value ) use ( $homeUrlPath ) {
				$regEx = ( new WildCardOptions() )->buildFullRegexValue( $value, WildCardOptions::URL_PATH, false );
				if ( strpos( $regEx, $homeUrlPath ) !== 0 ) {
					$regEx = '/'.ltrim( rtrim( $homeUrlPath, '/' ).'/'.ltrim( $regEx, '/' ), '/' );
				}
				return '^'.$regEx;
			},
			$this->getCon()->isPremiumActive() ? $this->getOptions()->getOpt( 'request_whitelist', [] ) : []
		);
	}
}