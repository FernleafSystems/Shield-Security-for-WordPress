<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Services\Services;

abstract class SecurityAdminBase extends BaseCmd {

	protected function getCmdBase() :array {
		return \array_merge( parent::getCmdBase(), [
			'secadmin'
		] );
	}

	/**
	 * @throws \WP_CLI\ExitException
	 */
	protected function loadUserFromArgs() :\WP_User {
		$WPU = Services::WpUsers();

		$user = null;
		if ( isset( $this->execCmdArgs[ 'uid' ] ) ) {
			$user = $WPU->getUserById( $this->execCmdArgs[ 'uid' ] );
		}
		elseif ( isset( $this->execCmdArgs[ 'email' ] ) ) {
			$user = $WPU->getUserByEmail( $this->execCmdArgs[ 'email' ] );
		}
		elseif ( isset( $this->execCmdArgs[ 'username' ] ) ) {
			$user = $WPU->getUserByUsername( $this->execCmdArgs[ 'username' ] );
		}

		if ( !$user instanceof \WP_User || $user->ID < 1 ) {
			\WP_CLI::error( "Couldn't find that user." );
		}

		return $user;
	}
}