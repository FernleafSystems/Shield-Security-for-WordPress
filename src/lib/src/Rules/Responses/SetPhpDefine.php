<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class SetPhpDefine extends Base {

	public const SLUG = 'set_php_define';

	public function execResponse() :bool {
		if ( !\defined( $this->params[ 'define_name' ] ) ) {
			\define( $this->params[ 'define_name' ], $this->params[ 'define_value' ] );
		}
		return true;
	}

	public function getParamsDef() :array {
		return [
			'define_name'  => [
				'type'         => EnumParameters::TYPE_STRING,
				'label'        => __( 'PHP Define Name', 'wp-simple-firewall' ),
				'verify_regex' => '/^[a-z_]+[0-9a-z_]*$/'
			],
			'define_value' => [
				'type'  => EnumParameters::TYPE_SCALAR,
				'label' => __( 'PHP Define Value', 'wp-simple-firewall' ),
			],
		];
	}
}