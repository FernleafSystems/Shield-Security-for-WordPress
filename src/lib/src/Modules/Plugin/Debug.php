<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET\BuildData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Api\DecisionsDownload;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\FileScanOptimiser;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Crowdsec\RetrieveScenarios;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\RunTests;
use FernleafSystems\Wordpress\Services\Utilities\File\Search\SearchFile;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Verify\Email;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class Debug extends Modules\Base\Debug {

	public function run() {
//		$this->testAAAA( 'fwdproxy-odn-017.fbsv.net' );
		$this->crowdsec();
		die( 'finish' );
	}

	private function iputil() {
		$address = \IPLib\Factory::parseAddressString( '90.250.11.231' );
		var_dump( $address->getRangeType() );
		$address = \IPLib\Factory::parseAddressString( '10.0.0.1' );
		var_dump( $address->getRangeType() );
		$address = \IPLib\Factory::parseAddressString( '127.0.0.1' );
		var_dump( $address->getRangeType() );

		$address = \IPLib\Factory::parseRangeString( '90.250.11.21' );
		var_dump( $address->asSubnet()->toString() );
		$address = \IPLib\Factory::parseRangeString( '90.250.11.21/32' );
		var_dump( $address->asSubnet()->toString() );
		$address = \IPLib\Factory::parseRangeString( '90.250.11.21/24' );
		var_dump( $address->asPattern()->toString() );
		$address = \IPLib\Factory::parseRangeString( '90.250.11.21/27' );
		var_dump( $address->asSubnet()->toString() );
		$address = \IPLib\Factory::parseRangeString( '90.250.11.21/10' );
		var_dump( $address->asSubnet()->toString() );
	}

	private function crowdsec() {
		$modIPs = $this->getCon()->getModule_IPs();
		$csCon = $modIPs->getCrowdSecCon();

		try {
			$res = $this->getCon()
						 ->getModule_License()
						 ->getLicenseHandler()
						 ->getLicense()->crowdsec[ 'scenarios' ] ?? [];
			$res = ( new Modules\IPs\Lib\CrowdSec\Decisions\CleanDecisions_IPs() )
				->setMod( $modIPs )
				->duplicates();
//			var_dump( $modIPs->getOptions()->getOpt('crowdsec_cfg') );
//			var_dump( $csCon->getApi()->getAuthStatus() );
//			var_dump( $csCon->getApi()->getAuthorizationToken() );
//			$csCon->getApi()->machineEnroll( false );
//			var_dump( $csCon->getApi()->getAuthStatus() );
//			var_dump( $modIPs->getCrowdSecCon()->cfg );
//			var_dump( $csCon->getApi()->getAuthorizationToken() );
//			( new Modules\IPs\Lib\CrowdSec\Signals\PushSignalsToCS() )
//				->setMod( $this->getCon()->getModule_IPs() )
//				->execute();
//			( new Modules\IPs\Lib\CrowdSec\Decisions\DownloadDecisionsUpdate() )
//				->setMod( $modIPs )
//				->execute();
//			$res = ( new DecisionsDownload( $csCon->getApi()->getAuthorizationToken() ) )->run();
//			$res = ( new RetrieveScenarios() )
//				->setMod( $this->getMod() )
//				->retrieve();
//			$res = ( new DecisionsDownload( $csCon->getApi()->getAuthorizationToken() ) )->run();
		}
		catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
		var_dump( $res ?? 'unset' );
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