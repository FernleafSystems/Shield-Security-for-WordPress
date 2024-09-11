<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\ImportDecisions;

class CrowdsecDebug extends CrowdsecBase {

	protected function cmdParts() :array {
		return [ 'debug' ];
	}

	protected function cmdShortDescription() :string {
		return 'Perform actions with pending CrowdSec signals.';
	}

	public function runCmd() :void {
		try {
			( new ImportDecisions() )->runImport();
//			( new Enroll() )->enroll();
		}
		catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
	}
}