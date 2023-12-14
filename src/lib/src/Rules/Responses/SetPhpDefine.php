<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

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
				'type'         => 'string',
				'label'        => __( 'PHP Define Name', 'wp-simple-firewall' ),
				'verify_regex' => '/^[a-z_]+[0-9a-z_]*$/'
			],
			'define_value' => [
				'type'  => 'scalar',
				'label' => __( 'PHP Define Value', 'wp-simple-firewall' ),
			],
		];
	}
}