<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class EventFire extends Base {

	const SLUG = 'event_fire';

	protected function execResponse() :bool {
		$params = $this->responseParams;
		$event = $params[ 'event' ] ?? '';
		if ( !empty( $event ) ) {
			unset( $params[ 'event' ] );
			$params[ 'audit_params' ] = $this->getConsolidatedConditionMeta();
			$this->getCon()->fireEvent( $event, $params );
		}

		$this->getCon()->fireEvent( 'shield/rules/response/'.$this->rule->slug );

		return true;
	}
}