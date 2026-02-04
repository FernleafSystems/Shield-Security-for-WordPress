<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class PhpSendHttpHeader extends Base {

	public const SLUG = 'php_send_http_header';

	public function execResponse() :void {
		\header( $this->p->header );
	}

	public function getParamsDef() :array {
		return [
			'header' => [
				'type'         => EnumParameters::TYPE_STRING,
				'verify_regex' => '/^[a-z]+:.+$/i',
				'label'        => __( 'HTTP Response Header To Send', 'wp-simple-firewall' ),
			],
		];
	}
}