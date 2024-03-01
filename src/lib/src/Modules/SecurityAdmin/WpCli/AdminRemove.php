<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;
use WP_CLI;

class AdminRemove extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'admin-remove' ] ),
			[ $this, 'cmdAdminRemove' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Add a Security Admin user to the list of automatic sec-admins.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'uid',
					'optional'    => true,
					'description' => 'Administrator User ID.',
				],
				[
					'type'        => 'assoc',
					'name'        => 'username',
					'optional'    => true,
					'description' => 'Administrator username.',
				],
				[
					'type'        => 'assoc',
					'name'        => 'email',
					'optional'    => true,
					'description' => 'Administrator email address.',
				],
			],
		] ) );
	}

	/**
	 * @param array $null
	 * @param array $args
	 * @throws WP_CLI\ExitException
	 */
	public function cmdAdminRemove( array $null, array $args ) {

		if ( empty( $args ) ) {
			WP_CLI::error( 'Please specify the user for which you want to remove as a Security Admin.' );
		}
		if ( \count( $args ) > 1 ) {
			WP_CLI::error( 'Please specify only 1 way to identify a user.' );
		}

		$user = $this->loadUserFromArgs( $args );

		$current = self::con()->opts->optGet( 'sec_admin_users' );
		if ( !\in_array( $user->user_login, $current ) ) {
			WP_CLI::success( "This user isn't currently a security admin." );
		}
		else {
			unset( $current[ \array_search( $user->user_login, $current ) ] );
			\natsort( $current );
			self::con()->opts->optSet( 'sec_admin_users', \array_unique( $current ) );
			WP_CLI::success( sprintf( "User '%s' removed as a Security Admin.", $user->user_login ) );
		}
	}
}