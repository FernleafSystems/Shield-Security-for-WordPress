<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Verify {

	use ModConsumer;

	/**
	 * @var bool
	 */
	private $bForceCheck;

	/**
	 */
	public function run() {
		$oCon = $this->getCon();
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();
		/** @var License\Options $oOpts */
		$oOpts = $this->getOptions();
		$oHandler = $oMod->getLicenseHandler();
		// Is a check actually required and permitted
		$bCheckReq = $this->isLicenseCheckRequired() && $this->canLicenseCheck();

		// 1 check in 20 seconds
		if ( ( $this->isForceCheck() || $bCheckReq ) && $this->getIsLicenseNotCheckedFor( 20 ) ) {

			$this->preVerify();

			$oCurrent = $oHandler->getLicense();

			$oLookupLicense = ( new LookupRequest() )
				->setMod( $oMod )
				->lookup();

			if ( $oLookupLicense->isValid() ) {
				$oCurrent = $oLookupLicense;
				$oCurrent->updateLastVerifiedAt( true );
				if ( !$oHandler->isLicenseActive() ) {
					$oOpts->setOptAt( 'license_activated_at' );
				}
				$oMod->clearLastErrors();
				$oCon->fireEvent( 'lic_check_success' );
			}
			elseif ( $oCurrent->isValid() ) {
				// we have something valid previously stored

				if ( !$this->isForceCheck() && $oHandler->isWithinVerifiedGraceExpired() ) {
					$oHandler->sendLicenseWarningEmail();
					$oCon->fireEvent( 'lic_fail_email' );
				}
				elseif ( $this->isForceCheck() || $oCurrent->isExpired() || $oHandler->isLastVerifiedGraceExpired() ) {
					$oCurrent = $oLookupLicense;
					$oHandler->deactivate();
					$oHandler->sendLicenseDeactivatedEmail();
					$oCon->fireEvent( 'lic_fail_deactivate' );
				}
			}
			elseif ( $oLookupLicense->isReady() ) {
				// No previously valid license, and the license lookup also failed but the http request was successful.
				$oHandler->deactivate();
				$oCurrent = $oLookupLicense;
			}

			$oCurrent->last_request_at = Services::Request()->ts();
			$oOpts->setOpt( 'license_data', $oCurrent->getRawDataAsArray() );
			$this->getMod()->saveModOptions();
		}
	}

	private function preVerify() {
		/** @var License\Options $oOpts */
		$oOpts = $this->getOptions();
		Services::WpFs()->touch( $this->getCon()->getPath_Flags( 'license_check' ) );
		$oOpts->setOptAt( 'license_last_checked_at' );
		$this->getMod()->saveModOptions();
	}

	/**
	 * @param int $nTimePeriod
	 * @return bool
	 */
	private function getIsLicenseNotCheckedFor( $nTimePeriod ) {
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();
		return $oMod->getLicenseHandler()->getLicenseNotCheckedForInterval() > $nTimePeriod;
	}

	/**
	 * @return bool
	 */
	private function isLicenseCheckRequired() {
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();
		$oHandler = $oMod->getLicenseHandler();
		return ( $this->isLicenseMaybeExpiring() && $this->getIsLicenseNotCheckedFor( HOUR_IN_SECONDS*4 ) )
			   || ( $oHandler->isLicenseActive()
					&& !$oHandler->getLicense()->isReady() && $this->getIsLicenseNotCheckedFor( HOUR_IN_SECONDS ) )
			   || ( $oHandler->hasValidWorkingLicense() && $oHandler->isLastVerifiedExpired()
					&& $this->getIsLicenseNotCheckedFor( HOUR_IN_SECONDS*4 ) );
	}

	/**
	 * @return bool
	 */
	protected function isLicenseMaybeExpiring() {
		/** @var \ICWP_WPSF_FeatureHandler_License $oMod */
		$oMod = $this->getMod();
		$oHandler = $oMod->getLicenseHandler();
		return $oHandler->isLicenseActive() &&
			   (
				   abs( Services::Request()->ts() - $oHandler->getLicense()->getExpiresAt() )
				   < ( DAY_IN_SECONDS/2 )
			   );
	}

	/**
	 * @return bool
	 */
	public function isForceCheck() {
		return (bool)$this->bForceCheck;
	}

	/**
	 * @param bool $bForceCheck
	 * @return $this
	 */
	public function setForceCheck( $bForceCheck ) {
		$this->bForceCheck = $bForceCheck;
		return $this;
	}

	/**
	 * @return bool
	 */
	private function canLicenseCheck() {
		return !in_array( $this->getCon()->getShieldAction(), [ 'keyless_handshake', 'license_check' ] )
			   && $this->canLicenseCheck_FileFlag();
	}

	/**
	 * @return bool
	 */
	private function canLicenseCheck_FileFlag() {
		$oFs = Services::WpFs();
		$sFileFlag = $this->getCon()->getPath_Flags( 'license_check' );
		$nMtime = $oFs->exists( $sFileFlag ) ? $oFs->getModifiedTime( $sFileFlag ) : 0;
		return ( Services::Request()->ts() - $nMtime ) > MINUTE_IN_SECONDS;
	}
}
