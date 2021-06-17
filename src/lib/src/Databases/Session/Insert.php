<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends Base\Insert {

	public function create( string $ID, string $username ) :bool {
		return $this->setInsertData( [
				'session_id'  => $ID,
				'wp_username' => $username,
				'ip'          => Services::IP()->getRequestIp()
			] )->query() === 1;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function verifyInsertData() {
		parent::verifyInsertData();

		$data = $this->getInsertData();
		if ( empty( $data[ 'session_id' ] ) ) {
			throw new \Exception( 'Session ID not provided.' );
		}
		if ( empty( $data[ 'wp_username' ] ) ) {
			throw new \Exception( 'WP Username not provided' );
		}

		$srvIP = Services::IP();
		if ( empty( $data[ 'ip' ] ) || !$srvIP->isValidIp( $data[ 'ip' ] ) ) {
			$reqIP = $srvIP->getRequestIp();
			$data[ 'ip' ] = $srvIP->isValidIp( $reqIP ) ? $reqIP: '';
		}

		$req = Services::Request();
		return $this->setInsertData( array_merge(
			[
				'browser'                 => md5( $req->getUserAgent() ),
				'logged_in_at'            => $req->ts(),
				'last_activity_at'        => $req->ts(),
				'last_activity_uri'       => $req->getUri(),
				'secadmin_at'             => 0,
			],
			$data
		) );
	}
}