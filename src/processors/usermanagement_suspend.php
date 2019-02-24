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
			add_filter( 'manage_users_columns', array( $this, 'addUserListSuspendedFlag' ) );
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
	 * Adds the column to the users listing table to indicate whether WordPress will automatically update the plugins
	 * @param array $aColumns
	 * @return array
	 */
	public function addUserListSuspendedFlag( $aColumns ) {

		$sCustomColumnName = $this->prefix( 'col_user_status' );
		if ( !isset( $aColumns[ $sCustomColumnName ] ) ) {
			$aColumns[ $sCustomColumnName ] = _wpsf__( 'User Status' );
		}

		add_filter( 'manage_users_custom_column',
			function ( $sContent, $sColumnName, $nUserId ) use ( $sCustomColumnName ) {

				if ( $sColumnName == $sCustomColumnName ) {
					$oUser = Services::WpUsers()->getUserById( $nUserId );
					if ( $oUser instanceof \WP_User ) {
						$oMeta = $this->getCon()->getUserMeta( $oUser );
						if ( $oMeta->hard_suspended_at > 0 ) {
							$sNewContent = sprintf( '%s: %s',
								_wpsf__( 'Suspended' ),
								( new \Carbon\Carbon() )->setTimestamp( $oMeta->hard_suspended_at )->diffForHumans()
							);
							$sContent = empty( $sContent ) ? $sNewContent : $sContent.'<br/>'.$sNewContent;
						}
					}
				}

				return $sContent;
			},
			10, 3
		);

		return $aColumns;
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
					'label'       => _wpsf__( 'Check to suspend user account' ),
					'description' => _wpsf__( 'The user will never be able to login while their account is suspended.' ),
				),
				'vars'                  => [
					'form_field'   => 'shield_suspend_user',
					'is_suspended' => $oMeta->hard_suspended_at > 0
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
			$oReq = Services::Request();
			$oMeta = $oCon->getUserMeta( Services::WpUsers()->getUserById( $nUserId ) );
			$bIsToSuspend = $oReq->post( 'shield_suspend_user' ) === 'Y';
			if ( ( $oMeta->hard_suspended_at > 0 ) !== $bIsToSuspend ) {
				$oMeta->hard_suspended_at = $bIsToSuspend ? $oReq->ts() : 0;
			}
		}
	}
}