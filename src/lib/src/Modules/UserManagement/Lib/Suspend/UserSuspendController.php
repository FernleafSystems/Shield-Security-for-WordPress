<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Users\ProfileSuspend;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\UserMeta\Ops\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class UserSuspendController {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return $this->isSuspendEnabled();
	}

	public function getSuspendAutoIdleTime() :int {
		return self::con()->opts->optGet( 'auto_idle_days' )*\DAY_IN_SECONDS;
	}

	public function getSuspendAutoIdleUserRoles() :array {
		return self::con()->opts->optGet( 'auto_idle_roles' );
	}

	public function isSuspendEnabled() :bool {
		return $this->isSuspendManualEnabled() || $this->isSuspendAutoIdleEnabled() || $this->isSuspendAutoPasswordEnabled();
	}

	public function isSuspendManualEnabled() :bool {
		return self::con()->opts->optIs( 'manual_suspend', 'Y' );
	}

	public function isSuspendAutoIdleEnabled() :bool {
		return $this->getSuspendAutoIdleTime() > 0 && \count( $this->getSuspendAutoIdleUserRoles() ) > 0;
	}

	public function isSuspendAutoPasswordEnabled() :bool {
		$con = self::con();
		return $con->comps->opts_lookup->isPassPoliciesEnabled()
			   && $con->opts->optIs( 'auto_password', 'Y' )
			   && $con->comps->opts_lookup->getPassExpireTimeout() > 0;
	}

	protected function run() {
		if ( !self::con()->this_req->request_bypasses_all_restrictions ) {
			if ( $this->isSuspendManualEnabled() ) {
				( new Suspended() )->execute();
			}
			if ( $this->isSuspendAutoIdleEnabled() ) {
				( new Idle() )->execute();
			}
			if ( $this->isSuspendAutoPasswordEnabled() ) {
				( new PasswordExpiry() )->execute();
			}
		}

		// User profile UI
		add_action( 'edit_user_profile', [ $this, 'addUserBlockOption' ], 1 );
		add_action( 'edit_user_profile_update', [ $this, 'handleUserSuspendOptionSubmit' ] );

		// Show suspended user list filters
		add_action( 'load-users.php', function () {

			$this->addSuspendedUserFilters();

			// Display manually suspended on the user list table; TODO: at auto suspended
			add_filter( 'shield/user_status_column', function ( array $content, \WP_User $user ) {

				$meta = self::con()->user_metas->for( $user );
				if ( $meta->record->hard_suspended_at > 0 ) {
					$content[] = sprintf( '<em>%s</em>: %s',
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

	public function canManuallySuspend() :bool {
		return Services::WpUsers()->isUserLoggedIn() &&
			   apply_filters( 'shield/user_suspend_can_manually_suspend', self::con()->isPluginAdmin(),
				   Services::WpUsers()->getCurrentWpUser() );
	}

	/**
	 * Sets-up all the UI filters necessary to provide manual user suspension
	 * filter the User Tables
	 */
	private function addSuspendedUserFilters() {
		$ts = Services::Request()->ts();

		/** @var Select $metaSelect */
		$metaSelect = self::con()->db_con->user_meta->getQuerySelector();

		$expireTimeout = self::con()->comps->opts_lookup->getPassExpireTimeout();

		$manual = $this->isSuspendManualEnabled() ? $metaSelect->reset()->filterByHardSuspended()->count() : 0;
		$passwords = $this->isSuspendAutoPasswordEnabled() ?
			$metaSelect->reset()->filterByPassExpired( $ts - $expireTimeout )->count() : 0;
		$idle = $this->isSuspendAutoPasswordEnabled() ?
			$metaSelect->reset()->filterByPassExpired( $ts - $this->getSuspendAutoIdleTime() )->count() : 0;

		if ( $manual + $passwords + $idle > 0 ) {
			// Filter the user list database query
			add_filter( 'users_list_table_query_args', function ( $args ) use ( $manual, $idle, $passwords ) {
				$req = Services::Request();
				$ts = Services::Request()->ts();

				if ( \is_array( $args ) ) {
					/** @var Select $metaSelect */
					$metaSelect = self::con()->db_con->user_meta->getQuerySelector();

					if ( $manual > 0 && $req->query( 'shield_users_suspended' ) ) {
						$filtered = true;
						$metaSelect->filterByHardSuspended();
					}
					elseif ( $idle > 0 && $req->query( 'shield_users_idle' ) ) {
						$filtered = true;
						$metaSelect->filterByPassExpired( $ts - self::con()->comps->opts_lookup->getPassExpireTimeout() );
					}
					elseif ( $passwords > 0 && $req->query( 'shield_users_pass' ) ) {
						$filtered = true;
						$metaSelect->filterByIdle( $ts - $this->getSuspendAutoIdleTime() );
					}
					else {
						$filtered = false;
					}

					if ( $filtered ) {
						$idsToInclude = \array_map(
							function ( $res ) {
								return (int)array_pop( $res );
							},
							$metaSelect->setResultsAsVo( false )
									   ->setSelectResultsFormat( ARRAY_A )
									   ->setColumnsToSelect( [ 'user_id' ] )
									   ->queryWithResult()
						);
						if ( !empty( $idsToInclude ) ) {
							$args[ 'include' ] = $idsToInclude;
						}
					}
				}
				return $args;
			} );

			// Provide the links above the table.
			add_filter( 'views_users', function ( $views ) use ( $manual, $idle, $passwords ) {
				$WP = Services::WpGeneral();
				if ( $manual > 0 ) {
					$views[ 'shield_users_suspended' ] = sprintf(
						'<a href="%s">%s <span class="count">(%s)</span></a>',
						URL::Build( $WP->getUrl_CurrentAdminPage(), [ 'shield_users_suspended' => 1 ] ),
						__( 'Manually Suspended', 'wp-simple-firewall' ), $manual
					);
				}

				if ( $idle > 0 ) {
					$views[ 'shield_idle_users' ] = sprintf(
						'<a href="%s">%s <span class="count">(%s)</span></a>',
						URL::Build( $WP->getUrl_CurrentAdminPage(), [ 'shield_users_idle' => 1 ] ),
						__( 'Idle', 'wp-simple-firewall' ), $idle
					);
				}

				if ( $passwords > 0 ) {
					$views[ 'shield_users_pass' ] = sprintf(
						'<a href="%s">%s <span class="count">(%s)</span></a>',
						URL::Build( $WP->getUrl_CurrentAdminPage(), [ 'shield_users_pass' => 1 ] ),
						__( 'Password Expired', 'wp-simple-firewall' ), $passwords
					);
				}

				return $views;
			} );
		}
	}

	public function addUserBlockOption( \WP_User $user ) {
		echo self::con()->action_router->render( ProfileSuspend::SLUG, [
			'user_id' => $user->ID,
		] );
	}

	public function handleUserSuspendOptionSubmit( int $uid ) {
		$user = Services::WpUsers()->getUserById( $uid );

		if ( $user instanceof \WP_User
			 && ( !Services::WpUsers()->isUserAdmin( $user ) || self::con()->isPluginAdmin() )
		) {
			$isSuspend = Services::Request()->post( 'shield_suspend_user' ) === 'Y';
			$this->addRemoveHardSuspendUser( $user, $isSuspend );
			if ( $isSuspend ) {
				\WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
			}
		}
	}

	public function addRemoveHardSuspendUser( \WP_User $user, bool $add = true ) {
		$con = self::con();
		$meta = $con->user_metas->for( $user );
		$isSuspended = $meta->record->hard_suspended_at > 0;

		if ( $add && !$isSuspended ) {
			$meta->record->hard_suspended_at = Services::Request()->ts();
			$con->fireEvent( 'user_hard_suspended', [
				'audit_params' => [
					'user_login' => $user->user_login,
					'admin'      => Services::WpUsers()->getCurrentWpUsername(),
				]
			] );
		}
		elseif ( !$add && $isSuspended ) {
			$meta->record->hard_suspended_at = 0;
			$con->fireEvent( 'user_hard_unsuspended', [
				'audit_params' => [
					'user_login' => $user->user_login,
					'admin'      => Services::WpUsers()->getCurrentWpUsername(),
				]
			] );
		}
	}
}