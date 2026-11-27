<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class DoAction extends Base {

	public const SLUG = 'do_action';

	public function execResponse() :void {
		do_action( $this->p->hook ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Fires the hook named in the rule's settings: a shield/ hook in Shield's own rules, or one an admin's custom rule names.
	}

	public function getParamsDef() :array {
		return [
			'hook' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Hook Name', 'wp-simple-firewall' ),
			],
		];
	}
}