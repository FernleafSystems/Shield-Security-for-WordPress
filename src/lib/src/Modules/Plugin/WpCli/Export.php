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
			[ $this, 'cmdExport' ], [
			'shortdesc' => 'Export configuration to file.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'file',
					'optional'    => false,
					'description' => 'The absolute path to the file for export.',
				],
				[
					'type'        => 'assoc',
					'name'        => 'overwrite',
					'optional'    => true,
					'default'     => 'y',
					'option'      => [
						'y',
						'n'
					],
					'description' => 'Whether to overwrite the file if it already exists. Default behaviour will overwrite existing files.',
				],
			],
		] );
	}

	/**
	 * @param array $null
	 * @param array $aA
	 * @throws WP_CLI\ExitException
	 */
	public function cmdExport( array $null, array $aA ) {
		$oFS = Services::WpFs();

		$sFile = isset( $aA[ 'file' ] ) ? $aA[ 'file' ] : '';
		$bOverwrite = $aA[ 'overwrite' ] === 'y';

		if ( !path_is_absolute( $sFile ) ) {
			WP_CLI::error( __( "The path you've specified isn't an absolute path.", 'wp-simple-firewall' ) );
		}
		if ( $oFS->isFile( $sFile ) && !$bOverwrite ) {
			WP_CLI::error( __( "The path you've specified already exists.", 'wp-simple-firewall' ) );
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