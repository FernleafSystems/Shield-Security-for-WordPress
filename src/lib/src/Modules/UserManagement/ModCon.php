<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'user_management';

	private $userSuspendCon;

	public function getUserSuspendCon() :Lib\Suspend\UserSuspendController {
		return $this->userSuspendCon ?? $this->userSuspendCon = new Lib\Suspend\UserSuspendController();
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->opts();

		if ( $opts->getIdleTimeoutInterval() > $opts->getMaxSessionTime() ) {
			$opts->setOpt( 'session_idle_timeout_interval', $opts->getOpt( 'session_timeout_interval' )*24 );
		}

		$opts->setOpt( 'auto_idle_roles',
			\array_unique( \array_filter( \array_map(
				function ( $role ) {
					return \preg_replace( '#[^\s\da-z_-]#i', '', \trim( \strtolower( $role ) ) );
				},
				$opts->getSuspendAutoIdleUserRoles()
			) ) )
		);

		$checks = $opts->getEmailValidationChecks();
		if ( !empty( $checks ) ) {
			$checks[] = 'syntax';
		}
		$opts->setOpt( 'email_checks', \array_unique( $checks ) );
	}
}