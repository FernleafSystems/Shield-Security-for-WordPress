<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertFileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;

class AlertHandlerFileLocker extends AlertHandlerBase {

	protected function canRun() :bool {
		return self::con()->comps->file_locker->isEnabled();
	}

	public function alertAction() :string {
		return EmailInstantAlertFileLocker::class;
	}

	public function alertDataKeys() :array {
		return [
			'filelocker'
		];
	}

	public function alertTitle() :string {
		return __( 'FileLocker Changes Detected', 'wp-simple-firewall' );
	}

	protected function run() {
		add_action( 'wp_loaded', function () {
			$data = [];
			foreach ( ( new LoadFileLocks() )->withProblemsNotNotified() as $lock ) {
				if ( self::con()->db_con->file_locker->getQueryUpdater()->markNotified( $lock ) ) {
					$data[ $lock->type ] = $lock->path;
				}
			}
			if ( !empty( $data ) ) {
				self::con()->comps->instant_alerts->updateAlertDataFor( $this, [ 'filelocker' => $data ] );
			}
		} );
	}
}