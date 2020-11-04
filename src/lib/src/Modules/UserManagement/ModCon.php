<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	/**
	 * Should have no default email. If no email is set, no notification is sent.
	 * @return string[]
	 */
	public function getAdminLoginNotificationEmails() {
		$aEmails = [];

		$sEmails = $this->getOptions()->getOpt( 'enable_admin_login_email_notification', '' );
		if ( !empty( $sEmails ) ) {
			$aEmails = array_values( array_unique( array_filter(
				array_map(
					function ( $sEmail ) {
						return trim( strtolower( $sEmail ) );
					},
					explode( ',', $sEmails )
				),
				function ( $sEmail ) {
					return Services::Data()->validEmail( $sEmail );
				}
			) ) );
			if ( !$this->isPremium() && !empty( $aEmails ) ) {
				$aEmails = array_slice( $aEmails, 0, 1 );
			}
		}

		return $aEmails;
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$opts->setOpt( 'enable_admin_login_email_notification', implode( ', ', $this->getAdminLoginNotificationEmails() ) );

		if ( $opts->getIdleTimeoutInterval() > $opts->getMaxSessionTime() ) {
			$opts->setOpt( 'session_idle_timeout_interval', $opts->getOpt( 'session_timeout_interval' )*24 );
		}

		$opts->setOpt( 'auto_idle_roles',
			array_unique( array_filter( array_map(
				function ( $sRole ) {
					return preg_replace( '#[^\sa-z0-9_-]#i', '', trim( strtolower( $sRole ) ) );
				},
				$opts->getSuspendAutoIdleUserRoles()
			) ) )
		);

		$aChecks = $opts->getEmailValidationChecks();
		if ( !empty( $aChecks ) ) {
			$aChecks[] = 'syntax';
		}
		$opts->setOpt( 'email_checks', array_unique( $aChecks ) );
	}

	public function isUserSessionsManagementEnabled() :bool {
		return $this->isModOptEnabled() && $this->getDbHandler_Sessions()->isReady();
	}

	public function isSendUserEmailLoginNotification() :bool {
		return $this->isPremium() && $this->getOptions()->isOpt( 'enable_user_login_email_notification', 'Y' );
	}

	/**
	 * @param int $nStrength
	 * @return int
	 */
	public function getPassStrengthName( $nStrength ) {
		$aMap = [
			__( 'Very Weak', 'wp-simple-firewall' ),
			__( 'Weak', 'wp-simple-firewall' ),
			__( 'Medium', 'wp-simple-firewall' ),
			__( 'Strong', 'wp-simple-firewall' ),
			__( 'Very Strong', 'wp-simple-firewall' ),
		];
		return $aMap[ max( 0, min( 4, $nStrength ) ) ];
	}

	/**
	 * @param int  $nUserId
	 * @param bool $bAdd - set true to add, false to remove
	 */
	public function addRemoveHardSuspendUserId( $nUserId, $bAdd = true ) {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$aIds = $opts->getSuspendHardUserIds();

		$oMeta = $this->getCon()->getUserMeta( Services::WpUsers()->getUserById( $nUserId ) );
		$bIdSuspended = isset( $aIds[ $nUserId ] ) || $oMeta->hard_suspended_at > 0;

		if ( $bAdd && !$bIdSuspended ) {
			$oMeta->hard_suspended_at = Services::Request()->ts();
			$aIds[ $nUserId ] = $oMeta->hard_suspended_at;
			$this->getCon()->fireEvent(
				'user_hard_suspended',
				[
					'audit' => [
						'user_id' => $nUserId,
						'admin'   => Services::WpUsers()->getCurrentWpUsername(),
					]
				]
			);
		}
		elseif ( !$bAdd && $bIdSuspended ) {
			$oMeta->hard_suspended_at = 0;
			unset( $aIds[ $nUserId ] );
			$this->getCon()->fireEvent(
				'user_hard_unsuspended',
				[
					'audit' => [
						'user_id' => $nUserId,
						'admin'   => Services::WpUsers()->getCurrentWpUsername(),
					]
				]
			);
		}

		$opts->setOpt( 'hard_suspended_userids', $aIds );
	}
}