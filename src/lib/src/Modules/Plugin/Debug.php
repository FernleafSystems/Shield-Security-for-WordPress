<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET\BuildData;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\FileScanOptimiser;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\RunTests;
use FernleafSystems\Wordpress\Services\Utilities\File\Search\SearchFile;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Verify\Email;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class Debug extends Modules\Base\Debug {

	public function run() {
//		$this->testAAAA( 'fwdproxy-odn-017.fbsv.net' );
		$this->cleanoptimisehashes();
		die( 'finish' );
	}

	private function cleanoptimisehashes() {
		( new FileScanOptimiser() )
			->setMod( $this->getMod() )
			->cleanStaleHashesOlderThan( 1654091228 );
	}

	private function testFileSearch() {
		$searcher = new SearchFile( $this->getCon()->root_file );
		var_dump( $searcher->exists( 'a' ) );
		var_dump( $searcher->multipleFindFirst( [ 'init', 'if' ] ) );
	}

	private function testAAAA( string $hostname ) {
		$id = ( new IpID( '2a03:2880:32ff:11::face:b00c', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)' ) )->run();
		var_dump( $id );
//		$record = dns_get_record( $hostname, DNS_AAAA );
//		var_dump( $record );
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