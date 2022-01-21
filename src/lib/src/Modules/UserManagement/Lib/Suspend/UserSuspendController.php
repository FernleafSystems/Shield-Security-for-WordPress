<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
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
					->execute();
			}
			if ( $opts->isSuspendAutoIdleEnabled() ) {
				( new Idle() )
					->setMod( $this->getMod() )
					->execute();
			}
			if ( $opts->isSuspendAutoPasswordEnabled() ) {
				( new PasswordExpiry() )
					->setMod( $this->getMod() )
					->execute();
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
		$userIDs = array_keys( $opts->getSuspendHardUserIds() );
		if ( !empty( $userIDs ) ) {
			// Provide the link above the table.
			add_filter( 'views_users', function ( $aViews ) use ( $userIDs ) {
				$aViews[ 'shield_suspended_users' ] = sprintf( '<a href="%s">%s</a>',
					add_query_arg( [ 'suspended' => 1 ], Services::WpGeneral()->getUrl_CurrentAdminPage() ),
					sprintf( '%s (%s)', __( 'Suspended', 'wp-simple-firewall' ), count( $userIDs ) ) );
				return $aViews;
			} );

			// Filter the database query
			add_filter( 'users_list_table_query_args', function ( $args ) use ( $userIDs ) {
				if ( is_array( $args ) && Services::Request()->query( 'suspended' ) ) {
					$args[ 'include' ] = $userIDs;
				}
				return $args;
			} );
		}
	}

	/**
	 * @param array $columns
	 * @return array
	 */
	public function addUserListSuspendedFlag( $columns ) {
		$customColumnName = $this->getCon()->prefix( 'col_user_status' );
		if ( !isset( $columns[ $customColumnName ] ) ) {
			$columns[ $customColumnName ] = __( 'User Status', 'wp-simple-firewall' );
		}

		add_filter( 'manage_users_custom_column',
			function ( $content, $columnName, $userID ) use ( $customColumnName ) {

				if ( $columnName == $customColumnName ) {
					$meta = $this->getCon()->getUserMeta( Services::WpUsers()->getUserById( $userID ) );
					if ( $meta->record->hard_suspended_at > 0 ) {
						$newContent = sprintf( '%s: %s',
							__( 'Suspended', 'wp-simple-firewall' ),
							Services::Request()
									->carbon( true )
									->setTimestamp( $meta->record->hard_suspended_at )
									->diffForHumans()
						);
						$content = empty( $content ) ? $newContent : $content.'<br/>'.$newContent;
					}
				}

				return $content;
			},
			10, 3
		);

		return $columns;
	}

	public function addUserBlockOption( \WP_User $user ) {
		$con = $this->getCon();
		$meta = $con->getUserMeta( $user );
		echo $this->getMod()->renderTemplate( '/admin/user/profile/suspend.twig', [
			'strings' => [
				'title'       => __( 'Suspend Account', 'wp-simple-firewall' ),
				'label'       => __( 'Check to un/suspend user account', 'wp-simple-firewall' ),
				'description' => __( 'The user can never login while their account is suspended.', 'wp-simple-firewall' ),
				'cant_manage' => __( 'Sorry, suspension for this account may only be managed by a security administrator.', 'wp-simple-firewall' ),
				'since'       => sprintf( '%s: %s', __( 'Suspended', 'wp-simple-firewall' ),
					Services::WpGeneral()->getTimeStringForDisplay( $meta->record->hard_suspended_at ) ),
			],
			'flags'   => [
				'can_manage_suspension' => !Services::WpUsers()->isUserAdmin( $user ) || $con->isPluginAdmin(),
				'is_suspended'          => $meta->record->hard_suspended_at > 0
			],
			'vars'    => [
				'form_field' => 'shield_suspend_user',
			]
		], true );
	}

	public function handleUserSuspendOptionSubmit( int $uid ) {
		$con = $this->getCon();
		$WPU = Services::WpUsers();

		$user = $WPU->getUserById( $uid );

		if ( $user instanceof \WP_User && ( !$WPU->isUserAdmin( $user ) || $con->isPluginAdmin() ) ) {
			$isSuspend = Services::Request()->post( 'shield_suspend_user' ) === 'Y';
			/** @var UserManagement\ModCon $mod */
			$mod = $this->getMod();
			$mod->addRemoveHardSuspendUser( $user, $isSuspend );

			if ( $isSuspend ) { // Delete any existing user sessions
				( new Terminate() )
					->setMod( $con->getModule_Sessions() )
					->byUsername( $user->user_login );
			}
		}
	}
}