<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

class BackupsOff extends BackupsBase {

	protected function cmdParts() :array {
		return [ 'off' ];
	}

	protected function cmdShortDescription() :string {
		return 'Switch off backups, refusing backup service requests that start or continue a backup.';
	}

	public function runCmd() :void {
		self::con()->comps->backups->setEnabled( false );
		\WP_CLI::success( __( 'Backups switched off. Backup service requests that start or continue a backup will be refused.', 'wp-simple-firewall' ) );
	}
}
