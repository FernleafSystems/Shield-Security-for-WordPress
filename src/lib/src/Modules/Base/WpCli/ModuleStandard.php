<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli;

class ModuleStandard extends BaseWpCliCmd {

	protected function addCmds() {
		\WP_CLI::add_command(
			$this->buildCmd( [ 'opt-list' ] ),
			[ $this, 'cmdOptList' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'List the option keys and their names.',
			'synopsis'  => [
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
			],
		] ) );

		\WP_CLI::add_command(
			$this->buildCmd( [ 'opt-get' ] ),
			[ $this, 'cmdOptGet' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Enable, disable, or query the status of a module.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'key',
					'optional'    => false,
					'options'     => $this->getOptions()->getOptionsForWpCli(),
					'description' => 'The option key to get.',
				],
			],
		] ) );

		\WP_CLI::add_command(
			$this->buildCmd( [ 'opt-set' ] ),
			[ $this, 'cmdOptSet' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Enable, disable, or query the status of a module.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'key',
					'optional'    => false,
					'options'     => $this->getOptions()->getOptionsForWpCli(),
					'description' => 'The option key to updateModuleStandard.php
					.',
				],
				[
					'type'        => 'assoc',
					'name'        => 'value',
					'optional'    => false,
					'description' => "The option's new value.",
				],
			],
		] ) );

		\WP_CLI::add_command(
			$this->buildCmd( [ 'module' ] ),
			[ $this, 'cmdModAction' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Enable, disable, or query the status of a module.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'action',
					'optional'    => false,
					'options'     => [
						'status',
						'enable',
						'disable',
					],
					'description' => 'The action to perform on the module.',
				],
			],
		] ) );
	}

	public function cmdModAction( $null, $args ) {
		$oMod = $this->mod();

		switch ( $args[ 'action' ] ) {

			case 'status':
				$oMod->isModOptEnabled() ?
					\WP_CLI::log( 'Module is currently enabled.' )
					: \WP_CLI::log( 'Module is currently disabled.' );
				break;

			case 'enable':
				$this->mod()
					 ->setIsMainFeatureEnabled( true )
					 ->saveModOptions();
				\WP_CLI::success( 'Module enabled.' );
				break;

			case 'disable':
				$this->mod()
					 ->setIsMainFeatureEnabled( false )
					 ->saveModOptions();
				\WP_CLI::success( 'Module disabled.' );
				break;
		}
	}

	/**
	 * @param array $null
	 * @param array $args
	 */
	public function cmdOptGet( array $null, array $args ) {
		$opts = $this->getOptions();

		$mVal = $opts->getOpt( $args[ 'key' ], $null );
		$aOpt = $opts->getOptDefinition( $args[ 'key' ] );
		if ( !is_numeric( $mVal ) && empty( $mVal ) ) {
			\WP_CLI::log( __( 'No value set.', 'wp-simple-firewall' ) );
		}
		else {
			$sExplain = '';

			if ( is_array( $mVal ) ) {
				$mVal = sprintf( '[ %s ]', implode( ', ', $mVal ) );
			}

			if ( $aOpt[ 'type' ] === 'checkbox' ) {
				$sExplain = sprintf( 'Note: %s', __( '"Y" = Turned On; "N" = Turned Off' ) );
			}

			\WP_CLI::log( sprintf( __( 'Current value: %s', 'wp-simple-firewall' ), $mVal ) );
			if ( !empty( $sExplain ) ) {
				\WP_CLI::log( $sExplain );
			}
		}
	}

	public function cmdOptSet( array $null, array $args ) {
		$this->getOptions()->setOpt( $args[ 'key' ], $args[ 'value' ] );
		\WP_CLI::success( 'Option updated.' );
	}

	public function cmdOptList( array $null, array $args ) {
		$opts = $this->getOptions();
		$strings = $this->mod()->getStrings();
		$optsList = [];
		foreach ( $opts->getOptionsForWpCli() as $key ) {
			try {
				$optsList[] = [
					'key'     => $key,
					'name'    => $strings->getOptionStrings( $key )[ 'name' ],
					'type'    => $opts->getOptionType( $key ),
					'current' => $opts->getOpt( $key ),
					'default' => $opts->getOptDefault( $key ),
				];
			}
			catch ( \Exception $e ) {
			}
		}

		if ( empty( $optsList ) ) {
			\WP_CLI::log( "This module doesn't have any configurable options." );
		}
		else {
			if ( !\WP_CLI\Utils\get_flag_value( $args, 'full', false ) ) {
				$allKeys = [
					'key',
					'name',
					'current'
				];
			}
			else {
				$allKeys = array_keys( $optsList[ 0 ] );
			}

			\WP_CLI\Utils\format_items(
				$args[ 'format' ],
				$optsList,
				$allKeys
			);
		}
	}
}