<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class EventFireDefault extends Base {

	public const SLUG = 'event_fire_default';

	public function execResponse() :bool {
		self::con()->fireEvent( 'shield/rules/response/'.$this->responseParams[ 'rule_slug' ] );
		return true;
	}
}