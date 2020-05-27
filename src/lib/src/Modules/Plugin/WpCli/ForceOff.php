<?php

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
			[ $this, 'cmdForceOff' ], [
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
		] );
	}

	public function cmdForceOff( $null, $aA ) {
		$oFS = Services::WpFs();
		$sPath = path_join( $this->getCon()->getRootDir(), 'forceoff' );

		switch ( $aA[ 'action' ] ) {
			case 'query':
				if ( $oFS->exists( $sPath ) ) {
					WP_CLI::log( '`forceoff` file is present.' );
				}
				else {
					WP_CLI::log( "`forceoff` file isn't present." );
				}
				break;

			case 'create':
				$oFS->touch( $sPath );
				if ( $oFS->exists( $sPath ) ) {
					WP_CLI::success( '`forceoff` file created successfully.' );
				}
				else {
					WP_CLI::error( '`forceoff` file could not be created.' );
				}
				break;

			case 'delete':
				if ( !$oFS->exists( $sPath ) ) {
					WP_CLI::success( "`forceoff` doesn't exist." );
				}
				else {
					$oFS->deleteFile( $sPath );
					if ( $oFS->exists( $sPath ) ) {
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