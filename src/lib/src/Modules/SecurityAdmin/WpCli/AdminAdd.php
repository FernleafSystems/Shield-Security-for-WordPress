<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;
use FernleafSystems\Wordpress\Services\Services;
use WP_CLI;

class AdminAdd extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'admin-add' ] ),
			[ $this, 'cmdAdminAdd' ], $this->mergeCommonCmdArgs( [
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
	public function cmdAdminAdd( array $null, array $args ) {

		if ( empty( $args ) ) {
			WP_CLI::error( 'Please specify the user for which you want to add as a Security Admin.' );
		}
		if ( \count( $args ) > 1 ) {
			WP_CLI::error( 'Please specify only 1 way to identify a user.' );
		}

		$user = $this->loadUserFromArgs( $args );

		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();
		$current = $opts->getSecurityAdminUsers();
		if ( \in_array( $user->user_login, $current ) ) {
			WP_CLI::success( "This user is already a security admin." );
		}
		elseif ( !Services::WpUsers()->isUserAdmin( $user ) ) {
			WP_CLI::error( "This user isn't a WordPress administrator." );
		}
		else {
			$current[] = $user->user_login;
			\natsort( $current );
			$opts->setOpt( 'sec_admin_users', \array_unique( $current ) );
			WP_CLI::success( sprintf( "User '%s' added as a Security Admin.", $user->user_login ) );
		}
	}
}