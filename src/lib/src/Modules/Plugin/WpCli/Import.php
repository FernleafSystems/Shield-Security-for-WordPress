<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;
use WP_CLI;

class Import extends Base\WpCli\BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'import' ] ),
			[ $this, 'cmdImport' ]
		);
	}

	/**
	 * @param array $null
	 * @param array $aA
	 * @throws WP_CLI\ExitException
	 */
	public function cmdImport( array $null, array $aA ) {

		$sSource = isset( $aA[ 'source' ] ) ? $aA[ 'source' ] : '';
		if ( empty( $sSource ) ) {
			WP_CLI::error_multi_line(
				[
					__( 'Please provide the source site.', 'wp-simple-firewall' ),
					__( 'It must be running ShieldPRO and be configured to allow exports.', 'wp-simple-firewall' ),
					__( 'Use the `--source=` argument.', 'wp-simple-firewall' )
				]
			);
			WP_CLI::halt( 1 );
		}

		$sSecret = isset( $aA[ 'secret' ] ) ? $aA[ 'secret' ] : '';
		$bNetwork = isset( $aA[ 'set-slave' ] ) && $aA[ 'set-slave' ];
		if ( empty( $sSecret ) ) {
			WP_CLI::log( __( "No secret provided so we're proceeding as a whitelisted site.", 'wp-simple-firewall' ) );
			if ( $bNetwork ) {
				WP_CLI::error( __( "You have elected to set this site up as a slave without providing the `secret`.", 'wp-simple-firewall' ) );
			}
		}

		if ( !array_key_exists( 'force', $aA ) ) {
			WP_CLI::confirm( __( "Importing options will overwrite this site's Shield configuration. Are you sure?", 'wp-simple-firewall' ) );
		}
		try {
			( new Lib\ImportExport\Import() )
				->setMod( $this->getMod() )
				->fromSite( $sSource, $sSecret, $bNetwork );
		}
		catch ( \Exception $oE ) {
			WP_CLI::error_multi_line(
				[
					__( 'The import encountered an error.', 'wp-simple-firewall' ),
					$oE->getMessage(),
				]
			);
			WP_CLI::halt( $oE->getCode() );
		}

		WP_CLI::success( __( 'Plugin settings imported successfully.', 'wp-simple-firewall' ) );
	}
}