<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class WpDie extends Base {

	use Traits\IsTerminating;

	public const SLUG = 'wp_die';

	public function execResponse() :void {
		wp_die( $this->p->message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Message an administrator wrote for the rule, which may be HTML; wp_die() serves it per response type, and its JSON and XML handlers expect it unescaped.
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