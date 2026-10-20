<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

class BackupsOn extends BackupsBase {

	protected function cmdParts() :array {
		return [ 'on' ];
	}

	protected function cmdShortDescription() :string {
		return 'Switch on backups (the default).';
	}

	public function runCmd() :void {
		self::con()->comps->backups->setEnabled( true );
		\WP_CLI::success( __( 'Backups switched on.', 'wp-simple-firewall' ) );
	}
}
