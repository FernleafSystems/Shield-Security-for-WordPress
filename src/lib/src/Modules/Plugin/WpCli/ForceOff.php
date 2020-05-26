<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\WpCli;

use FernleafSystems\Wordpress\Services\Services;
use WP_CLI;

class ForceOff extends Base {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'forceoff', 'state' ] ),
			[ $this, 'cmdForceoffQuery' ]
		);
		WP_CLI::add_command(
			$this->buildCmd( [ 'forceoff', 'create' ] ),
			[ $this, 'cmdForceoffCreate' ]
		);
		WP_CLI::add_command(
			$this->buildCmd( [ 'forceoff', 'delete' ] ),
			[ $this, 'cmdForceoffDelete' ]
		);
	}

	public function cmdForceoffQuery( $args, $aNamed ) {
		$sPath = path_join( $this->getCon()->getRootDir(), 'forceoff' );
		if ( Services::WpFs()->exists( $sPath ) ) {
			WP_CLI::log( '`forceoff` file is active.' );
		}
		else {
			WP_CLI::log( "`forceoff` file isn't active." );
		}
	}

	public function cmdForceoffCreate( $args, $aNamed ) {
		$oFS = Services::WpFs();
		$sPath = path_join( $this->getCon()->getRootDir(), 'forceoff' );

		$oFS->touch( $sPath );
		if ( $oFS->exists( $sPath ) ) {
			WP_CLI::success( '`forceoff` file created successfully.' );
		}
		else {
			WP_CLI::error( '`forceoff` file could not be created.' );
		}
	}

	public function cmdForceoffDelete( $args, $aNamed ) {
		$oFS = Services::WpFs();
		$sPath = path_join( $this->getCon()->getRootDir(), 'forceoff' );

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
	}
}