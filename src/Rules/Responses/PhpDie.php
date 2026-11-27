<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class PhpDie extends Base {
	use Traits\IsTerminating;

	public const SLUG = 'php_die';

	public function execResponse() :void {
		die( $this->p->message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Message an administrator wrote for the rule, which may be HTML; it also reaches plain-text clients, where escaping would show entities.
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
