<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends Base\Insert {

	public function create( string $ID, string $username ) :bool {
		$aData = [
			'session_id'  => $ID,
			'wp_username' => $username,
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

		$req = Services::Request();
		$aData = array_merge(
			[
				'browser'                 => md5( $req->getUserAgent() ),
				'logged_in_at'            => $req->ts(),
				'last_activity_at'        => $req->ts(),
				'last_activity_uri'       => $req->getUri(),
				'login_intent_expires_at' => 0,
				'secadmin_at'             => 0,
			],
			$aData
		);

		return $this->setInsertData( $aData );
	}
}