<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Ops\ResetPlugin;

class PluginReset extends BaseCmd {

	protected function cmdParts() :array {
		return [ 'reset' ];
	}

	protected function cmdShortDescription() :string {
		return sprintf( 'Reset the %s plugin to default settings.', self::con()->labels->Name );
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
			\WP_CLI::confirm( sprintf( __( 'Are you sure you want to reset the %s plugin to defaults?', 'wp-simple-firewall' ), self::con()->labels->Name ) );
		}
		( new ResetPlugin() )->run();
		\WP_CLI::success( __( 'Plugin reset to defaults.', 'wp-simple-firewall' ) );
	}
}