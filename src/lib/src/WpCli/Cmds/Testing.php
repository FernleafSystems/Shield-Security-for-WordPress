<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

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

	private function testFacebookCrawler() :void {
		try {
			$id = ( new IpID( '2a03:2880:7ff:6::', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)' ) )->run();
			\WP_CLI::log( var_export( $id, true ) );
		}
		catch ( \Exception $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}
}