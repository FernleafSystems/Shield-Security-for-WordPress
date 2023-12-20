<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class HookAddFilter extends Base {

	public const SLUG = 'hook_add_filter';

	public function execResponse() :bool {
		add_filter(
			$this->params[ 'hook' ],
			$this->params[ 'callback' ],
			$this->params[ 'priority' ],
			$this->params[ 'args' ]
		);
		return true;
	}

	public function getParamsDef() :array {
		return [
			'hook'     => [
				'type' => EnumParameters::TYPE_STRING,
				'label' => __( 'Hook Name', 'wp-simple-firewall' ),
			],
			'callback' => [
				'type' => EnumParameters::TYPE_CALLBACK,
				'label' => __( 'Callback', 'wp-simple-firewall' ),
			],
			'priority' => [
				'type'    => EnumParameters::TYPE_INT,
				'default' => 10,
				'label' => __( 'Priority', 'wp-simple-firewall' ),
			],
			'args'     => [
				'type'    => EnumParameters::TYPE_INT,
				'default' => 1,
				'label' => __( 'Number of Arguments', 'wp-simple-firewall' ),
			],
		];
	}
}