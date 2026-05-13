<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\GetRequestMeta;

trait HandlesRequestMetaTableAction {

	/**
	 * @throws \Exception
	 */
	protected function getRequestMeta() :array {
		$requestMeta = new GetRequestMeta();
		$rid = (string)( $this->action_data[ 'rid' ] ?? '' );

		return [
			'success'      => true,
			'html'         => $requestMeta->retrieve( $rid ),
			'request_meta' => $requestMeta->retrieveContract( $rid ),
		];
	}
}
