<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class WpCli {

	use ModConsumer;
	use \FernleafSystems\Utilities\Logic\OneTimeExecute;

	const MOD_COMMAND_KEY = '';

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
	}

	protected function run() {
		try {
			error_log( $this->getMod()->getModSlug() );
			$this->addDefaultCmds();
			$this->addCmds();
		}
		catch ( \Exception $oE ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	protected function addDefaultCmds() {
		\WP_CLI::add_command(
			$this->buildCmd( [ 'opt', 'set' ] ),
			[ $this, 'cmdOptSet' ]
		);
		\WP_CLI::add_command(
			$this->buildCmd( [ 'module', 'disable' ] ),
			[ $this, 'cmdModuleDisable' ]
		);
		\WP_CLI::add_command(
			$this->buildCmd( [ 'module', 'enable' ] ),
			[ $this, 'cmdModuleEnable' ]
		);
	}

	/**
	 * @param array $aParts
	 * @return string
	 */
	protected function buildCmd( array $aParts ) {
		return implode( ' ', array_merge( $this->getBaseCmdParts(), $aParts ) );
	}

	public function cmdModuleDisable( $aArgs, $aNamed ) {
		$this->getMod()
			 ->setIsMainFeatureEnabled( false )
			 ->saveModOptions();
		\WP_CLI::success( 'Module disabled.' );
	}

	public function cmdModuleEnable( $aArgs, $aNamed ) {
		$this->getMod()
			 ->setIsMainFeatureEnabled( false )
			 ->saveModOptions();
		\WP_CLI::success( 'Module enabled.' );
	}

	public function cmdOptSet( $aArgs, $aNamed ) {
		$aA = wp_parse_args(
			$aNamed,
			[
				'opt'   => '',
				'value' => '',
			]
		);

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
		if ( !in_array( $aA[ 'opt' ], $oOpts->getOptionsKeys() ) ) {
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
		foreach ( $this->getOptions()->getOptionsKeys() as $sOptKey ) {
			$aList[] = sprintf( '%s: %s', $sOptKey, $oStrings->getOptionStrings( $sOptKey )[ 'name' ] );
		}
		return $aList;
	}

	/**
	 * @return bool
	 */
	protected function canRun() {
		return $this->getCon()->isPremiumActive();
	}

	/**
	 * @return string[]
	 */
	protected function getBaseCmdParts() {
		return [ 'shield', $this->getBaseCmdKey() ];
	}

	/**
	 * @return string
	 */
	protected function getBaseCmdKey() {
		return strlen( static::MOD_COMMAND_KEY ) > 0 ?
			static::MOD_COMMAND_KEY
			: $this->getMod()->getModSlug( false );
	}
}