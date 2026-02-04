<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertAdmins;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors\{
	Base,
	Users
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\{
	Ops\Diff,
	SnapshotVO
};

class AlertHandlerAdmins extends AlertHandlerBase {

	public function alertAction() :string {
		return EmailInstantAlertAdmins::class;
	}

	public function alertDataKeys() :array {
		return [
			'added',
			'removed',
			'user_pass',
			'user_email',
			'promoted',
			'demoted',
		];
	}

	public function alertTitle() :string {
		return __( 'Admin Changes Detected', 'wp-simple-firewall' );
	}

	protected function run() {
		add_action( 'shield/pre_snapshot_update',
			/**
			 * @param Base|mixed $auditor
			 */
			function ( $auditor, SnapshotVO $current, SnapshotVO $previous ) {
				if ( $auditor::Slug() === Users::Slug() ) {
					$data = [];
					try {
						$diff = ( new Diff( $previous, $current ) )->run();
						if ( $diff->has_diffs ) {
							foreach ( [ 'added', 'removed' ] as $type ) {
								foreach ( $diff->{$type} as $user ) {
									if ( $user[ 'is_admin' ] ) {
										$data[ $type ] = \array_merge( $data[ $type ] ?? [], [ $user[ 'user_login' ] ] );
									}
								}
							}

							foreach ( $diff->changed as $change ) {
								if ( isset( $change[ 'old' ] ) && isset( $change[ 'new' ] ) ) {

									if ( $change[ 'old' ][ 'is_admin' ] || $change[ 'new' ][ 'is_admin' ] ) {
										$diff = \array_diff( $change[ 'old' ], $change[ 'new' ] );
										foreach ( [ 'user_pass', 'user_email' ] as $type ) {
											if ( isset( $diff[ $type ] ) ) {
												$data[ $type ] = \array_merge( $data[ $type ] ?? [], [ $change[ 'new' ][ 'user_login' ] ] );
											}
										}

										// admin has been demoted or promoted
										if ( isset( $diff[ 'is_admin' ] ) ) {
											$type = $change[ 'new' ][ 'is_admin' ] ? 'promoted' : 'demoted';
											$data[ $type ] = \array_merge( $data[ $type ] ?? [], [ $change[ 'new' ][ 'user_login' ] ] );
										}
									}
								}
							}

							foreach ( $data as $type => $items ) {
								$data[ $type ] = \array_unique( $items );
							}
							self::con()->comps->instant_alerts->updateAlertDataFor( $this, $data );
						}
					}
					catch ( \Exception $e ) {
					}
				}
			},
			10, 3
		);
	}
}