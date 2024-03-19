<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\Tables;

class ActivityLogPrint extends ActivityLogBase {

	public function cmdDisplay( array $null, array $a ) {
		( new Tables\Render\WpCliTable\ActivityLog() )->render();
	}

	protected function cmdShortDescription() :string {
		return 'Print the activity log';
	}

	protected function cmdParts() :array {
		return [ 'print' ];
	}

	protected function cmdSynopsis() :array {
		return [];
	}

	protected function runCmd() :void {
		( new Tables\Render\WpCliTable\ActivityLog() )->render();
	}
}