<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	/**
	 * Should have no default email. If no email is set, no notification is sent.
	 * @return string[]
	 */
	public function getAdminLoginNotificationEmails() :array {
		$emails = [];

		$rawEmails = $this->getOptions()->getOpt( 'enable_admin_login_email_notification', '' );
		if ( !empty( $rawEmails ) ) {
			$emails = array_values( array_unique( array_filter(
				array_map(
					function ( $sEmail ) {
						return trim( strtolower( $sEmail ) );
					},
					explode( ',', $rawEmails )
				),
				function ( $email ) {
					return Services::Data()->validEmail( $email );
				}
			) ) );
			if ( count( $emails ) > 1 && !$this->isPremium() ) {
				$emails = array_slice( $emails, 0, 1 );
			}
		}

		return $emails;
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

		$checks = $opts->getEmailValidationChecks();
		if ( !empty( $checks ) ) {
			$checks[] = 'syntax';
		}
		$opts->setOpt( 'email_checks', array_unique( $checks ) );
	}

	public function isSendUserEmailLoginNotification() :bool {
		return $this->isPremium() && $this->getOptions()->isOpt( 'enable_user_login_email_notification', 'Y' );
	}

	/**
	 * @param int $nStrength
	 * @return int
	 */
	public function getPassStrengthName( $nStrength ) {
		return [
				   __( 'Very Weak', 'wp-simple-firewall' ),
				   __( 'Weak', 'wp-simple-firewall' ),
				   __( 'Medium', 'wp-simple-firewall' ),
				   __( 'Strong', 'wp-simple-firewall' ),
				   __( 'Very Strong', 'wp-simple-firewall' ),
			   ][ max( 0, min( 4, $nStrength ) ) ];
	}

	/**
	 * @param int  $userID
	 * @param bool $add - set true to add, false to remove
	 */
	public function addRemoveHardSuspendUser( \WP_User $user, bool $add = true ) {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$IDs = $opts->getSuspendHardUserIds();

		$meta = $this->getCon()->getUserMeta( $user );
		$isSuspended = isset( $IDs[ $user->ID ] ) || $meta->hard_suspended_at > 0;

		if ( $add && !$isSuspended ) {
			$meta->hard_suspended_at = Services::Request()->ts();
			$IDs[ $user->ID ] = $meta->hard_suspended_at;
			$this->getCon()->fireEvent(
				'user_hard_suspended',
				[
					'audit_params' => [
						'user_login' => $user->user_login,
						'admin'      => Services::WpUsers()->getCurrentWpUsername(),
					]
				]
			);
		}
		elseif ( !$add && $isSuspended ) {
			$meta->hard_suspended_at = 0;
			unset( $IDs[ $user->ID ] );
			$this->getCon()->fireEvent(
				'user_hard_unsuspended',
				[
					'audit_params' => [
						'user_login' => $user->user_login,
						'admin'      => Services::WpUsers()->getCurrentWpUsername(),
					]
				]
			);
		}

		$opts->setOpt( 'hard_suspended_userids', $IDs );
	}
}