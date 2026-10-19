<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

class BackupsOff extends BackupsBase {

	protected function cmdParts() :array {
		return [ 'off' ];
	}

	protected function cmdShortDescription() :string {
		return 'Switch off backups, refusing all backup service requests to this site.';
	}

	public function runCmd() :void {
		self::con()->comps->backups->setEnabled( false );
		\WP_CLI::success( __( 'Backups switched off. All backup service requests will be refused.', 'wp-simple-firewall' ) );
	}
}
