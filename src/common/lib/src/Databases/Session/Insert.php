<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends Base\Insert {

	/**
	 * @param string $sSessionId
	 * @param string $sUsername
	 * @return bool
	 */
	public function create( $sSessionId, $sUsername ) {
		$oReq = Services::Request();
		$nTimeStamp = $oReq->ts();

		$aData = array(
			'session_id'              => $sSessionId,
			'ip'                      => Services::IP()->getRequestIp(), // TODO: SHA1
			'browser'                 => md5( $oReq->getUserAgent() ),
			'wp_username'             => $sUsername,
			'logged_in_at'            => $nTimeStamp,
			'created_at'              => $nTimeStamp,
			'last_activity_at'        => $nTimeStamp,
			'last_activity_uri'       => $oReq->server( 'REQUEST_URI' ),
			'login_intent_expires_at' => 0,
			'secadmin_at'             => 0,
		);
		return $this->setInsertData( $aData )->query() === 1;
	}
}