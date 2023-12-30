<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class PhpErrorLog extends Base {

	public const SLUG = 'php_error_log';

	public function execResponse() :void {
		\error_log( $this->params[ 'log' ] );
	}

	public function getParamsDef() :array {
		return [
			'log' => [
				'type'    => EnumParameters::TYPE_STRING,
				'label'   => __( 'Error Log Message', 'wp-simple-firewall' ),
			],
		];
	}
}