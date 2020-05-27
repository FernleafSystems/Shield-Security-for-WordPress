<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib\Ops\Terminate;
use FernleafSystems\Wordpress\Services\Services;
use WP_CLI;

class SessionOps extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'terminate' ] ),
			[ $this, 'cmdTerminate' ]
		);
	}

	/**
	 * @param array $null
	 * @param array $aArgs
	 * @throws WP_CLI\ExitException
	 */
	public function cmdTerminate( $null, $aArgs ) {
		$oWpUsers = Services::WpUsers();

		if ( !array_key_exists( 'all', $aArgs )
			 && !array_key_exists( 'user_id', $aArgs ) && !array_key_exists( 'user_login', $aArgs ) ) {
			WP_CLI::error_multi_line(
				[
					"Please provide a user.",
					"Use `--all`, `--user_id=` or `--user_login=`.",
				]
			);
			WP_CLI::halt( 1 );
		}

		WP_CLI::confirm( 'This will logout all affected users. Are you sure?' );

		if ( array_key_exists( 'all', $aArgs ) ) {
			$this->runTerminateAll();
		}
		elseif ( !empty( $aArgs[ 'user_id' ] ) ) {
			$this->runTerminateByUser( $oWpUsers->getUserById( $aArgs[ 'user_id' ] ) );
		}
		elseif ( !empty( $aArgs[ 'user_login' ] ) ) {
			$this->runTerminateByUser( $oWpUsers->getUserByUsername( $aArgs[ 'user_login' ] ) );
		}
	}

	/**
	 * @param \WP_User $oUser
	 * @throws WP_CLI\ExitException
	 */
	private function runTerminateByUser( $oUser ) {
		if ( empty( $oUser ) ) {
			WP_CLI::error( "User doesn't exist." );
		}
		( new Terminate() )
			->setMod( $this->getMod() )
			->byUsername( $oUser->user_login );
		WP_CLI::success(
			__( 'All sessions for user have been terminated.', 'wp-simple-firewall' )
		);
	}

	private function runTerminateAll() {
		( new Terminate() )
			->setMod( $this->getMod() )
			->all();
		WP_CLI::success(
			__( 'All user sessions have been terminated.', 'wp-simple-firewall' )
		);
	}
}