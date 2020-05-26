<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;

class Base extends BaseWpCliCmd {

	/**
	 * @param array $aArgs
	 * @return true
	 * @throws \WP_CLI\ExitException
	 */
	protected function validateArg_List( array $aArgs ) {
		$sList = strtolower( isset( $aArgs[ 'list' ] ) ? $aArgs[ 'list' ] : '' );
		if ( empty( $sList ) ) {
			\WP_CLI::error_multi_line( [
					__( 'Please specify either the white or black list.', 'wp-simple-firewall' ),
					__( 'Use the `--list=` option.' )
				]
			);
			\WP_CLI::halt( 1 );
		}
		elseif ( !in_array( $sList, [ 'white', 'black' ] ) ) {
			\WP_CLI::error( __( 'The only option for `list` is either `white` or `black`.', 'wp-simple-firewall' ) );
		}

		return true;
	}
}