<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertFileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;

class InstantAlertFileLocker extends InstantAlertBase {

	public function __construct() {
		$this->alertActionData = [
			'filelocker' => []
		];
	}

	protected function canRun() :bool {
		return self::con()->comps->file_locker->isEnabled();
	}

	protected function alertAction() :string {
		return EmailInstantAlertFileLocker::class;
	}

	protected function alertTitle() :string {
		return __( 'FileLocker Changes Detected', 'wp-simple-firewall' );
	}

	protected function run() {
		parent::run();

		add_action( 'wp_loaded', function () {
			foreach ( ( new LoadFileLocks() )->withProblemsNotNotified() as $lock ) {
				if ( self::con()->db_con->file_locker->getQueryUpdater()->markNotified( $lock ) ) {
					$this->alertActionData[ 'filelocker' ][ $lock->type ] = $lock->path;
				}
			}
		} );
	}
}