<?php

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_UserManagement_Suspend extends Modules\BaseShield\ShieldProcessor {

	public function run() {
		/** @var UserManagement\Options $oOpts */
		$oOpts = $this->getOptions();

		if ( $oOpts->isSuspendManualEnabled() ) {
			$this->setupUserFilters();
			( new Suspend\Suspended() )
				->setMod( $this->getMod() )
				->run();
		}

		if ( $oOpts->isSuspendAutoIdleEnabled() ) {
			( new Suspend\Idle() )
				->setMod( $this->getMod() )
				->run();
		}

		if ( $oOpts->isSuspendAutoPasswordEnabled() ) {
			( new Suspend\PasswordExpiry() )
				->setMaxPasswordAge( $oOpts->getPassExpireTimeout() )
				->setMod( $this->getMod() )
				->run();
		}
	}

	public function runHourlyCron() {
		$this->updateUserMetaVersion();
	}

	/**
	 * Run from CRON
	 * Updates all user meta versions. Limits to 25 users at a time via the cron
	 */
	private function updateUserMetaVersion() {
		$oCon = $this->getCon();
		$nVersion = $oCon->getVersionNumeric();
		$sMetaKey = $oCon->prefix( 'meta-version' );

		$nCount = 0;

		$oUserIt = new \FernleafSystems\Wordpress\Services\Utilities\Iterators\WpUserIterator();
		$oUserIt->filterByMeta( $sMetaKey, $nVersion, 'NOT EXISTS' );
		foreach ( $oUserIt as $oUser ) {
			$oCon->getUserMeta( $oUser );
			if ( $nCount++ > 25 ) {
				break;
			}
		}

		$oUserIt = new \FernleafSystems\Wordpress\Services\Utilities\Iterators\WpUserIterator();
		$oUserIt->filterByMeta( $sMetaKey, $nVersion, '<' );
		foreach ( $oUserIt as $oUser ) {
			$oCon->getUserMeta( $oUser );
			if ( $nCount++ > 25 ) {
				break;
			}
		}
	}

	/**
	 * Sets-up all the UI filters necessary to provide manual user suspension and filter the User Tables
	 */
	private function setupUserFilters() {
		/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
		$oMod = $this->getMod();

		// User profile UI
		add_filter( 'edit_user_profile', [ $this, 'addUserBlockOption' ], 1, 1 );
		add_action( 'edit_user_profile_update', [ $this, 'handleUserSuspendOptionSubmit' ] );

		// Display suspended on the user list table
		add_filter( 'manage_users_columns', [ $this, 'addUserListSuspendedFlag' ] );

		// Provide Suspended user filter above table
		$aUserIds = array_keys( $oMod->getSuspendHardUserIds() );
		if ( !empty( $aUserIds ) ) {
			// Provide the link above the table.
			add_filter( 'views_users', function ( $aViews ) use ( $aUserIds ) {
				$aViews[ 'shield_suspended_users' ] = sprintf( '<a href="%s">%s</a>',
					add_query_arg( [ 'suspended' => 1 ], Services::WpGeneral()->getUrl_CurrentAdminPage() ),
					sprintf( '%s (%s)', __( 'Suspended', 'wp-simple-firewall' ), count( $aUserIds ) ) );
				return $aViews;
			} );

			// Filter the database query
			add_filter( 'users_list_table_query_args', function ( $aQueryArgs ) use ( $aUserIds ) {
				if ( is_array( $aQueryArgs ) && Services::Request()->query( 'suspended' ) ) {
					$aQueryArgs[ 'include' ] = $aUserIds;
				}
				return $aQueryArgs;
			} );
		}
	}

	/**
	 * @param array $aColumns
	 * @return array
	 */
	public function addUserListSuspendedFlag( $aColumns ) {

		$sCustomColumnName = $this->getCon()->prefix( 'col_user_status' );
		if ( !isset( $aColumns[ $sCustomColumnName ] ) ) {
			$aColumns[ $sCustomColumnName ] = __( 'User Status', 'wp-simple-firewall' );
		}

		add_filter( 'manage_users_custom_column',
			function ( $sContent, $sColumnName, $nUserId ) use ( $sCustomColumnName ) {

				if ( $sColumnName == $sCustomColumnName ) {
					$oUser = Services::WpUsers()->getUserById( $nUserId );
					if ( $oUser instanceof \WP_User ) {
						$oMeta = $this->getCon()->getUserMeta( $oUser );
						if ( $oMeta->hard_suspended_at > 0 ) {
							$sNewContent = sprintf( '%s: %s',
								__( 'Suspended', 'wp-simple-firewall' ),
								Services::Request()
										->carbon()
										->setTimestamp( $oMeta->hard_suspended_at )
										->diffForHumans()
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
		$oWpUsers = Services::WpUsers();

		$aData = [
			'strings' => [
				'title'       => __( 'Suspend Account', 'wp-simple-firewall' ),
				'label'       => __( 'Check to un/suspend user account', 'wp-simple-firewall' ),
				'description' => __( 'The user can never login while their account is suspended.', 'wp-simple-firewall' ),
				'cant_manage' => __( 'Sorry, suspension for this account may only be managed by a security administrator.', 'wp-simple-firewall' ),
				'since'       => sprintf( '%s: %s', __( 'Suspended', 'wp-simple-firewall' ), Services::WpGeneral()
																									 ->getTimeStringForDisplay( $oMeta->hard_suspended_at ) ),
			],
			'flags'   => [
				'can_manage_suspension' => !$oWpUsers->isUserAdmin( $oUser ) || $oCon->isPluginAdmin(),
				'is_suspended'          => $oMeta->hard_suspended_at > 0
			],
			'vars'    => [
				'form_field' => 'shield_suspend_user',
			]
		];
		echo $this->getMod()->renderTemplate( '/snippets/user/profile/suspend.twig', $aData, true );
	}

	/**
	 * @param int $nUserId
	 */
	public function handleUserSuspendOptionSubmit( $nUserId ) {
		$oCon = $this->getCon();
		$oWpUsers = Services::WpUsers();

		$oEditedUser = $oWpUsers->getUserById( $nUserId );

		if ( !$oWpUsers->isUserAdmin( $oEditedUser ) || $oCon->isPluginAdmin() ) {
			$bIsSuspend = Services::Request()->post( 'shield_suspend_user' ) === 'Y';
			/** @var \ICWP_WPSF_FeatureHandler_UserManagement $oMod */
			$oMod = $this->getMod();
			$oMod->addRemoveHardSuspendUserId( $nUserId, $bIsSuspend );

			if ( $bIsSuspend ) { // Delete any existing user sessions
				( new Sessions\Lib\Ops\Terminate() )
					->setMod( $oCon->getModule_Sessions() )
					->byUsername( $oEditedUser->user_login );
			}
		}
	}
}