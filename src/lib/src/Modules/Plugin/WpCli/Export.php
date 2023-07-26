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
					'name'        => 'force',
					'optional'    => true,
					'description' => 'Bypass confirmation to overwrite files and create necessary directories.',
				],
			],
		] ) );
	}

	/**
	 * @throws WP_CLI\ExitException
	 */
	public function cmdExport( array $null, array $args ) {
		$FS = Services::WpFs();

		$file = $args[ 'file' ];
		$isForce = $this->isForceFlag( $args );
		if ( !path_is_absolute( $file ) ) {
			$file = path_join( ABSPATH, $file );
			WP_CLI::log( __( "File provied wasn't an absolute path, so we're using the following path to the export file" ) );
		}
		WP_CLI::log( sprintf( '%s: %s', __( 'Export file path', 'wp-simple-firewall' ), $file ) );

		if ( $FS->isDir( $file ) ) {
			WP_CLI::error( __( "The file specified is an existing directory.", 'wp-simple-firewall' ) );
		}

		$dir = \dirname( $file );
		if ( !$FS->isDir( $dir ) ) {
			if ( !$isForce ) {
				WP_CLI::confirm( "The directory for the export file doesn't exist. Create it?" );
			}
			$FS->mkdir( $file );
			if ( $FS->mkdir( $file ) && !$FS->isDir( $dir ) ) {
				WP_CLI::error( sprintf( __( "Couldn't create the directory: %s", 'wp-simple-firewall' ), $dir ) );
			}
		}

		if ( $FS->isAccessibleFile( $file ) && !$isForce ) {
			WP_CLI::confirm( "The export file already exists. Overwrite?" );
		}

		$FS->touch( $file );
		if ( !$FS->isAccessibleFile( $file ) ) {
			WP_CLI::error( __( "Couldn't create the export file.", 'wp-simple-firewall' ) );
		}
		if ( !\is_writable( $file ) ) {
			WP_CLI::error( __( "The system reports that this file path isn't writable.", 'wp-simple-firewall' ) );
		}

		if ( !$FS->putFileContent( $file, \implode( "\n", ( new Lib\ImportExport\Export() )->toStandardArray() ) ) ) {
			WP_CLI::error( __( "The system reports that writing the export file failed.", 'wp-simple-firewall' ) );
		}

		WP_CLI::success( __( 'Plugin configuration exported successfully.', 'wp-simple-firewall' ) );
	}
}