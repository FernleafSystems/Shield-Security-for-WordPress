<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;

class ConfigOptsList extends ConfigBase {

	protected function cmdParts() :array {
		return [ 'opt-list' ];
	}

	protected function cmdShortDescription() :string {
		return 'List all the option keys and their names and current assignments (may also filter by module).';
	}

	protected function cmdSynopsis() :array {
		return [
			[
				'type'        => 'assoc',
				'name'        => 'format',
				'optional'    => true,
				'options'     => [
					'table',
					'json',
					'yaml',
					'csv',
				],
				'default'     => 'table',
				'description' => 'Display all the option details.',
			],
			[
				'type'        => 'flag',
				'name'        => 'full',
				'optional'    => true,
				'description' => 'Display all the option details.',
			],
		];
	}

	public function runCmd() :void {
		$opts = self::con()->opts;
		$strings = new StringsOptions();
		$optsList = [];
		foreach ( $this->getOptionsForWpCli( $this->execCmdArgs[ 'module' ] ?? null ) as $key ) {
			try {
				$optsList[] = [
					'key'     => $key,
					'name'    => $strings->getFor( $key )[ 'name' ],
					'type'    => $opts->optType( $key ),
					'current' => $opts->optGet( $key ),
					'default' => $opts->optDefault( $key ),
				];
			}
			catch ( \Exception $e ) {
			}
		}

		if ( empty( $optsList ) ) {
			\WP_CLI::log( "This module doesn't have any configurable options." );
		}
		else {
			if ( !\WP_CLI\Utils\get_flag_value( $this->execCmdArgs, 'full', false ) ) {
				$allKeys = [
					'key',
					'name',
					'current'
				];
			}
			else {
				$allKeys = \array_keys( $optsList[ 0 ] );
			}

			\WP_CLI\Utils\format_items(
				$this->execCmdArgs[ 'format' ],
				$optsList,
				$allKeys
			);
		}
	}
}