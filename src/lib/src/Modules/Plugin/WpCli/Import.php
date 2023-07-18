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
			[ $this, 'cmdImport' ], $this->mergeCommonCmdArgs( [
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
					'description' => 'Bypass confirmation prompt.',
				],
				[
					'type'        => 'flag',
					'name'        => 'delete-file',
					'optional'    => true,
					'description' => 'Delete file after configurations have been imported.',
				],
			],
		] ) );
	}

	/**
	 * @throws WP_CLI\ExitException
	 */
	public function cmdImport( array $null, array $args ) {

		$source = $args[ 'source' ] ?? '';
		if ( empty( $source ) ) {
			WP_CLI::error( __( 'Please use the `--source=` argument to provide the source site URL or path to file.', 'wp-simple-firewall' ) );
		}

		if ( !$this->isForceFlag( $args ) ) {
			WP_CLI::confirm( __( "Importing options will overwrite this site's Shield configuration. Are you sure?", 'wp-simple-firewall' ) );
		}

		try {
			if ( \filter_var( $source, FILTER_VALIDATE_URL ) ) {

				$secret = $args[ 'site-secret' ] ?? '';
				$slave = isset( $args[ 'slave' ] ) ? \strtolower( $args[ 'slave' ] ) : '';
				if ( empty( $secret ) ) {
					WP_CLI::log( __( "No secret provided so we assume we're a registered slave site.", 'wp-simple-firewall' ) );
					if ( $slave === 'add' ) {
						throw new \Exception( "You have elected to set this site up as a slave without providing the `site-secret`.", 'wp-simple-firewall' );
					}
				}

				( new Lib\ImportExport\Import() )->fromSite(
					$source,
					(string)$secret,
					$slave === 'add' ? true : ( $slave === 'remove' ? false : null )
				);
			}
			else {
				( new Lib\ImportExport\Import() )->fromFile( $source, (bool)WP_CLI\Utils\get_flag_value( $args, 'delete-file', false ) );
			}
		}
		catch ( \Exception $e ) {
			WP_CLI::error_multi_line(
				[
					__( 'The import encountered an error.', 'wp-simple-firewall' ),
					$e->getMessage(),
				]
			);
			WP_CLI::halt( $e->getCode() );
		}

		WP_CLI::success( __( 'Plugin settings imported successfully.', 'wp-simple-firewall' ) );
	}
}