<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class PhpSetDefine extends Base {

	public const SLUG = 'php_set_define';

	public function execResponse() :void {
		if ( !\defined( $this->p->name ) ) {
			\define( $this->p->name, $this->p->value );
		}
	}

	public function getParamsDef() :array {
		return [
			'name'  => [
				'type'         => EnumParameters::TYPE_STRING,
				'label'        => __( 'PHP Define Name', 'wp-simple-firewall' ),
				'verify_regex' => '/^[A-Za-z_]+[0-9a-z_]*$/'
			],
			'value' => [
				'type'  => EnumParameters::TYPE_SCALAR,
				'label' => __( 'PHP Define Value', 'wp-simple-firewall' ),
			],
		];
	}
}