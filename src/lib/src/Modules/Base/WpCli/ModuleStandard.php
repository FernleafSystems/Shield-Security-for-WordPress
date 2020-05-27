<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli;

class ModuleStandard extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		\WP_CLI::add_command(
			$this->buildCmd( [ 'opt', 'get' ] ),
			[ $this, 'cmdOptGet' ], [
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
		] );
		\WP_CLI::add_command(
			$this->buildCmd( [ 'opt', 'set' ] ),
			[ $this, 'cmdOptSet' ], [
			'shortdesc' => 'Enable, disable, or query the status of a module.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'key',
					'optional'    => false,
					'options'     => $this->getOptions()->getOptionsForWpCli(),
					'description' => 'The option key to set.',
				],
				[
					'type'        => 'assoc',
					'name'        => 'value',
					'optional'    => false,
					'description' => 'The option value.',
				],
			],
		] );

		\WP_CLI::add_command(
			$this->buildCmd( [ 'module' ] ),
			[ $this, 'cmdModAction' ], [
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
		] );
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
		\WP_CLI::log( sprintf( __( 'Current value: %s' ),
			$this->getOptions()->getOpt( $aA[ 'opt' ] ) ) );
	}

	public function cmdOptSet( array $null, array $aA ) {

		if ( is_null( @$aA[ 'value' ] ) ) {
			\WP_CLI::error(
				__( 'Please supply a value for the option.', 'wp-simple-firewall' )
			);
		}

		$this->getOptions()->setOpt( $aA[ 'opt' ], $aA[ 'value' ] );
		\WP_CLI::success( 'Option updated.' );
	}

	/**
	 * @param array $aA
	 * @throws \WP_CLI\ExitException
	 */
	private function commonOptCmdChecking( array $aA ) {

		if ( empty( $aA[ 'opt' ] ) ) {
			\WP_CLI::error_multi_line( array_merge(
				[
					__( 'Please provide an option key.', 'wp-simple-firewall' ),
					__( 'Possible option keys include:', 'wp-simple-firewall' ),
				],
				$this->listOptKeysForError()
			) );
			\WP_CLI::halt( 1 );
		}
		$oOpts = $this->getOptions();
		if ( !in_array( $aA[ 'opt' ], $oOpts->getOptionsForWpCli() ) ) {
			\WP_CLI::error_multi_line( array_merge(
				[
					sprintf( 'Not a valid option key for this module: "%s"', $aA[ 'opt' ] ),
					'Possible option keys are:'
				],
				$this->listOptKeysForError()
			) );
			\WP_CLI::halt( 2 );
		}
	}

	/**
	 * @return string[]
	 * @throws \Exception
	 */
	private function listOptKeysForError() {
		$aList = [];
		$oStrings = $this->getMod()->getStrings();
		foreach ( $this->getOptions()->getOptionsForWpCli() as $sOptKey ) {
			$aList[] = sprintf( '%s: %s', $sOptKey, $oStrings->getOptionStrings( $sOptKey )[ 'name' ] );
		}
		return $aList;
	}
}