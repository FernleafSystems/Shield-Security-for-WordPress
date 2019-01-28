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
		$aData = array(
			'session_id'  => $sSessionId,
			'wp_username' => $sUsername,
		);
		return $this->setInsertData( $aData )->query() === 1;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function verifyInsertData() {
		parent::verifyInsertData();

		$aData = $this->getInsertData();
		if ( empty( $aData[ 'session_id' ] ) ) {
			throw new \Exception( 'Session ID not provided.' );
		}
		if ( empty( $aData[ 'wp_username' ] ) ) {
			throw new \Exception( 'WP Username not provided' );
		}

		$oReq = Services::Request();
		$nTimeStamp = $oReq->ts();

		$aData = array_merge(
			[
				'browser'                 => md5( $oReq->getUserAgent() ),
				'ip'                      => Services::IP()->getRequestIp(), // TODO: SHA1
				'logged_in_at'            => $nTimeStamp,
				'last_activity_at'        => $nTimeStamp,
				'last_activity_uri'       => $oReq->server( 'REQUEST_URI' ),
				'login_intent_expires_at' => 0,
				'secadmin_at'             => 0,
			],
			$aData
		);

		return $this->setInsertData( $aData );
	}
}