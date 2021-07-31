<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib\Ops\Terminate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement;
use FernleafSystems\Wordpress\Services\Services;

class UserSuspendController extends ExecOnceModConsumer {

	protected function canRun() :bool {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();
		return $opts->isSuspendEnabled() && $this->getCon()->isPremiumActive();
	}

	protected function run() {
		/** @var UserManagement\ModCon $mod */
		$mod = $this->getMod();
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();

		if ( $opts->isSuspendManualEnabled() ) {
			$this->applyManualSuspendUIFilters();
		}

		if ( !$mod->isVisitorWhitelisted() ) {

			if ( $opts->isSuspendManualEnabled() ) {
				( new Suspended() )
					->setMod( $this->getMod() )
					->run();
			}
			if ( $opts->isSuspendAutoIdleEnabled() ) {
				( new Idle() )
					->setMod( $this->getMod() )
					->run();
			}
			if ( $opts->isSuspendAutoPasswordEnabled() ) {
				( new PasswordExpiry() )
					->setMaxPasswordAge( $opts->getPassExpireTimeout() )
					->setMod( $this->getMod() )
					->run();
			}
		}
	}

	/**
	 * Sets-up all the UI filters necessary to provide manual user suspension
	 * filter the User Tables
	 */
	private function applyManualSuspendUIFilters() {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();

		// User profile UI
		add_filter( 'edit_user_profile', [ $this, 'addUserBlockOption' ], 1 );
		add_action( 'edit_user_profile_update', [ $this, 'handleUserSuspendOptionSubmit' ] );

		// Display suspended on the user list table
		add_filter( 'manage_users_columns', [ $this, 'addUserListSuspendedFlag' ] );

		// Provide Suspended user filter above table
		$aUserIds = array_keys( $opts->getSuspendHardUserIds() );
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
										->carbon( true )
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

	public function addUserBlockOption( \WP_User $oUser ) {
		$con = $this->getCon();
		$meta = $con->getUserMeta( $oUser );
		$oWpUsers = Services::WpUsers();

		$aData = [
			'strings' => [
				'title'       => __( 'Suspend Account', 'wp-simple-firewall' ),
				'label'       => __( 'Check to un/suspend user account', 'wp-simple-firewall' ),
				'description' => __( 'The user can never login while their account is suspended.', 'wp-simple-firewall' ),
				'cant_manage' => __( 'Sorry, suspension for this account may only be managed by a security administrator.', 'wp-simple-firewall' ),
				'since'       => sprintf( '%s: %s', __( 'Suspended', 'wp-simple-firewall' ),
					Services::WpGeneral()->getTimeStringForDisplay( $meta->hard_suspended_at ) ),
			],
			'flags'   => [
				'can_manage_suspension' => !$oWpUsers->isUserAdmin( $oUser ) || $con->isPluginAdmin(),
				'is_suspended'          => $meta->hard_suspended_at > 0
			],
			'vars'    => [
				'form_field' => 'shield_suspend_user',
			]
		];
		echo $this->getMod()->renderTemplate( '/admin/user/profile/suspend.twig', $aData, true );
	}

	public function handleUserSuspendOptionSubmit( int $uid ) {
		$con = $this->getCon();
		$WPU = Services::WpUsers();

		$oEditedUser = $WPU->getUserById( $uid );

		if ( !$WPU->isUserAdmin( $oEditedUser ) || $con->isPluginAdmin() ) {
			$isSuspend = Services::Request()->post( 'shield_suspend_user' ) === 'Y';
			/** @var UserManagement\ModCon $mod */
			$mod = $this->getMod();
			$mod->addRemoveHardSuspendUserId( $uid, $isSuspend );

			if ( $isSuspend ) { // Delete any existing user sessions
				( new Terminate() )
					->setMod( $con->getModule_Sessions() )
					->byUsername( $oEditedUser->user_login );
			}
		}
	}
}