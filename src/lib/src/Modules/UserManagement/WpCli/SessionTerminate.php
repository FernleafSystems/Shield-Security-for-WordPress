<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;
use WP_CLI;

/**
 * @deprecated 15.0
 */
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
					'description' => 'Bypass confirmation prompt.',
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
	}

	/**
	 * @param \WP_User $oUser
	 * @throws WP_CLI\ExitException
	 */
	private function runTerminateByUser( $oUser ) {
	}

	private function runTerminateAll() {
	}
}