<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Restrictions;

use FernleafSystems\Wordpress\Services\Services;

class Users extends Base {

	protected function canRun() :bool {
		return self::con()->opts->optIs( 'admin_access_restrict_admin_users', 'Y' );
	}

	protected function run() {
		add_filter( 'editable_roles', [ $this, 'restrictEditableRoles' ], 100 );
		add_filter( 'user_has_cap', [ $this, 'restrictAdminUserChanges' ], 100, 3 );
		add_action( 'delete_user', [ $this, 'restrictAdminUserDelete' ], 100 );
		add_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100, 2 );
		add_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100, 2 );
		add_action( 'set_user_role', [ $this, 'restrictSetUserRole' ], 100, 3 );
	}

	/**
	 * @param int    $userId
	 * @param string $role
	 */
	public function restrictAddUserRole( $userId, $role ) {
		$WPU = Services::WpUsers();

		if ( $WPU->getCurrentWpUserId() !== $userId && \strtolower( $role ) === 'administrator' ) {
			$modifiedUser = $WPU->getUserById( $userId );

			remove_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100 );
			$modifiedUser->remove_role( 'administrator' );
			add_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100, 2 );
		}
	}

	/**
	 * @param int    $userId
	 * @param string $role
	 * @param array  $oldRoles
	 */
	public function restrictSetUserRole( $userId, $role, $oldRoles = [] ) {
		$WPU = Services::WpUsers();

		$role = \strtolower( (string)$role );
		if ( !\is_array( $oldRoles ) ) {
			$oldRoles = [];
		}

		if ( !empty( $role ) && $WPU->getCurrentWpUserId() !== (int)$userId ) {
			$newRoleIsAdmin = $role == 'administrator';

			// 1. Setting administrator role where it doesn't previously exist
			if ( $newRoleIsAdmin && !\in_array( 'administrator', $oldRoles ) ) {
				$revert = true;
			}
			// 2. Setting non-administrator role when previous roles included administrator
			elseif ( !$newRoleIsAdmin && \in_array( 'administrator', $oldRoles ) ) {
				$revert = true;
			}
			else {
				$revert = false;
			}

			if ( $revert ) {
				$modifiedUser = $WPU->getUserById( $userId );
				remove_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100 );
				remove_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100 );
				$modifiedUser->remove_role( $role );
				foreach ( $oldRoles as $preExistingRoles ) {
					$modifiedUser->add_role( $preExistingRoles );
				}
				add_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100, 2 );
				add_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100, 2 );
			}
		}
	}

	/**
	 * @param int    $userId
	 * @param string $role
	 */
	public function restrictRemoveUserRole( $userId, $role ) {
		$WPU = Services::WpUsers();

		if ( $WPU->getCurrentWpUserId() !== $userId && \strtolower( (string)$role ) === 'administrator' ) {
			$modifiedUser = $WPU->getUserById( $userId );

			remove_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100 );
			$modifiedUser->add_role( 'administrator' );
			add_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100, 2 );
		}
	}

	/**
	 * @param int $userID
	 */
	public function restrictAdminUserDelete( $userID ) {
		$WPU = Services::WpUsers();
		$userToDelete = $WPU->getUserById( $userID );
		if ( $userToDelete && $WPU->isUserAdmin( $userToDelete ) ) {
			Services::WpGeneral()
					->wpDie( __( 'Sorry, deleting administrators is currently restricted to your Security Admin', 'wp-simple-firewall' ) );
		}
	}

	/**
	 * @param array[] $roles
	 * @return array[]
	 */
	public function restrictEditableRoles( $roles ) {
		if ( \is_array( $roles ) && isset( $roles[ 'administrator' ] ) ) {
			unset( $roles[ 'administrator' ] );
		}
		return $roles;
	}

	/**
	 * This hooked function captures the attempts to modify the user role using the standard
	 * WordPress profile edit pages. It doesn't sufficiently capture the AJAX request to
	 * modify user roles. (see user role hooks)
	 * @param array $allCaps
	 * @param       $cap
	 * @param array $args
	 * @return array
	 */
	public function restrictAdminUserChanges( $allCaps, $cap, $args ) {
		/** @var string $userCap */
		$userCap = $args[ 0 ];

		if ( \in_array( $userCap, [ 'edit_users', 'create_users' ] ) ) {
			$blockCapability = false;

			$req = Services::Request();
			$WPU = Services::WpUsers();

			$requestedUser = false;
			$requestedUsername = $req->post( 'user_login' );

			if ( empty( $requestedUsername ) ) {
				$requestedUserId = $req->post( 'user_id' );
				if ( !empty( $requestedUserId ) ) {
					$requestedUser = $WPU->getUserById( $requestedUserId );
				}
			}
			else {
				$requestedUser = $WPU->getUserByUsername( $requestedUsername );
			}

			$requestedRole = \strtolower( (string)$req->post( 'role', '' ) );

			if ( $requestedUser instanceof \WP_User ) {
				// editing an existing user other than yourself?
				if ( $requestedUser->user_login != $WPU->getCurrentWpUsername() ) {

					if ( $WPU->isUserAdmin( $requestedUser ) || $requestedRole == 'administrator' ) {
						$blockCapability = true;
					}
				}
			}
			elseif ( $requestedRole == 'administrator' ) { //creating a new admin user?
				$blockCapability = true;
			}

			if ( $blockCapability ) {
				$allCaps[ $userCap ] = false;
			}
		}

		return $allCaps;
	}
}