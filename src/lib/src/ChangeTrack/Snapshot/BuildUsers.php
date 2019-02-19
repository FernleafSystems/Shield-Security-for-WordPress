<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class BuildUsers
 * @package FernleafSystems\Wordpress\Plugin\Shield\ChangeTrack\Snapshot
 */
class BuildUsers {

	/**
	 * @uses 2 SQL queries
	 * @return array[] - key is user ID, values are arrays with keys: id, user_login, user_pass, user_email, is_admin
	 */
	public function run() {
		$aAdminIds = array_keys( $this->getAdmins() );
		return array_map(
			function ( $aUser ) use ( $aAdminIds ) {
				$aUser[ 'is_admin' ] = in_array( $aUser[ 'id' ], $aAdminIds );
				return $aUser;
			},
			$this->getUsers()
		);
	}

	/**
	 * @return array
	 */
	private function getAdmins() {
		return $this->getUsers( [ 'role' => 'administrator' ] );
	}

	/**
	 * @param array $aParams
	 * @return array[]
	 */
	private function getUsers( $aParams = [] ) {
		$aParams = Services::DataManipulation()->mergeArraysRecursive(
			[
				'fields' => $this->getFields(),
				'number' => 1,
				'paged'  => 1,
			],
			$aParams
		);

		$aActual = [];
		do {
			$aUserResult = Services::WpUsers()->getAllUsers( $aParams );
			foreach ( $aUserResult as $oUser ) {
				/** @var \stdClass $oUser */
				$aActual[ $oUser->id ] = [
					'id'         => $oUser->id,
					'user_pass'  => sha1( $oUser->user_pass ),
					'user_email' => sha1( $oUser->user_email ),
				];
			}
			$aParams[ 'paged' ]++;
		} while ( !empty( $aUserResult ) );

		return $aActual;
	}

	/**
	 * @return array
	 */
	private function getFields() {
		return [
			'id',
			'user_pass',
			'user_email',
		];
	}
}
