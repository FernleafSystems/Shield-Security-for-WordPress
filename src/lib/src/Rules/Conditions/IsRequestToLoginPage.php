<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Services\Services;

class IsRequestToLoginPage extends Base {

	use Traits\TypeWordpress;

	public function getDescription() :string {
		return __( 'Is Request To The Login Page.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		$loginPath = (string)wp_parse_url( Services::WpGeneral()->getLoginUrl(), \PHP_URL_PATH );
		return [
			'conditions' => MatchRequestPath::class,
			'params'     => [
				'match_type' => EnumMatchTypes::MATCH_TYPE_REGEX,
				'match_path' => sprintf( '#^/%s$#', \trim( $loginPath, '/' ) ),
			],
		];
	}
}