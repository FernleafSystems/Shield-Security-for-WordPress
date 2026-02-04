<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequestConsumer;

/**
 * Try to fill missing audit parameters data from the Request object if it's missing.
 */
class FillEventAuditParamsFromRequest {

	use PluginControllerConsumer;
	use ThisRequestConsumer;

	public function run( string $eventKey, array $params = [] ) :array {
		$eventDef = self::con()->comps->events->getEventDef( $eventKey );
		if ( !empty( $eventDef ) ) {
			$map = $this->requestToParamsMap();
			foreach ( \array_diff( $eventDef[ 'audit_params' ] ?? [], \array_keys( $params[ 'audit_params' ] ?? [] ) ) as $paramKey ) {
				$params[ 'audit_params' ][ $paramKey ] = $map[ $paramKey ] ?? null;
			}
		}
		return $params;
	}

	private function requestToParamsMap() :array {
		$req = $this->req ?? self::con()->this_req;
		return [
			'crawler' => $req->useragent,
			'path'    => $req->path,
		];
	}
}