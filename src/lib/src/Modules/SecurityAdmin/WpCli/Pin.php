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
	public function cmdPin( array $null, array$aA ) {

		$sNewPin = isset( $aA[ 'set' ] ) ? $aA[ 'set' ] : null;
		$bRemove = WP_CLI\Utils\get_flag_value( $aA, 'remove', false );

		if ( !empty( $sNewPin ) && !empty( $bRemove ) ) {
			WP_CLI::error( 'Please use either `--set` or `--remove`, but not both.' );
		}
		elseif ( empty( $sNewPin ) && empty( $bRemove ) ) {
			WP_CLI::error( 'Please provide the desired action, either `--set` or `--remove`.' );
		}

		if ( $bRemove ) {
			( new Actions\RemoveSecAdmin() )
				->setMod( $this->getMod() )
				->remove();
			WP_CLI::success( __( 'Security admin pin removed.', 'wp-simple-firewall' ) );
		}
		else {
			try {
				( new Actions\SetSecAdminPin() )
					->setMod( $this->getMod() )
					->run( $sNewPin );
				WP_CLI::success(
					sprintf( __( 'Security admin pin set to: "%s"', 'wp-simple-firewall' ), $sNewPin )
				);
			}
			catch ( \Exception $oE ) {
				WP_CLI::error_multi_line(
					[
						__( 'Setting Security admin pin failed.', 'wp-simple-firewall' ),
						$oE->getMessage()
					]
				);
				WP_CLI::halt( 1 );
			}
		}
	}
}