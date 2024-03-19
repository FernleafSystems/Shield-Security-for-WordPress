<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Ops\ResetPlugin;

class PluginReset extends BaseCmd {

	protected function cmdParts() :array {
		return [ 'reset' ];
	}

	protected function cmdShortDescription() :string {
		return 'Reset the Shield plugin to default settings.';
	}

	protected function cmdSynopsis() :array {
		return [
			[
				'type'        => 'flag',
				'name'        => 'force',
				'optional'    => true,
				'description' => 'Bypass confirmation prompt.',
			],
		];
	}

	protected function runCmd() :void {
		if ( !$this->isForceFlag() ) {
			\WP_CLI::confirm( __( 'Are you sure you want to reset the Shield plugin to defaults?', 'wp-simple-firewall' ) );
		}
		( new ResetPlugin() )->run();
		\WP_CLI::success( __( 'Plugin reset to defaults.', 'wp-simple-firewall' ) );
	}
}