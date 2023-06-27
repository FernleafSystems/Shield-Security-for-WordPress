<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\License\ShieldLicense;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LicenseHandler {

	use ExecOnce;
	use ModConsumer;
	use PluginCronsConsumer;

	/**
	 * @var ShieldLicense
	 */
	private $license;

	/**
	 * @var Capabilities
	 */
	private $caps;

	protected function run() {
		add_action( $this->con()->prefix( 'adhoc_cron_license_check' ), function () {
			$this->runAdhocLicenseCheck();
		} );

		$this->setupCronHooks();

		add_action( 'init', function () {
			$this->mod()->getWpHashesTokenManager()->execute();
		} );

		add_action( 'wp_loaded', function () {
			try {
				$this->verify();
			}
			catch ( \Exception $e ) {
			}
		} );
	}

	public function getCaps() :Capabilities {
		return $this->caps ?? $this->caps = new Capabilities();
	}

	public function runHourlyCron() {
		$this->mod()->getWpHashesTokenManager()->getToken();
	}

	public function scheduleAdHocCheck( ?int $delay = null ) {
		$con = $this->con();
		if ( !wp_next_scheduled( $con->prefix( 'adhoc_cron_license_check' ) ) ) {
			if ( empty( $delay ) ) {
				$delay = rand( \MINUTE_IN_SECONDS, \MINUTE_IN_SECONDS*30 );
			}
			wp_schedule_single_event(
				Services::Request()->ts() + \max( \MINUTE_IN_SECONDS, $delay ),
				$con->prefix( 'adhoc_cron_license_check' )
			);
		}
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
		$licHost = wp_parse_url( $this->getLicense()->url, PHP_URL_HOST );
		$reqHost = Services::Request()->getHost();
		if ( !$this->hasValidWorkingLicense() || empty( $licHost ) || empty( $reqHost ) || ( $licHost === $reqHost ) ) {
			try {
				$this->verify( true );
			}
			catch ( \Exception $e ) {
			}
		}
	}

	private function canCheck() :bool {
		return $this->getIsLicenseNotCheckedFor( 20 ) && $this->canLicenseCheck_FileFlag();
	}

	public function clearLicense() {
		$this->mod()->clearLastErrors();
		$this->updateLicenseData( [] );
	}

	public function updateLicenseData( array $data ) {
		$this->opts()->setOpt( 'license_data', $data );
		$this->unsetLicense();
	}

	public function deactivate( bool $sendEmail = true ) {
		if ( $this->isActive() ) {
			$this->clearLicense();
			$this->opts()->setOptAt( 'license_deactivated_at' );
			if ( $sendEmail ) {
				( new LicenseEmails() )->sendLicenseDeactivatedEmail();
			}
			$this->con()->fireEvent( 'lic_fail_deactivate' );
		}
		// force all options to resave i.e. reset premium to defaults.
		add_filter( $this->con()->prefix( 'force_options_resave' ), '__return_true' );
	}

	protected function getActivatedAt() :int {
		return (int)$this->opts()->getOpt( 'license_activated_at' );
	}

	protected function getDeactivatedAt() :int {
		return (int)$this->opts()->getOpt( 'license_deactivated_at' );
	}

	public function getLicense() :ShieldLicense {
		if ( !isset( $this->license ) ) {
			$data = $this->opts()->getOpt( 'license_data', [] );
			$this->license = ( new ShieldLicense() )->applyFromArray( \is_array( $data ) ? $data : [] );
		}
		return $this->license;
	}

	public function unsetLicense() :self {
		unset( $this->license );
		return $this;
	}

	public function getLicenseNotCheckedForInterval() :int {
		return (int)( Services::Request()->ts() - $this->opts()->getOpt( 'license_last_checked_at' ) );
	}

	/**
	 * Use the grace period (currently 3 days) to adjust when the license registration
	 * expires on this site. We consider a registration as expired if the last verified
	 * date is past, or the actual license is expired - whichever happens earlier -
	 * plus the grace period.
	 */
	public function getRegistrationExpiresAt() :int {
		$verifiedExpiredDays = $this->getLicVerifyExpireDays() + $this->getLicExpireGraceDays();

		$lic = $this->getLicense();
		return (int)min(
			$lic->getExpiresAt() + $this->getLicExpireGraceDays()*DAY_IN_SECONDS,
			$lic->last_verified_at + $verifiedExpiredDays*DAY_IN_SECONDS
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
			   > $this->getLicVerifyExpireDays()*DAY_IN_SECONDS;
	}

	public function isLastVerifiedGraceExpired() :bool {
		return ( Services::Request()->ts() - $this->getLicense()->last_verified_at )
			   > ( DAY_IN_SECONDS*( $this->getLicVerifyExpireDays() + $this->getLicExpireGraceDays() ) );
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
	 * @throws \Exception
	 */
	public function verify( bool $onDemand = false, bool $scheduleAnyway = false ) :self {
		if ( $this->canCheck() ) {
			if ( $onDemand ) {
				Services::WpCron()->deleteCronJob( $this->con()->prefix( 'adhoc_cron_license_check' ) );
				( new Verify() )->run();
			}
			elseif ( $scheduleAnyway || $this->isVerifyRequired() ) {
				$this->scheduleAdHocCheck();
			}
		}
		else {
			throw new \Exception( __( 'Please wait a short while before checking again.', 'wp-simple-firewall' ) );
		}

		return $this;
	}

	private function getIsLicenseNotCheckedFor( int $interval ) :bool {
		return $this->getLicenseNotCheckedForInterval() > $interval;
	}

	private function canLicenseCheck_FileFlag() :bool {
		$FS = Services::WpFs();
		$path = $this->con()->paths->forFlag( 'license_check' );
		$mtime = $FS->exists( $path ) ? $FS->getModifiedTime( $path ) : 0;
		return ( Services::Request()->ts() - $mtime ) > MINUTE_IN_SECONDS;
	}

	private function getLicVerifyExpireDays() :int {
		return (int)wp_rand( 9, 14 );
	}

	private function getLicExpireGraceDays() :int {
		return $this->opts()->getDef( 'lic_verify_expire_grace_days' );
	}
}