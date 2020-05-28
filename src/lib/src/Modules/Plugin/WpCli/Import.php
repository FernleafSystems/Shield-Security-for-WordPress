<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;
use FernleafSystems\Wordpress\Services\Services;
use WP_CLI;

class Import extends Base\WpCli\BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'import' ] ),
			[ $this, 'cmdImport' ], [
			'shortdesc' => 'Import configuration from another WP site running Shield',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'source',
					'optional'    => false,
					'description' => 'The URL of the source site or absolute path to import file.',
				],
				[
					'type'        => 'assoc',
					'name'        => 'site-secret',
					'optional'    => true,
					'default'     => null,
					'description' => 'The secret key on the source site. Not required if this site is already registered on the source site.',
				],
				[
					'type'        => 'assoc',
					'name'        => 'slave',
					'optional'    => true,
					'default'     => null,
					'options'     => [
						'add',
						'remove',
					],
					'description' => 'Add or remove this site as a registered slave (in the whitelist) on the source site. Secret is required to `add`.',
				],
				[
					'type'        => 'flag',
					'name'        => 'force',
					'optional'    => true,
					'description' => 'By-pass confirmation prompt.',
				],
				[
					'type'        => 'flag',
					'name'        => 'delete-file',
					'optional'    => true,
					'description' => 'Delete file after configurations have been imported.',
				],
			],
		] );
	}

	/**
	 * @param array $null
	 * @param array $aA
	 * @throws WP_CLI\ExitException
	 */
	public function cmdImport( array $null, array $aA ) {

		$sSource = isset( $aA[ 'source' ] ) ? $aA[ 'source' ] : '';
		if ( empty( $sSource ) ) {
			WP_CLI::error( __( 'Please use the `--source=` argument to provide the source site URL or path to file.', 'wp-simple-firewall' ) );
		}

		if ( !isset( $aA[ 'force' ] ) ) {
			WP_CLI::confirm( __( "Importing options will overwrite this site's Shield configuration. Are you sure?", 'wp-simple-firewall' ) );
		}

		try {
			if ( filter_var( $sSource, FILTER_VALIDATE_URL ) ) {
				$this->runImportFromSite( $aA );
			}
			else {
				$this->runImportFromFile( $sSource, $aA[ 'delete-file' ] );
			}
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

	/**
	 * @param string $sPath
	 * @param bool   $bDeleteFile
	 * @throws \Exception
	 */
	private function runImportFromFile( $sPath, $bDeleteFile = false ) {
		$oFS = Services::WpFs();
		if ( !$oFS->isFile( $sPath ) ) {
			throw new \Exception( "The source specified isn't a valid file." );
		}
		if ( !is_readable( $sPath ) ) {
			throw new \Exception( "Couldn't read the source file." );
		}

		( new Lib\ImportExport\Import() )
			->setMod( $this->getMod() )
			->fromFile( $sPath );

		if ( $bDeleteFile ) {
			$oFS->deleteFile( $sPath );
		}
	}

	/**
	 * @param array $aA
	 * @throws \Exception
	 */
	private function runImportFromSite( array $aA ) {

		$sSecret = isset( $aA[ 'site-secret' ] ) ? $aA[ 'site-secret' ] : '';
		$sSlave = isset( $aA[ 'slave' ] ) ? strtolower( $aA[ 'slave' ] ) : '';
		if ( empty( $sSecret ) ) {
			WP_CLI::log( __( "No secret provided so we assume we're a registered slave site.", 'wp-simple-firewall' ) );
			if ( $sSlave === 'add' ) {
				throw new \Exception( "You have elected to set this site up as a slave without providing the `site-secret`.", 'wp-simple-firewall' );
			}
		}

		( new Lib\ImportExport\Import() )
			->setMod( $this->getMod() )
			->fromSite(
				$aA[ 'source' ],
				$sSecret,
				$sSlave === 'add' ? true : ( $sSlave === 'remove' ? false : null )
			);
	}
}