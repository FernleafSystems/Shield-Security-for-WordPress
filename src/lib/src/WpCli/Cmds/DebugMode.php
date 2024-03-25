<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

class DebugMode extends BaseCmd {

	protected function cmdParts() :array {
		return [ 'debug-mode' ];
	}

	protected function cmdSynopsis() :array {
		return [
			[
				'type'        => 'assoc',
				'name'        => 'action',
				'options'     => [
					'enable',
					'disable',
					'status',
				],
				'optional'    => false,
				'description' => 'Action to take with the debug mode.',
			],
		];
	}

	protected function cmdShortDescription() :string {
		return 'Manage the debug mode.';
	}

	public function runCmd() :void {
		$debugMode = new \FernleafSystems\Wordpress\Plugin\Shield\Controller\Modes\DebugMode();

		switch ( $this->execCmdArgs[ 'action' ] ) {
			case 'enable':
				if ( $debugMode->isActiveViaModeFile() ) {
					\WP_CLI::error( 'Debug mode is already enabled.' );
				}
				elseif ( $debugMode->enableViaFile() ) {
					\WP_CLI::success( 'Debug mode enabled using mode file.' );
				}
				else {
					\WP_CLI::error( "Debug mode couldn't be enabled - likely a filesystem error." );
				}
				break;
			case 'disable':
				if ( $debugMode->isActiveViaDefine() ) {
					\WP_CLI::error( "Debug mode can't be disabled as it's enabled using a PHP constant." );
				}
				elseif ( !$debugMode->isActiveViaModeFile() ) {
					\WP_CLI::success( "No change as debug mode isn't currently enabled." );
				}
				elseif ( $debugMode->disableViaFile() ) {
					\WP_CLI::success( 'Debug mode disabled by removing mode file.' );
				}
				else {
					\WP_CLI::error( "Debug mode couldn't be disabled - likely a filesystem error." );
				}
				break;
			case 'status':
			default:
				if ( $debugMode->isActiveViaDefine() ) {
					\WP_CLI::log( 'Debug mode is active using PHP constant.' );
				}
				elseif ( $debugMode->isActiveViaModeFile() ) {
					\WP_CLI::log( 'Debug mode is active using mode file.' );
				}
				else {
					\WP_CLI::log( "Debug mode isn't active." );
				}
				break;
		}
	}
}