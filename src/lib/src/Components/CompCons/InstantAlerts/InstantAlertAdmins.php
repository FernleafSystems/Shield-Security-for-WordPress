<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertAdmins;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors\{
	Base,
	Users
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\{
	Ops\Diff,
	SnapshotVO
};

class InstantAlertAdmins extends InstantAlertBase {

	public function __construct() {
		$this->alertActionData = [
			'added'      => [],
			'removed'    => [],
			'user_pass'  => [],
			'user_email' => [],
			'promoted'   => [],
			'demoted'    => [],
		];
	}

	protected function alertAction() :string {
		return EmailInstantAlertAdmins::class;
	}

	protected function alertTitle() :string {
		return __( 'Admin Changes Detected', 'wp-simple-firewall' );
	}

	protected function run() {
		parent::run();

		add_action( 'shield/pre_snapshot_update',
			/**
			 * @param Base|mixed $auditor
			 */
			function ( $auditor, SnapshotVO $current, SnapshotVO $previous ) {
				if ( $auditor::Slug() === Users::Slug() ) {
					try {
						$diff = ( new Diff( $previous, $current ) )->run();
						if ( $diff->has_diffs ) {
							foreach ( [ 'added', 'removed' ] as $type ) {
								foreach ( $diff->{$type} as $user ) {
									if ( $user[ 'is_admin' ] ) {
										$this->alertActionData[ $type ][] = $user[ 'user_login' ];
									}
								}
							}

							foreach ( $diff->changed as $change ) {
								if ( isset( $change[ 'old' ] ) && isset( $change[ 'new' ] ) ) {
									$diff = \array_diff( $change[ 'old' ], $change[ 'new' ] );
									foreach ( [ 'user_pass', 'user_email' ] as $type ) {
										if ( isset( $diff[ $type ] ) ) {
											$this->alertActionData[ $type ][] = $change[ 'new' ][ 'user_login' ];
										}
									}

									// admin has been demoted or promoted
									if ( isset( $diff[ 'is_admin' ] ) ) {
										$this->alertActionData[ $change[ 'new' ][ 'is_admin' ] ? 'promoted' : 'demoted' ][] = $change[ 'new' ][ 'user_login' ];
									}
								}
							}
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