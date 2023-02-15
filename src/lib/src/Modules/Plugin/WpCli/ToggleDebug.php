<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Utilities\DebugMode;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;
use WP_CLI;

class ToggleDebug extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'forceoff' ] ),
			[ $this, 'cmdDebugMode' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Manage the debug mode.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'action',
					'options'     => [
						'enable',
						'disable',
						'query',
					],
					'optional'    => false,
					'description' => 'Action to take with the debug mode.',
				],
			],
		] ) );
	}

	/**
	 * @param       $null
	 * @param array $args
	 * @throws WP_CLI\ExitException
	 */
	public function cmdDebugMode( $null, $args ) {
		$debugMode = ( new DebugMode() )->setCon( $this->getCon() );

		switch ( $args[ 'action' ] ) {
			case 'query':
				if ( $debugMode->isActiveViaDefine() ) {
					WP_CLI::log( 'Debug mode is active using PHP constant.' );
				}
				elseif ( $debugMode->isActiveViaModeFile() ) {
					WP_CLI::log( 'Debug mode is active using mode file.' );
				}
				else {
					WP_CLI::log( "Debug mode isn't active." );
				}
				break;

			case 'enable':
				if ( $debugMode->isActiveViaModeFile() ) {
					WP_CLI::error( 'Debug mode is already enabled.' );
				}
				elseif ( $debugMode->enableViaFile() ) {
					WP_CLI::success( 'Debug mode enabled using mode file.' );
				}
				else {
					WP_CLI::error( "Debug mode couldn't be enabled - likely a filesystem error." );
				}
				break;

			case 'disable':
				if ( $debugMode->isActiveViaDefine() ) {
					WP_CLI::error( "Debug mode can't be disabled as it's enabled using a PHP constant." );
				}
				elseif ( !$debugMode->isActiveViaModeFile() ) {
					WP_CLI::success( "No change as debug mode isn't currently enabled." );
				}
				elseif ( $debugMode->disableViaFile() ) {
					WP_CLI::success( 'Debug mode disabled by removing mode file.' );
				}
				else {
					WP_CLI::error( "Debug mode couldn't be disabled - likely a filesystem error." );
				}
				break;
		}
	}
}