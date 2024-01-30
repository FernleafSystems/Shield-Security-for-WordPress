<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class PhpDie extends Base {

	public const SLUG = 'php_die';

	public function execResponse() :void {
		die( $this->p->message );
	}

	public function getParamsDef() :array {
		return [
			'message' => [
				'type'    => EnumParameters::TYPE_STRING,
				'default' => '',
				'label'   => __( 'User Display Message', 'wp-simple-firewall' ),
			],
		];
	}
}