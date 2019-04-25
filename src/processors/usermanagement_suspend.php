<?php

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Suspend;

class ICWP_WPSF_Processor_UserManagement_Suspend extends ICWP_WPSF_Processor_BaseWpsf {

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isSuspendManualEnabled() ) {
			$this->setupUserFilters();
			( new Suspend\Suspended() )
				->setMod( $this->getMod() )
				->run();
		}

		if ( $oFO->isSuspendAutoIdleEnabled() ) {
			( new Suspend\Idle() )
				->setMod( $this->getMod() )
				->run();
		}

		if ( $oFO->isSuspendAutoPasswordEnabled() ) {
			( new Suspend\PasswordExpiry() )
				->setMaxPasswordAge( $oFO->getPassExpireTimeout() )
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
		$nVersion = $this->getCon()->getVersionNumeric();
		$sMetaKey = $this->prefix( 'meta-version' );

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
		/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
		$oFO = $this->getMod();

		// User profile UI
		add_filter( 'edit_user_profile', [ $this, 'addUserBlockOption' ], 1, 1 );
		add_action( 'edit_user_profile_update', [ $this, 'handleUserSuspendOptionSubmit' ] );

		// Display suspended on the user list table
		add_filter( 'manage_users_columns', [ $this, 'addUserListSuspendedFlag' ] );

		// Provide Suspended user filter above table
		$aUserIds = array_keys( $oFO->getSuspendHardUserIds() );
		if ( !empty( $aUserIds ) ) {
			// Provide the link above the table.
			add_filter( 'views_users', function ( $aViews ) use ( $aUserIds ) {
				$aViews[ 'shield_suspended_users' ] = sprintf( '<a href="%s">%s</a>',
					add_query_arg( [ 'suspended' => 1 ], Services::WpGeneral()->getUrl_CurrentAdminPage() ),
					sprintf( '%s (%s)', _wpsf__( 'Suspended' ), count( $aUserIds ) ) );
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
		$oWpUsers = Services::WpUsers();
		$oMeta = $oCon->getUserMeta( $oUser );

		$oWpUsers->isUserAdmin( $oUser );

		$aData = [
			'strings' => [
				'title'       => _wpsf__( 'Suspend Account' ),
				'label'       => _wpsf__( 'Check to un/suspend user account' ),
				'description' => _wpsf__( 'The user can never login while their account is suspended.' ),
				'cant_manage' => _wpsf__( 'Sorry, suspension for this account may only be managed by a security administrator.' ),
				'since'       => sprintf( '%s: %s', _wpsf__( 'Suspended' ), Services::WpGeneral()
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
			/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
			$oFO = $this->getMod();
			$oFO->addRemoveHardSuspendUserId( $nUserId, $bIsSuspend );

			if ( $bIsSuspend ) { // Delete any existing user sessions
				$oProcessor = $oFO->getSessionsProcessor();
				/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Delete $oDel */
				$oDel = $oProcessor->getDbHandler()->getQueryDeleter();
				$oDel->forUsername( $oEditedUser->user_login );
			}
		}
	}
}