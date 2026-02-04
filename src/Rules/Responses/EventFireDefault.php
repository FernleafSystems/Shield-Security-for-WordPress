<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class EventFireDefault extends Base {

	public const SLUG = 'event_fire_default';

	public function execResponse() :void {
		self::con()->fireEvent( 'shield/rules/response/'.$this->p->rule_slug );
	}

	public function getParamsDef() :array {
		return [
			'rule_slug' => [
				'type'    => EnumParameters::TYPE_STRING,
				'label'   => __( 'Rule Slug', 'wp-simple-firewall' ),
				'default' => '',
				'hidden'  => true,
			],
		];
	}
}