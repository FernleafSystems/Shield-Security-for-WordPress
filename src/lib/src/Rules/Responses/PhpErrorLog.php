<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class PhpErrorLog extends Base {

	public const SLUG = 'php_error_log';

	public function execResponse() :void {
		\error_log( $this->p->message );
	}

	public function getParamsDef() :array {
		return [
			'message' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Error Log Message', 'wp-simple-firewall' ),
			],
		];
	}
}