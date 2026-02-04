<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

class SecurityAdminRemove extends SecurityAdminBase {

	protected function cmdParts() :array {
		return [ 'admin-remove' ];
	}

	protected function cmdShortDescription() :string {
		return 'Remove a Security Admin user from the list of automatic sec-admins.';
	}

	protected function cmdSynopsis() :array {
		return [
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
		];
	}

	public function runCmd() :void {

		if ( empty( $this->execCmdArgs ) ) {
			\WP_CLI::error( 'Please specify the user for which you want to remove as a Security Admin.' );
		}
		if ( \count( $this->execCmdArgs ) > 1 ) {
			\WP_CLI::error( 'Please specify only 1 way to identify a user.' );
		}

		$user = $this->loadUserFromArgs();

		$current = self::con()->opts->optGet( 'sec_admin_users' );
		if ( !\in_array( $user->user_login, $current ) ) {
			\WP_CLI::success( "This user isn't currently a security admin." );
		}
		else {
			unset( $current[ \array_search( $user->user_login, $current ) ] );
			\natsort( $current );
			self::con()->opts->optSet( 'sec_admin_users', \array_unique( $current ) );
			\WP_CLI::success( sprintf( "User '%s' removed as a Security Admin.", $user->user_login ) );
		}
	}
}