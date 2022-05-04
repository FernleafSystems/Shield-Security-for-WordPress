<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET\BuildData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\RunTests;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Verify\Email;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class Debug extends Modules\Base\Debug {

	public function run() {
		$this->checkIP( '66.249.79.9' );
		die( 'finish' );
	}

	private function checkIP( string $ip ) {
		$id = ( new IpID( $ip, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' ) )->run();
		var_dump( $id );
	}

	private function checkEmail( string $email ) {
		$apiToken = $this->getCon()
						 ->getModule_License()
						 ->getWpHashesTokenManager()
						 ->getToken();
		if ( !empty( $apiToken ) ) {
			$verifys = ( new Email( $apiToken ) )->getEmailVerification( $email );
			var_dump( $verifys );
		}
	}

	private function dbIntegrity() {
		( new Lib\Ops\VerifyDatabaseIntegrity() )
			->setCon( $this->getCon() )
			->run();
	}

	private function testbotsignals() {
		$r = ( new BuildData() )
			->setMod( $this->getCon()->getModule_IPs() )
			->build( true );
		var_dump( $r );
	}

	private function getIpRefs() {
		$ipRefs = $this->getCon()
					   ->getModule_Data()
					   ->getDbH_ReqLogs()
					   ->getQuerySelector()
					   ->getDistinctForColumn( 'ip_ref' );
		var_dump( $ipRefs );
	}

	private function tests() {
		( new RunTests() )
			->setCon( $this->getCon() )
			->run();
	}
}