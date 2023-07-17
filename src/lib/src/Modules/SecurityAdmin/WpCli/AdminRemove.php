<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;
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
	 * @param array $aA
	 * @throws WP_CLI\ExitException
	 */
	public function cmdAdminRemove( array $null, array $aA ) {

		if ( empty( $aA ) ) {
			WP_CLI::error( 'Please specify the user for which you want to remove as a Security Admin.' );
		}
		if ( \count( $aA ) > 1 ) {
			WP_CLI::error( 'Please specify only 1 way to identify a user.' );
		}

		$oU = $this->loadUserFromArgs( $aA );

		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();
		$current = $opts->getSecurityAdminUsers();
		if ( !\in_array( $oU->user_login, $current ) ) {
			WP_CLI::success( "This user isn't currently a security admin." );
		}
		else {
			unset( $current[ \array_search( $oU->user_login, $current ) ] );
			\natsort( $current );
			$opts->setOpt( 'sec_admin_users', \array_unique( $current ) );
			WP_CLI::success( sprintf( "User '%s' removed as a Security Admin.", $oU->user_login ) );
		}
	}
}