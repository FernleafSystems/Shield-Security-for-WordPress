<?php

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend;

class ICWP_WPSF_Processor_UserManagement_Suspend extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isSuspendManualEnabled() ) {
			add_action( 'edit_user_profile', array( $this, 'addUserBlockOption' ) );
			add_action( 'edit_user_profile_update', array( $this, 'handleUserBlockOptionSubmit' ) );
			( new Suspend\Suspended() )
				->setCon( $this->getCon() )
				->run();
		}
		if ( $oFO->isSuspendAutoIdleEnabled() ) {
			( new Suspend\Idle() )
				->setVerifiedExpires( $oFO->getSuspendAutoIdleTime() )
				->setCon( $this->getCon() )
				->run();
		}
		if ( $oFO->isSuspendAutoPasswordEnabled() ) {
			( new Suspend\PasswordExpiry() )
				->setMaxPasswordAge( $oFO->getPassExpireTimeout() )
				->setCon( $this->getCon() )
				->run();
		}
	}

	/**
	 * @param \WP_User $oUser
	 */
	public function addUserBlockOption( $oUser ) {
		$oCon = $this->getCon();
		$oMeta = $oCon->getUserMeta( $oUser );

		if ( $oCon->isPluginAdmin() ) {
			$aData = [
				'user_to_edit_is_admin' => Services::WpUsers()->isUserAdmin( $oUser ),
				'strings'               => array(
					'title'       => _wpsf__( 'Suspend Account' ),
					'label'       => _wpsf__( 'Check to block login by user' ),
					'description' => _wpsf__( 'The user will never be able to login when their account is suspended.' ),
				),
				'vars'                  => [
					'form_field'   => 'shield_suspend_user',
					'is_suspended' => $oMeta->is_hard_suspended
				]
			];
			echo $this->getMod()->renderTemplate( '/snippets/user/profile/suspend.twig', $aData, true );
		}
	}

	/**
	 * @param int $nUserId
	 */
	public function handleUserBlockOptionSubmit( $nUserId ) {
		$oCon = $this->getCon();
		if ( $oCon->isPluginAdmin() ) {
			$oMeta = $oCon->getUserMeta( Services::WpUsers()->getUserById( $nUserId ) );
			$oMeta->is_hard_suspended = Services::Request()->post( 'shield_suspend_user' ) === 'Y';
		}
	}
}