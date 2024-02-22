<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;

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
					'options'     => $this->getOptionsForWpCli(),
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
					'options'     => $this->getOptionsForWpCli(),
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
		switch ( $args[ 'action' ] ) {
			case 'status':
				$this->mod()->isModOptEnabled() ?
					\WP_CLI::log( 'Module is currently enabled.' )
					: \WP_CLI::log( 'Module is currently disabled.' );
				break;
			case 'enable':
				$this->mod()->setIsMainFeatureEnabled( true );
				\WP_CLI::success( 'Module enabled.' );
				break;
			case 'disable':
				$this->mod()->setIsMainFeatureEnabled( false );
				\WP_CLI::success( 'Module disabled.' );
				break;
		}
		self::con()->opts->store();
	}

	public function cmdOptGet( array $null, array $args ) {
		$opts = self::con()->opts;

		$optKey = $args[ 'key' ];
		if ( !$opts->optExists( $optKey ) ) {
			\WP_CLI::log( __( 'Not a valid option key.', 'wp-simple-firewall' ) );
		}
		else {
			$value = $opts->optGet( $optKey );
			if ( !\is_numeric( $value ) && empty( $value ) ) {
				\WP_CLI::log( __( 'No value set.', 'wp-simple-firewall' ) );
			}
			else {
				$explain = '';

				if ( \is_array( $value ) ) {
					$value = sprintf( '[ %s ]', \implode( ', ', $value ) );
				}
				if ( $opts->optType( $optKey ) === 'checkbox' ) {
					$explain = sprintf( 'Note: %s', __( '"Y" = Turned On; "N" = Turned Off' ) );
				}

				\WP_CLI::log( sprintf( __( 'Current value: %s', 'wp-simple-firewall' ), $value ) );
				if ( !empty( $explain ) ) {
					\WP_CLI::log( $explain );
				}
			}
		}
	}

	public function cmdOptSet( array $null, array $args ) {
		self::con()->opts->optSet( $args[ 'key' ], $args[ 'value' ] );
		\WP_CLI::success( 'Option updated.' );
	}

	public function cmdOptList( array $null, array $args ) {
		$opts = self::con()->opts;
		$strings = new StringsOptions();
		$optsList = [];
		foreach ( $this->getOptionsForWpCli() as $key ) {
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
			if ( !\WP_CLI\Utils\get_flag_value( $args, 'full', false ) ) {
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
				$args[ 'format' ],
				$optsList,
				$allKeys
			);
		}
	}

	/**
	 * @return string[]
	 */
	protected function getOptionsForWpCli() :array {
		return \array_filter(
			\array_keys( self::con()->cfg->configuration->optsForModule( $this->mod() ) ),
			function ( $key ) {
				return empty( self::con()->opts->optDef( $key )[ 'hidden' ] );
			}
		);
	}
}