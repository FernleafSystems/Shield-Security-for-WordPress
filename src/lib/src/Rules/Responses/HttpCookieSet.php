<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;

class HttpCookieSet extends Base {

	public const SLUG = 'http_cookie_set';

	public function execResponse() :void {
		Services::Response()->cookieSet( $this->p->name, $this->p->value, $this->p->duration );
	}

	public function getParamsDef() :array {
		return [
			'name'     => [
				'type'         => EnumParameters::TYPE_STRING,
				'label'        => __( 'Cookie Name', 'wp-simple-firewall' ),
				'verify_regex' => '/^[0-9A-Za-z_]+$/'
			],
			'value'    => [
				'type'  => EnumParameters::TYPE_SCALAR,
				'label' => __( 'Cookie Value', 'wp-simple-firewall' ),
			],
			'duration' => [
				'type'    => EnumParameters::TYPE_INT,
				'default' => 3600,
				'label'   => sprintf( '%s (%s)', __( 'Duration', 'wp-simple-firewall' ), __( 'seconds' ) ),
			],
		];
	}
}