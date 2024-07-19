<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots;
use FernleafSystems\Wordpress\Services\Services;

class SnapUsers extends BaseSnap {

	public function snap() :array {
		$users = [];

		$adminLogins = [];
		foreach ( $this->getAdmins() as $admin ) {
			$admin[ 'is_admin' ] = true;
			$users[ $admin[ 'uniq' ] ] = $admin;
			$adminLogins[] = $admin[ 'user_login' ];
		}

		if ( !self::con()->comps->activity_log->flags()->users_audit_snapshot_admins_only ) {
			foreach ( $this->getNonAdmins( $adminLogins ) as $nonAdmin ) {
				$users[ $nonAdmin[ 'uniq' ] ] = $nonAdmin;
			}
		}
		return $users;
	}

	private function getAdmins() :array {
		return $this->buildUsers( [ 'role__in' => [ 'administrator' ] ] );
	}

	private function getNonAdmins( array $adminLogins = [] ) :array {
		return $this->buildUsers( [ 'login__not_in' => $adminLogins ] );
	}

	private function buildUsers( array $params = [] ) :array {
		$params = Services::DataManipulation()->mergeArraysRecursive( [
			'number'        => 250,
			'paged'         => 1,
			'fields'        => [
				'id',
				'user_login',
				'user_pass',
				'user_email',
			],
			'orderby'       => [ 'ID' => 'ASC' ],
			'cache_results' => false,
		], $params );

		$actual = [];
		do {
			$hasUser = false;
			foreach ( get_users( $params ) as $user ) {
				/** @var \stdClass $user */
				$actual[] = [
					'uniq'       => (int)$user->id,
					'is_admin'   => false,
					'user_login' => $user->user_login,
					'user_pass'  => Snapshots\Hasher::Item( $user->user_pass ),
					'user_email' => Snapshots\Hasher::Item( $user->user_email ),
				];
				$hasUser = true;
			}
			$params[ 'paged' ]++;
		} while ( $hasUser );

		return $actual;
	}

	/**
	 * @param \WP_User $item
	 */
	public function updateItemOnSnapshot( array $snapshotData, $item ) :array {
		if ( $item instanceof \WP_User ) {
			if ( Services::WpUsers()
						 ->isUserAdmin( $item ) || !self::con()->comps->activity_log->flags()->users_audit_snapshot_admins_only ) {
				$snapshotData[ $item->ID ] = [
					'uniq'       => $item->ID,
					'is_admin'   => Services::WpUsers()->isUserAdmin( $item ),
					'user_login' => $item->user_login,
					'user_pass'  => Snapshots\Hasher::Item( $item->user_pass ),
					'user_email' => Snapshots\Hasher::Item( $item->user_email ),
				];
			}
			else {
				unset( $snapshotData[ $item->ID ] );
			}
		}
		return $snapshotData;
	}

	/**
	 * @param \WP_User $item
	 */
	public function deleteItemOnSnapshot( array $snapshotData, $item ) :array {
		if ( $item instanceof \WP_User ) {
			unset( $snapshotData[ $item->ID ] );
		}
		return $snapshotData;
	}
}
