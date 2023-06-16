<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack;
use FernleafSystems\Wordpress\Services\Services;

class SnapAdmins extends BaseZone {

	public const SLUG = 'admins';

	public function getZoneReporterClass() :string {
		return ChangeTrack\Report\ZoneReportAdmins::class;
	}

	/**
	 * @return array[] - key is user ID, values are arrays with keys: id, user_login, user_pass, user_email, is_admin
	 * @uses 2 SQL queries
	 */
	public function snap() :array {
		$adminIDs = \array_keys( $this->getAdmins() );
		return \array_map(
			function ( $user ) use ( $adminIDs ) {
				$user[ 'is_admin' ] = \in_array( $user[ 'uniq' ], $adminIDs );
				return $user;
			},
			$this->getUsers()
		);
	}

	private function getAdmins() :array {
		return $this->getUsers( [ 'role' => 'administrator' ] );
	}

	private function getUsers( array $params = [] ) :array {
		$WPU = Services::WpUsers();

		$params = Services::DataManipulation()->mergeArraysRecursive(
			[
				'fields' => $this->getFields(),
				'number' => 1,
				'paged'  => 1,
			],
			$params
		);

		$actual = [];
		do {
			$result = Services::WpUsers()->getAllUsers( $params );
			foreach ( $result as $user ) {
				/** @var \stdClass $user */
				$actual[ $user->id ] = [
					'uniq'       => $user->id,
					'is_admin'   => $WPU->isUserAdmin( $user->id ),
					'user_pass'  => ChangeTrack\Hasher::Item( $user->user_pass ),
					'user_email' => ChangeTrack\Hasher::Item( $user->user_email ),
				];
			}
			$params[ 'paged' ]++;
		} while ( !empty( $result ) );

		return $actual;
	}

	private function getFields() :array {
		return [
			'id',
			'user_pass',
			'user_email',
		];
	}
}
