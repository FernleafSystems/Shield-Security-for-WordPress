<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\Actions;
use WP_CLI;

class Pin extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'pin' ] ),
			[ $this, 'cmdPin' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Set or remove the Security Admin PIN.',
			'synopsis'  => [
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
			],
		] ) );
	}

	/**
	 * @param array $null
	 * @param array $aA
	 * @throws WP_CLI\ExitException
	 */
	public function cmdPin( array $null, array $aA ) {

		$newPIN = isset( $aA[ 'set' ] ) ? $aA[ 'set' ] : null;
		$isRemove = WP_CLI\Utils\get_flag_value( $aA, 'remove', false );

		if ( !empty( $newPIN ) && !empty( $isRemove ) ) {
			WP_CLI::error( 'Please use either `--set` or `--remove`, but not both.' );
		}
		elseif ( empty( $newPIN ) && empty( $isRemove ) ) {
			WP_CLI::error( 'Please provide the desired action, either `--set` or `--remove`.' );
		}

		if ( $isRemove ) {
			( new Actions\RemoveSecAdmin() )
				->setMod( $this->getMod() )
				->remove();
			WP_CLI::success( __( 'Security admin pin removed.', 'wp-simple-firewall' ) );
		}
		else {
			try {
				( new Actions\SetSecAdminPin() )
					->setMod( $this->getMod() )
					->run( $newPIN );
				WP_CLI::success(
					sprintf( __( 'Security admin pin set to: "%s"', 'wp-simple-firewall' ), $newPIN )
				);
			}
			catch ( \Exception $e ) {
				WP_CLI::error_multi_line(
					[
						__( 'Setting Security admin pin failed.', 'wp-simple-firewall' ),
						$e->getMessage()
					]
				);
				WP_CLI::halt( 1 );
			}
		}
	}
}