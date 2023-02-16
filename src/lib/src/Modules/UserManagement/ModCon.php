<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend\UserSuspendController;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'user_management';

	private $userSuspensionController;

	/**
	 * @deprecated 17.0
	 */
	public function getUserSuspendController() :UserSuspendController {
		return $this->userSuspensionController ?? $this->userSuspensionController = ( new UserSuspendController() )->setMod( $this );
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		if ( $opts->getIdleTimeoutInterval() > $opts->getMaxSessionTime() ) {
			$opts->setOpt( 'session_idle_timeout_interval', $opts->getOpt( 'session_timeout_interval' )*24 );
		}

		$opts->setOpt( 'auto_idle_roles',
			array_unique( array_filter( array_map(
				function ( $role ) {
					return preg_replace( '#[^\s\da-z_-]#i', '', trim( strtolower( $role ) ) );
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
}