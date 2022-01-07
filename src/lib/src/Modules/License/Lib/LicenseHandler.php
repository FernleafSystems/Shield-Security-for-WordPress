<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\License\EddLicenseVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\HandshakingNonce;
use FernleafSystems\Wordpress\Services\Services;

class LicenseHandler extends Modules\Base\Common\ExecOnceModConsumer {

	protected function run() {
		add_action( $this->getCon()->prefix( 'shield_action' ), function ( $action ) {
			$con = $this->getCon();
			switch ( $action ) {

				case 'keyless_handshake':
				case 'snapi_handshake':
					$nonce = Services::Request()->query( 'nonce' );
					if ( !empty( $nonce ) ) {
						die( json_encode( [
							'success' => ( new HandshakingNonce() )
								->setMod( $this->getMod() )
								->verify( $nonce )
						] ) );
					}
					break;

				case 'license_check':
					if ( !wp_next_scheduled( $con->prefix( 'adhoc_cron_license_check' ) ) ) {
						wp_schedule_single_event(
							Services::Request()->ts() + 20,
							$con->prefix( 'adhoc_cron_license_check' )
						);
					}
					break;
			}
		} );

		// performs the license check on-demand
		add_action( $this->getCon()->prefix( 'adhoc_cron_license_check' ), function () {
			$this->runAdhocLicenseCheck();
		} );
	}

	/**
	 * Customer reported that they're using a multilingual system with different hostnames for each language.
	 * This meant that adhoc lookups that happen on the wrong hostname name request would fail and remove
	 * the license. So now we tie ad-hoc lookups to the hostname.
	 *
	 * This doesn't solve all problems since the ad-hoc lookup is cron-based, and the cron may get triggered
	 * on the wrong hostname.
	 */
	private function runAdhocLicenseCheck() {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$licHost = wp_parse_url( $this->getLicense()->url, PHP_URL_HOST );
		$reqHost = Services::Request()->getHost();
		if ( !$this->hasValidWorkingLicense() || empty( $licHost ) || empty( $reqHost ) || ( $licHost === $reqHost ) ) {
			try {
				$mod->getLicenseHandler()->verify();
			}
			catch ( \Exception $e ) {
			}
		}
	}

	private function canCheck() :bool {
		return !in_array( $this->getCon()->getShieldAction(), [ 'keyless_handshake', 'license_check' ] )
			   && $this->getIsLicenseNotCheckedFor( 20 )
			   && $this->canLicenseCheck_FileFlag();
	}

	/**
	 * @return $this
	 */
	public function clearLicense() {
		$this->getMod()->clearLastErrors();
		$this->getOptions()->setOpt( 'license_data', [] );
		return $this;
	}

	/**
	 * @param bool $sendEmail
	 */
	public function deactivate( bool $sendEmail = true ) {
		if ( $this->isActive() ) {
			$this->clearLicense();
			$this->getOptions()->setOptAt( 'license_deactivated_at' );
			if ( $sendEmail ) {
				( new LicenseEmails() )
					->setMod( $this->getMod() )
					->sendLicenseDeactivatedEmail();
			}
			$this->getCon()->fireEvent( 'lic_fail_deactivate' );
		}
		// force all options to resave i.e. reset premium to defaults.
		add_filter( $this->getCon()->prefix( 'force_options_resave' ), '__return_true' );
	}

	protected function getActivatedAt() :int {
		return (int)$this->getOptions()->getOpt( 'license_activated_at' );
	}

	protected function getDeactivatedAt() :int {
		return (int)$this->getOptions()->getOpt( 'license_deactivated_at' );
	}

	public function getLicense() :EddLicenseVO {
		$data = $this->getOptions()->getOpt( 'license_data', [] );
		return ( new EddLicenseVO() )->applyFromArray( is_array( $data ) ? $data : [] );
	}

	public function getLicenseNotCheckedForInterval() :int {
		return (int)( Services::Request()->ts() - $this->getOptions()->getOpt( 'license_last_checked_at' ) );
	}

	/**
	 * Use the grace period (currently 3 days) to adjust when the license registration
	 * expires on this site. We consider a registration as expired if the last verified
	 * date is past, or the actual license is expired - whichever happens earlier -
	 * plus the grace period.
	 * @return int
	 */
	public function getRegistrationExpiresAt() :int {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$opts = $this->getOptions();

		$nVerifiedExpiredDays = $opts->getDef( 'lic_verify_expire_days' )
								+ $opts->getDef( 'lic_verify_expire_grace_days' );

		$oLic = $mod->getLicenseHandler()->getLicense();
		return (int)min(
			$oLic->getExpiresAt() + $opts->getDef( 'lic_verify_expire_grace_days' )*DAY_IN_SECONDS,
			$oLic->last_verified_at + $nVerifiedExpiredDays*DAY_IN_SECONDS
		);
	}

	/**
	 * IMPORTANT: Method used by Shield Central. Modify with care.
	 * We test various data points:
	 * 1) the key is valid format
	 * 2) the official license status is 'valid'
	 * 3) the license is marked as "active"
	 * 4) the license hasn't expired
	 * 5) the time since the last check hasn't expired
	 */
	public function hasValidWorkingLicense() :bool {
		return $this->getLicense()->isValid() && $this->isActive();
	}

	public function isActive() :bool {
		return ( $this->getActivatedAt() > 0 )
			   && ( $this->getDeactivatedAt() < $this->getActivatedAt() );
	}

	public function isLastVerifiedExpired() :bool {
		return ( Services::Request()->ts() - $this->getLicense()->last_verified_at )
			   > $this->getOptions()->getDef( 'lic_verify_expire_days' )*DAY_IN_SECONDS;
	}

	public function isLastVerifiedGraceExpired() :bool {
		$oOpts = $this->getOptions();
		$nGracePeriod = ( $oOpts->getDef( 'lic_verify_expire_days' )
						  + $oOpts->getDef( 'lic_verify_expire_grace_days' ) )*DAY_IN_SECONDS;
		return ( Services::Request()->ts() - $this->getLicense()->last_verified_at ) > $nGracePeriod;
	}

	private function isMaybeExpiring() :bool {
		return $this->isActive() &&
			   (
				   abs( Services::Request()->ts() - $this->getLicense()->getExpiresAt() )
				   < ( DAY_IN_SECONDS/2 )
			   );
	}

	public function isWithinVerifiedGraceExpired() :bool {
		return $this->isLastVerifiedExpired() && !$this->isLastVerifiedGraceExpired();
	}

	private function isVerifyRequired() :bool {
		return ( $this->isMaybeExpiring() && $this->getIsLicenseNotCheckedFor( HOUR_IN_SECONDS*4 ) )
			   || ( $this->isActive()
					&& !$this->getLicense()->isReady() && $this->getIsLicenseNotCheckedFor( HOUR_IN_SECONDS ) )
			   || ( $this->hasValidWorkingLicense() && $this->isLastVerifiedExpired()
					&& $this->getIsLicenseNotCheckedFor( HOUR_IN_SECONDS*4 ) );
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	public function verify( bool $force = true ) {
		if ( $force || ( $this->isVerifyRequired() && $this->canCheck() ) ) {
			( new Verify() )
				->setMod( $this->getMod() )
				->run();
		}
		return $this;
	}

	private function getIsLicenseNotCheckedFor( $nTimePeriod ) :bool {
		return $this->getLicenseNotCheckedForInterval() > $nTimePeriod;
	}

	private function canLicenseCheck_FileFlag() :bool {
		$mtime = Services::WpFs()->getModifiedTime(
			$this->getCon()->paths->forFlag( 'license_check' )
		);
		return ( Services::Request()->ts() - $mtime ) > MINUTE_IN_SECONDS;
	}
}