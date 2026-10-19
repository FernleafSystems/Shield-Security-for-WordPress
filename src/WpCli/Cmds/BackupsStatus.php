<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Services\Services;

class BackupsStatus extends BackupsBase {

	protected function cmdParts() :array {
		return [ 'status' ];
	}

	protected function cmdShortDescription() :string {
		return 'Show the backups switch, the backup service status, and the last backup.';
	}

	public function runCmd() :void {
		$backups = self::con()->comps->backups;
		$WP = Services::WpGeneral();

		\WP_CLI::log( sprintf( 'Backups: %s', $backups->isEnabled() ? 'on' : 'off' ) );
		\WP_CLI::log( sprintf( 'Backup service status: %s', $backups->isPausedRemotely() ? 'paused' : 'not paused' ) );

		$last = $backups->lastBackup();
		if ( empty( $last ) ) {
			\WP_CLI::log( 'Last backup: none recorded' );
		}
		else {
			\WP_CLI::log( sprintf( 'Last backup started: %s', $WP->getTimeStampForDisplay( $last[ 'started_at' ] ) ) );
			\WP_CLI::log( sprintf( 'Last backup finished: %s',
				empty( $last[ 'finished_at' ] ) ? '-' : $WP->getTimeStampForDisplay( $last[ 'finished_at' ] ) ) );
			\WP_CLI::log( sprintf( 'Last backup result: %s', $last[ 'result' ] ) );
			\WP_CLI::log( sprintf( 'Last backup duration: %ss', $last[ 'duration' ] ) );
		}
	}
}
