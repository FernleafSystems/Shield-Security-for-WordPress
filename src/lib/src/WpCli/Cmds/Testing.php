<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

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