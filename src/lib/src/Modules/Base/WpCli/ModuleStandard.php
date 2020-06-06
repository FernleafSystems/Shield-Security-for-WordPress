<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli;

class ModuleStandard extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		\WP_CLI::add_command(
			$this->buildCmd( [ 'opt', 'list' ] ),
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
			$this->buildCmd( [ 'opt', 'get' ] ),
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
			$this->buildCmd( [ 'opt', 'set' ] ),
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

	public function cmdModAction( $null, $aA ) {
		$oMod = $this->getMod();

		switch ( $aA[ 'action' ] ) {

			case 'status':
				$oMod->isModOptEnabled() ?
					\WP_CLI::log( 'Module is currently enabled.' )
					: \WP_CLI::log( 'Module is currently disabled.' );
				break;

			case 'enable':
				$this->getMod()
					 ->setIsMainFeatureEnabled( true )
					 ->saveModOptions();
				\WP_CLI::success( 'Module enabled.' );
				break;

			case 'disable':
				$this->getMod()
					 ->setIsMainFeatureEnabled( false )
					 ->saveModOptions();
				\WP_CLI::success( 'Module disabled.' );
				break;
		}
	}

	/**
	 * @param array $null
	 * @param array $aA
	 */
	public function cmdOptGet( array $null, array $aA ) {
		$oOpts = $this->getOptions();

		$mVal = $oOpts->getOpt( $aA[ 'key' ], $null );
		$aOpt = $oOpts->getRawData_SingleOption( $aA[ 'key' ] );
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

	/**
	 * @param array $null
	 * @param array $aA
	 */
	public function cmdOptSet( array $null, array $aA ) {
		$this->getOptions()->setOpt( $aA[ 'key' ], $aA[ 'value' ] );
		\WP_CLI::success( 'Option updated.' );
	}

	public function cmdOptList( array $null, array $aA ) {
		$oOpts = $this->getOptions();
		$oStrings = $this->getMod()->getStrings();
		$aOpts = [];
		foreach ( $oOpts->getOptionsForWpCli() as $sKey ) {
			try {
				$aOpts[] = [
					'key'     => $sKey,
					'name'    => $oStrings->getOptionStrings( $sKey )[ 'name' ],
					'type'    => $oOpts->getOptionType( $sKey ),
					'current' => $oOpts->getOpt( $sKey ),
					'default' => $oOpts->getOptDefault( $sKey ),
				];
			}
			catch ( \Exception $e ) {
			}
		}

		if ( empty( $aOpts ) ) {
			\WP_CLI::log( "This module doesn't have any configurable options." );
		}
		else {
			if ( !isset( $aA[ 'full' ] ) ) {
				$aKeys = [
					'key',
					'name',
					'current'
				];
			}
			else {
				$aKeys = array_keys( $aOpts[ 0 ] );
			}

			\WP_CLI\Utils\format_items(
				$aA[ 'format' ],
				$aOpts,
				$aKeys
			);
		}
	}
}