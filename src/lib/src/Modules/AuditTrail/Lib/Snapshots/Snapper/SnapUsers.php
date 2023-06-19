<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Snapper;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots;
use FernleafSystems\Wordpress\Services\Services;

class SnapUsers extends BaseSnap {

	public function snap() :array {
		$users = [];
		foreach ( $this->getAdmins() as $admin ) {
			$admin[ 'is_admin' ] = true;
			$users[ $admin[ 'uniq' ] ] = $admin;
		}
		foreach ( $this->getNonAdmins() as $nonAdmin ) {
			$users[ $nonAdmin[ 'uniq' ] ] = $nonAdmin;
		}
		return $users;
	}

	private function getAdmins() :array {
		return $this->buildUsers( [ 'role__in' => [ 'administrator' ] ] );
	}

	private function getNonAdmins() :array {
		return $this->buildUsers( [ 'role__not_in' => [ 'administrator' ] ] );
	}

	private function buildUsers( array $params = [] ) :array {
		$params = Services::DataManipulation()->mergeArraysRecursive( [
			'number' => 50,
			'paged'  => 1,
			'fields' => [
				'id',
				'user_login',
				'user_pass',
				'user_email',
			],
		], $params );

		$actual = [];
		do {
			$result = get_users( $params );
			foreach ( $result as $user ) {
				/** @var \stdClass $user */
				$actual[] = [
					'uniq'       => (int)$user->id,
					'is_admin'   => false,
					'user_login' => $user->user_login,
					'user_pass'  => Snapshots\Hasher::Item( $user->user_pass ),
					'user_email' => Snapshots\Hasher::Item( $user->user_email ),
				];
			}
			$params[ 'paged' ]++;
		} while ( !empty( $result ) );

		return $actual;
	}

	/**
	 * @param \WP_User $item
	 */
	public function updateItemOnSnapshot( array $snapshotData, $item ) :array {
		if ( $item instanceof \WP_User ) {
			$snapshotData[ $item->ID ] = [
				'uniq'       => $item->ID,
				'is_admin'   => Services::WpUsers()->isUserAdmin( $item ),
				'user_login' => $item->user_login,
				'user_pass'  => Snapshots\Hasher::Item( $item->user_pass ),
				'user_email' => Snapshots\Hasher::Item( $item->user_email ),
			];
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
