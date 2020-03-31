<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends Base\Insert {

	/**
	 * @param string $sSessionId
	 * @param string $sUsername
	 * @return bool
	 */
	public function create( $sSessionId, $sUsername ) {
		$aData = [
			'session_id'  => $sSessionId,
			'wp_username' => $sUsername,
			'ip'          => Services::IP()->getRequestIp()
		];
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

		$oIP = Services::IP();
		if ( empty( $aData[ 'ip' ] ) || !$oIP->isValidIp( $aData[ 'ip' ] ) ) {
			$sReqIP = $oIP->getRequestIp();
			$aData[ 'ip' ] = $oIP->isValidIp( $sReqIP ) ? $sReqIP : '';
		}

		$oReq = Services::Request();
		$aData = array_merge(
			[
				'browser'                 => md5( $oReq->getUserAgent() ),
				'logged_in_at'            => $oReq->ts(),
				'last_activity_at'        => $oReq->ts(),
				'last_activity_uri'       => $oReq->getRequestUri(),
				'login_intent_expires_at' => 0,
				'secadmin_at'             => 0,
			],
			$aData
		);

		return $this->setInsertData( $aData );
	}
}