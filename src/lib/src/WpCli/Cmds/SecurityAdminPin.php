<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\{
	RemoveSecAdmin,
	SetSecAdminPin
};

class SecurityAdminPin extends SecurityAdminBase {

	protected function cmdParts() :array {
		return [ 'pin' ];
	}

	protected function cmdShortDescription() :string {
		return 'Set or remove the Security Admin PIN.';
	}

	protected function cmdSynopsis() :array {
		return [
			[
				'type'        => 'assoc',
				'name'        => 'set',
				'optional'    => true,
				'description' => 'Set a new Security Admin PIN.',
			],
			[
				'type'        => 'flag',
				'name'        => 'remove',
				'optional'    => true,
				'description' => 'Use this to remove any existing PIN.',
			],
		];
	}

	public function runCmd() :void {

		$newPIN = $this->execCmdArgs[ 'set' ] ?? null;
		$isRemove = \WP_CLI\Utils\get_flag_value( $this->execCmdArgs, 'remove', false );

		if ( !empty( $newPIN ) && !empty( $isRemove ) ) {
			\WP_CLI::error( 'Please use either `--set` or `--remove`, but not both.' );
		}
		elseif ( empty( $newPIN ) && empty( $isRemove ) ) {
			\WP_CLI::error( 'Please provide the desired action, either `--set` or `--remove`.' );
		}

		if ( $isRemove ) {
			( new RemoveSecAdmin() )->remove( true );
			\WP_CLI::success( __( 'Security admin pin removed.', 'wp-simple-firewall' ) );
		}
		else {
			try {
				( new SetSecAdminPin() )->run( $newPIN );
				\WP_CLI::success(
					sprintf( __( 'Security admin pin set to: "%s"', 'wp-simple-firewall' ), $newPIN )
				);
			}
			catch ( \Exception $e ) {
				\WP_CLI::error_multi_line(
					[
						__( 'Setting Security admin pin failed.', 'wp-simple-firewall' ),
						$e->getMessage()
					]
				);
				\WP_CLI::halt( 1 );
			}
		}
	}
}