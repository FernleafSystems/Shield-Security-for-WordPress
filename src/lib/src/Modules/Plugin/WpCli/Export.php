<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;
use FernleafSystems\Wordpress\Services\Services;
use WP_CLI;

class Export extends Base\WpCli\BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'export' ] ),
			[ $this, 'cmdExport' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Export configuration to file.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'file',
					'optional'    => false,
					'description' => 'The absolute or relative (to ABSPATH) path to the file for export.',
				],
				[
					'type'        => 'flag',
					'name'        => 'quiet',
					'optional'    => true,
					'description' => 'By-pass confirmation to overwrite files - files will be overwritten quietly.',
				],
			],
		] ) );
	}

	/**
	 * @param array $null
	 * @param array $aA
	 * @throws WP_CLI\ExitException
	 */
	public function cmdExport( array $null, array $aA ) {
		$oFS = Services::WpFs();

		$sFile = $aA[ 'file' ];
		$bQuiet = isset( $aA[ 'quiet' ] );
		if ( !path_is_absolute( $sFile ) ) {
			$sFile = path_join( ABSPATH, $sFile );
			WP_CLI::log( __( "The file you specified wasn't an absolute path, so we're using the following path to the export file:" ) );
		}
		WP_CLI::log( sprintf( '%s: %s', __( 'Export file path', 'wp-simple-firewall' ), $sFile ) );

		if ( $oFS->isDir( $sFile ) ) {
			WP_CLI::error( __( "The file path you've provide is an existing directory.", 'wp-simple-firewall' ) );
		}

		$dir = dirname( $sFile );
		if ( !$oFS->isDir( $dir ) ) {
			if ( !$bQuiet ) {
				WP_CLI::confirm( "The directory for the export file doesn't exist. Create it?" );
			}
			$oFS->mkdir( $sFile );
			if ( $oFS->mkdir( $sFile ) && !$oFS->isDir( $dir ) ) {
				WP_CLI::error( sprintf( __( "Couldn't create the directory: %s", 'wp-simple-firewall' ), $dir ) );
			}
		}

		if ( $oFS->isFile( $sFile ) && !$bQuiet ) {
			WP_CLI::confirm( "The export file already exists. Overwrite?" );
		}

		$oFS->touch( $sFile );
		if ( !$oFS->isFile( $sFile ) ) {
			WP_CLI::error( __( "Couldn't create the export file.", 'wp-simple-firewall' ) );
		}
		if ( !is_writable( $sFile ) ) {
			WP_CLI::error( __( "The system reports that this file path isn't writable.", 'wp-simple-firewall' ) );
		}

		$aData = ( new Lib\ImportExport\Export() )
			->setMod( $this->getMod() )
			->toStandardArray();
		if ( !$oFS->putFileContent( $sFile, implode( "\n", $aData ) ) ) {
			WP_CLI::error( __( "The system reports that writing the export file failed.", 'wp-simple-firewall' ) );
		}

		WP_CLI::success( __( 'Plugin configuration exported successfully.', 'wp-simple-firewall' ) );
	}
}