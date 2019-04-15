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
		add_filter( 'edit_user_profile_update', [ $this, 'handleUserBlockOptionSubmit' ] );

		// Display suspended on the user list table
		add_filter( 'manage_users_columns', [ $this, 'addUserListSuspendedFlag' ] );

		// Provide Suspended user filter above table
		$aUserIds = array_keys( $oFO->getSuspendHardUserIds() );
		if ( !empty( $aUserIds ) ) {
			// Provide the link above the table.
			add_filter( 'views_users', function ( $aViews ) use ( $aUserIds ) {
				$nTotal = count( $aUserIds );
				$aViews[ 'shield_suspended_users' ] = sprintf( '<a href="%s">%s</a>',
					add_query_arg( [ 'suspended' => 1 ], Services::WpGeneral()->getUrl_CurrentAdminPage() ),
					sprintf( '%s (%s)', _wpsf__( 'Suspended' ), $nTotal ) );
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
				/** @var ICWP_WPSF_FeatureHandler_UserManagement $oFO */
				$oFO = $this->getMod();
				$oFO->addRemoveHardSuspendUserId( $nUserId, $bIsToSuspend );
			}
		}
	}
}