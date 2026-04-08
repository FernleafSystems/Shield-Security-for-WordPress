<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\GetRequestMeta;

trait HandlesRequestMetaTableAction {

	/**
	 * @throws \Exception
	 */
	protected function getRequestMeta() :array {
		return [
			'success' => true,
			'html'    => ( new GetRequestMeta() )->retrieve( (string)( $this->action_data[ 'rid' ] ?? '' ) ),
		];
	}
}
