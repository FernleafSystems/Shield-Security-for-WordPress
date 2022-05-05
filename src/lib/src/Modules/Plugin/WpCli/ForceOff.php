<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;
use FernleafSystems\Wordpress\Services\Services;
use WP_CLI;

class ForceOff extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'forceoff' ] ),
			[ $this, 'cmdForceOff' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Manage the `forceoff` file.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'action',
					'options'     => [
						'create',
						'delete',
						'query',
					],
					'optional'    => false,
					'description' => 'Action to take with the `forceoff` file.',
				],
			],
		] ) );
	}

	/**
	 * @param $null
	 * @param $args
	 * @throws WP_CLI\ExitException
	 */
	public function cmdForceOff( $null, $args ) {
		$FS = Services::WpFs();
		$path = path_join( $this->getCon()->getRootDir(), 'forceoff' );

		switch ( $args[ 'action' ] ) {
			case 'query':
				if ( $FS->exists( $path ) ) {
					WP_CLI::log( '`forceoff` file is present.' );
				}
				else {
					WP_CLI::log( "`forceoff` file isn't present." );
				}
				break;

			case 'create':
				$FS->touch( $path );
				if ( $FS->exists( $path ) ) {
					WP_CLI::success( '`forceoff` file created successfully.' );
				}
				else {
					WP_CLI::error( '`forceoff` file could not be created.' );
				}
				break;

			case 'delete':
				if ( !$FS->exists( $path ) ) {
					WP_CLI::success( "`forceoff` doesn't exist." );
				}
				else {
					$FS->deleteFile( $path );
					if ( $FS->exists( $path ) ) {
						WP_CLI::error( "`forceoff` file couldn't be deleted." );
					}
					else {
						WP_CLI::success( '`forceoff` file deleted successfully.' );
					}
				}
				break;
		}
	}
}