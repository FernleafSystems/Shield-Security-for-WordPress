<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli;

class ModuleStandard extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		\WP_CLI::add_command(
			$this->buildCmd( [ 'opt', 'get' ] ),
			[ $this, 'cmdOptGet' ]
		);
		\WP_CLI::add_command(
			$this->buildCmd( [ 'opt', 'set' ] ),
			[ $this, 'cmdOptSet' ]
		);
		\WP_CLI::add_command(
			$this->buildCmd( [ 'module', 'disable' ] ),
			[ $this, 'cmdModDisable' ]
		);
		\WP_CLI::add_command(
			$this->buildCmd( [ 'module', 'enable' ] ),
			[ $this, 'cmdModEnable' ]
		);
	}

	public function cmdModDisable( $aArgs, $aNamed ) {
		\WP_CLI::log( $this->getMod()->getSlug() );
		$this->getMod()
			 ->setIsMainFeatureEnabled( false )
			 ->saveModOptions();
		\WP_CLI::success( 'Module disabled.' );
	}

	public function cmdModEnable( $aArgs, $aNamed ) {
		$this->getMod()
			 ->setIsMainFeatureEnabled( false )
			 ->saveModOptions();
		\WP_CLI::success( 'Module enabled.' );
	}

	/**
	 * @param $aArgs
	 * @param $aNamed
	 * @throws \WP_CLI\ExitException
	 */
	public function cmdOptGet( $aArgs, $aNamed ) {
		$this->commonOptCmdChecking( $aNamed );
		\WP_CLI::log( sprintf( __( 'Current value: %s' ),
			$this->getOptions()->getOpt( $aNamed[ 'opt' ] ) ) );
	}

	public function cmdOptSet( $aArgs, $aNamed ) {

		$this->commonOptCmdChecking( $aNamed );
		if ( is_null( @$aNamed[ 'value' ] ) ) {
			\WP_CLI::error(
				__( 'Please supply a value for the option.', 'wp-simple-firewall' )
			);
		}

		$this->getOptions()->setOpt( $aNamed[ 'opt' ], $aNamed[ 'value' ] );
		\WP_CLI::success( 'Option updated.' );
	}

	private function setOption( $sKey, $mValue ) {

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