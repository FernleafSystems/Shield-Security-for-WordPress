<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class HookRemoveAction extends Base {

	public const SLUG = 'hook_remove_action';

	public function execResponse() :void {
		remove_action(
			$this->p->hook,
			$this->p->callback,
			$this->p->priority
		);
	}

	public function getParamsDef() :array {
		return [
			'hook'     => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Hook Name', 'wp-simple-firewall' ),
			],
			'callback' => [
				'type'  => EnumParameters::TYPE_CALLBACK,
				'label' => __( 'Callback', 'wp-simple-firewall' ),
			],
			'priority' => [
				'type'    => EnumParameters::TYPE_INT,
				'default' => 10,
				'label'   => __( 'Priority', 'wp-simple-firewall' ),
			],
		];
	}
}