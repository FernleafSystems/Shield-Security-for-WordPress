<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

class EventFire extends Base {

	const SLUG = 'event_fire';

	protected function execResponse() :bool {
//		$this->getCon()->fireEvent( 'shield/rules/response/'.$this->rule->slug );
		$params = $this->responseParams;
		$event = $params[ 'event' ] ?? '';
		if ( !empty( $event ) ) {
			unset( $params[ 'event' ] );
			// A FREAKING MESS!
			$params[ 'audit_params' ] = $this->getConsolidatedConditionMeta();
			if ( isset( $params[ 'audit_params' ][ 'offense_count' ] ) ) {
				$params[ 'offense_count' ] = $params[ 'audit_params' ][ 'offense_count' ];
				unset( $params[ 'audit_params' ][ 'offense_count' ] );
			}
			$this->getCon()->fireEvent( $event, $params );
		}

		return true;
	}
}