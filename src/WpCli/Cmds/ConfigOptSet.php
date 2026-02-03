<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

class ConfigOptSet extends ConfigBase {

	protected function cmdParts() :array {
		return [ 'opt-set' ];
	}

	protected function cmdShortDescription() :string {
		return 'Set the value of a configuration option.';
	}

	protected function cmdSynopsis() :array {
		return [
			[
				'type'        => 'assoc',
				'name'        => 'key',
				'optional'    => false,
				'options'     => $this->getOptionsForWpCli(),
				'description' => 'The option key to update.',
			],
			[
				'type'        => 'assoc',
				'name'        => 'value',
				'optional'    => false,
				'description' => "The option's new value.",
			],
		];
	}

	public function runCmd() :void {
		self::con()->opts->optSet( $this->execCmdArgs[ 'key' ], $this->execCmdArgs[ 'value' ] );
		\WP_CLI::success( 'Option updated.' );
	}
}