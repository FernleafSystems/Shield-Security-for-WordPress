<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\UserMeta\Ops\Select;
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

		// User profile UI
		add_filter( 'edit_user_profile', [ $this, 'addUserBlockOption' ], 1 );
		add_action( 'edit_user_profile_update', [ $this, 'handleUserSuspendOptionSubmit' ] );

		// Show suspended user list filters
		add_action( 'load-users.php', function () {

			$this->addSuspendedUserFilters();

			// Display manually suspended on the user list table; TODO: at auto suspended
			add_filter( 'shield/user_status_column', function ( array $content, int $userID ) {

				$meta = $this->getCon()->getUserMeta( Services::WpUsers()->getUserById( $userID ) );
				if ( $meta->record->hard_suspended_at > 0 ) {
					$content[] = sprintf( '%s: %s',
						__( 'Suspended', 'wp-simple-firewall' ),
						Services::Request()
								->carbon( true )
								->setTimestamp( $meta->record->hard_suspended_at )
								->diffForHumans()
					);
				}

				return $content;
			}, 10, 2 );
		} );
	}

	/**
	 * Sets-up all the UI filters necessary to provide manual user suspension
	 * filter the User Tables
	 */
	private function addSuspendedUserFilters() {
		/** @var UserManagement\Options $opts */
		$opts = $this->getOptions();
		$ts = Services::Request()->ts();

		/** @var Select $metaSelect */
		$metaSelect = $this->getCon()
						   ->getModule_Data()
						   ->getDbH_UserMeta()
						   ->getQuerySelector();

		if ( $opts->isSuspendManualEnabled() ) {
			$manual = array_map(
				function ( $res ) {
					return (int)array_pop( $res );
				},
				$metaSelect->filterByHardSuspended()
						   ->setResultsAsVo( false )
						   ->setSelectResultsFormat( ARRAY_A )
						   ->setColumnsToSelect( [ 'user_id' ] )
						   ->queryWithResult()
			);
		}
		else {
			$manual = [];
		}

		if ( $opts->isSuspendAutoPasswordEnabled() ) {
			$passwords = array_map(
				function ( $res ) {
					return (int)array_pop( $res );
				},
				$metaSelect->filterByPassExpired( $ts - $opts->getPassExpireTimeout() )
						   ->setResultsAsVo( false )
						   ->setSelectResultsFormat( ARRAY_A )
						   ->setColumnsToSelect( [ 'user_id' ] )
						   ->queryWithResult()
			);
		}
		else {
			$passwords = [];
		}

		if ( $opts->isSuspendAutoIdleEnabled() ) {
			$idle = array_map(
				function ( $res ) {
					return (int)array_pop( $res );
				},
				$metaSelect->filterByIdle( $ts - $opts->getSuspendAutoIdleTime() )
						   ->setResultsAsVo( false )
						   ->setSelectResultsFormat( ARRAY_A )
						   ->setColumnsToSelect( [ 'user_id' ] )
						   ->queryWithResult()
			);
		}
		else {
			$idle = [];
		}

		// Provide the links above the table.
		add_filter( 'views_users', function ( $views ) use ( $manual, $idle, $passwords ) {

			if ( !empty( $manual ) ) {
				$views[ 'shield_users_suspended' ] = sprintf(
					'<a href="%s">%s <span class="count">(%s)</span></a>',
					add_query_arg( [ 'shield_users_suspended' => 1 ], Services::WpGeneral()
																			  ->getUrl_CurrentAdminPage() ),
					__( 'Manually Suspended', 'wp-simple-firewall' ), count( $manual )
				);

				// Filter the database query
				add_filter( 'users_list_table_query_args', function ( $args ) use ( $manual ) {
					if ( is_array( $args ) && Services::Request()->query( 'shield_users_suspended' ) ) {
						$args[ 'include' ] = $manual;
					}
					return $args;
				} );
			}

			if ( !empty( $idle ) ) {
				$views[ 'shield_idle_users' ] = sprintf(
					'<a href="%s">%s <span class="count">(%s)</span></a>',
					add_query_arg( [ 'shield_users_idle' => 1 ], Services::WpGeneral()->getUrl_CurrentAdminPage() ),
					__( 'Idle', 'wp-simple-firewall' ), count( $idle )
				);
				add_filter( 'users_list_table_query_args', function ( $args ) use ( $manual ) {
					if ( is_array( $args ) && Services::Request()->query( 'shield_users_idle' ) ) {
						$args[ 'include' ] = $manual;
					}
					return $args;
				} );
			}

			if ( !empty( $passwords ) ) {
				$views[ 'shield_users_pass' ] = sprintf(
					'<a href="%s">%s <span class="count">(%s)</span></a>',
					add_query_arg( [ 'shield_users_pass' => 1 ], Services::WpGeneral()->getUrl_CurrentAdminPage() ),
					__( 'Password Expired', 'wp-simple-firewall' ), count( $passwords )
				);
				add_filter( 'users_list_table_query_args', function ( $args ) use ( $manual ) {
					if ( is_array( $args ) && Services::Request()->query( 'shield_users_pass' ) ) {
						$args[ 'include' ] = $manual;
					}
					return $args;
				} );
			}

			return $views;
		} );
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