<?php

class ICWP_WPSF_Processor_HackProtect_Integrity extends ICWP_WPSF_Processor_BaseWpsf {

	use \FernleafSystems\Wordpress\Plugin\Shield\Crons\StandardCron;

	/**
	 */
	public function run() {
		parent::run();
		$this->setupSnapshots();

		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isIcUsersEnabled() ) {
			add_action( 'user_register', array( $this, 'snapshotUsers' ) );
			add_action( 'profile_update', array( $this, 'snapshotUsers' ) );
			add_action( 'after_password_reset', array( $this, 'snapshotUsers' ) );
		}
	}

	/**
	 * @return array[] - associative arrays where keys are $this->getStandardUserFields()
	 */
	public function getSnapshotUsers() {
		return is_array( $this->getOption( 'snapshot_users' ) ) ? $this->getOption( 'snapshot_users' ) : array();
	}

	/**
	 * @return array
	 */
	public function getStandardUserFields() {
		return array( 'user_login', 'user_email', 'user_pass' );
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
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		if ( !$oFO->isIcUsersEnabled() ) {
			return;
		}

		$aSnapshot = $this->getSnapshotUsers();
		$aFieldsToCheck = $this->getStandardUserFields();

		$aUsers = $this->loadWpUsers()->getAllUsers();
		foreach ( $aUsers as $oUser ) {

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
		$oDb = $this->loadDbProcessor();
		return $oDb->deleteRowsFromTableWhere(
				$oDb->getTable_Users(),
				array( 'ID' => $nId )
			) > 0;
	}

	/**
	 * @param int $nId
	 * @return bool
	 */
	public function resetUserToSnapshot( $nId ) {
		$aSnapshot = $this->getSnapshotUsers();
		$aUser = $aSnapshot[ $nId ];

		$oDb = $this->loadDbProcessor();
		return $oDb->updateRowsFromTableWhere(
				$oDb->getTable_Users(),
				$aUser,
				array( 'ID' => $nId )
			) > 0;
	}

	/**
	 * Guarded: Only ever snapshots when option is enabled.
	 *
	 * @param bool $bUpdate
	 * @return $this
	 */
	public function snapshotUsers( $bUpdate = false ) {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isIcUsersEnabled() && ( $bUpdate || !$this->hasSnapshotUsers() ) ) {

			$aUsersToStore = array();
			$aFields = $this->getStandardUserFields();
			foreach ( $this->loadWpUsers()->getAllUsers() as $oUser ) {

				$aUserData = array();
				foreach ( $aFields as $sField ) {
					$aUserData[ $sField ] = $oUser->get( $sField );
				}
				$aUsersToStore[ $oUser->ID ] = $aUserData;
			}
			$oFO->setIcSnapshotUsers( $aUsersToStore );
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
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->getScanFrequency();
	}

	/**
	 * @return int
	 */
	protected function getCronName() {
		/** @var ICWP_WPSF_FeatureHandler_HackProtect $oFO */
		$oFO = $this->getMod();
		return $oFO->getIcCronName();
	}
}