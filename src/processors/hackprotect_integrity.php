<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ShieldProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_HackProtect_Integrity extends ShieldProcessor {

	public function run() {
		$this->setupSnapshots();
		add_action( 'user_register', [ $this, 'snapshotUsers' ] );
		add_action( 'profile_update', [ $this, 'snapshotUsers' ] );
		add_action( 'after_password_reset', [ $this, 'snapshotUsers' ] );
	}

	/**
	 * @return array[] - associative arrays where keys are $this->getStandardUserFields()
	 */
	public function getSnapshotUsers() {
		$aUs = $this->getOptions()->getOpt( 'snapshot_users' );
		return is_array( $aUs ) ? $aUs : [];
	}

	/**
	 * @return array
	 */
	public function getStandardUserFields() {
		return [ 'user_login', 'user_email', 'user_pass' ];
	}

	/**
	 * @return bool
	 */
	public function hasSnapshotUsers() {
		return ( count( $this->getSnapshotUsers() ) > 0 );
	}

	protected function setupSnapshots() {
		$this->snapshotUsers();
	}

	protected function verifyUsers() {
		$aSnapshot = $this->getSnapshotUsers();
		$aFieldsToCheck = $this->getStandardUserFields();

		foreach ( Services::WpUsers()->getAllUsers() as $oUser ) {

			if ( !array_key_exists( $oUser->ID, $aSnapshot ) ) {
				// Unrecognised user ID exists.
				$this->deleteUserById( $oUser->ID );
			}
			else {
				$aSnapUser = $aSnapshot[ $oUser->ID ];
				$bAltered = false;
				foreach ( $aFieldsToCheck as $sField ) {
					if ( $aSnapUser[ $sField ] != $oUser->get( $sField ) ) { //Field has been altered
						$bAltered = true;
					}
				}

				if ( $bAltered ) {
					$this->resetUserToSnapshot( $oUser->ID );
				}
			}
		}
	}

	/**
	 * @param int $nId
	 * @return bool
	 */
	public function deleteUserById( $nId ) {
		$oDb = Services::WpDb();
		return $oDb->deleteRowsFromTableWhere(
				$oDb->getTable_Users(),
				[ 'ID' => $nId ]
			) > 0;
	}

	/**
	 * @param int $nId
	 * @return bool
	 */
	public function resetUserToSnapshot( $nId ) {
		$aSnapshot = $this->getSnapshotUsers();
		$aUser = $aSnapshot[ $nId ];

		$oDb = Services::WpDb();
		return $oDb->updateRowsFromTableWhere(
				$oDb->getTable_Users(),
				$aUser,
				[ 'ID' => $nId ]
			) > 0;
	}

	/**
	 * Guarded: Only ever snapshots when option is enabled.
	 *
	 * @param bool $bUpdate
	 * @return $this
	 */
	public function snapshotUsers( $bUpdate = false ) {

		if ( $bUpdate || !$this->hasSnapshotUsers() ) {

			$aUsersToStore = [];
			$aFields = $this->getStandardUserFields();
			foreach ( Services::WpUsers()->getAllUsers() as $oUser ) {

				$aUserData = [];
				foreach ( $aFields as $sField ) {
					$aUserData[ $sField ] = $oUser->get( $sField );
				}
				$aUsersToStore[ $oUser->ID ] = $aUserData;
			}
			// store snapshot users
		}
		return $this;
	}

	/**
	 * Cron callback
	 */
	public function runCron() {
		$this->verifyUsers();
	}

	/**
	 * @return int
	 */
	protected function getCronFrequency() {
		/** @var HackGuard\Options $oOpts */
		$oOpts = $this->getOptions();
		return $oOpts->getScanFrequency();
	}
}