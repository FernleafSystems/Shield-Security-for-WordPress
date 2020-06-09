<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib\Ops\Terminate;
use WP_CLI;

class SessionTerminate extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'session', 'terminate' ] ),
			[ $this, 'cmdTerminate' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Terminate 1, some, or all user sessions.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'uid',
					'optional'    => true,
					'description' => 'Terminate all sessions for the given user ID.',
				],
				[
					'type'        => 'assoc',
					'name'        => 'username',
					'optional'    => true,
					'description' => 'Terminate all sessions for a user with the given username.',
				],
				[
					'type'        => 'assoc',
					'name'        => 'email',
					'optional'    => true,
					'description' => 'Terminate all sessions for a user with the given email address.',
				],
				[
					'type'        => 'flag',
					'name'        => 'all',
					'optional'    => true,
					'description' => 'Terminate all sessions.',
				],
				[
					'type'        => 'flag',
					'name'        => 'force',
					'optional'    => true,
					'description' => 'By-pass confirmation prompt.',
				],
			],
		] ) );
	}

	/**
	 * @param array $null
	 * @param array $aA
	 * @throws WP_CLI\ExitException
	 */
	public function cmdTerminate( $null, $aA ) {

		$bShowConfirm = true;
		if ( $this->isForceFlag( $aA ) ) {
			$bShowConfirm = false;
			unset( $aA[ 'force' ] );
		}

		if ( WP_CLI\Utils\get_flag_value( $aA, 'all', false ) ) {
			if ( $bShowConfirm ) {
				WP_CLI::confirm( 'This will logout all users. Are you sure?' );
			}
			$this->runTerminateAll();
			return;
		}

		if ( count( $aA ) === 0 ) {
			WP_CLI::error( 'Please specify the user for which you want to terminate sessions.' );
		}
		if ( count( $aA ) > 1 ) {
			WP_CLI::error( 'Please specify only 1 way to identify a user.' );
		}

		$oU = $this->loadUserFromArgs( $aA );

		if ( $bShowConfirm ) {
			WP_CLI::confirm( 'This will logout all session for this user. Are you sure?' );
		}

		$this->runTerminateByUser( $oU );
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