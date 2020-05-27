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
					'description' => 'The option key to update.',
				],
				[
					'type'        => 'assoc',
					'name'        => 'value',
					'optional'    => false,
					'description' => "The option's new value.",
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
		$this->getOptions()->setOpt( $aA[ 'opt' ], $aA[ 'value' ] );
		\WP_CLI::success( 'Option updated.' );
	}
}