<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

/**
 * Internal testing command - not included in public WP-CLI documentation.
 * This command is for development/testing purposes only.
 */
class Testing extends BaseCmd {

	protected function cmdParts() :array {
		return [ 'testing' ];
	}

	protected function cmdShortDescription() :string {
		return 'Tests.';
	}

	public function runCmd() :void {
	}
}