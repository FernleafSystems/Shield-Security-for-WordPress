<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class EventFireDefault extends Base {

	public const SLUG = 'event_fire_default';

	public function execResponse() :void {
		self::con()->fireEvent( 'shield/rules/response/'.$this->params[ 'rule_slug' ] );
	}
}