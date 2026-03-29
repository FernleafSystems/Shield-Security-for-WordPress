<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldNetApi;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET\BuildCanonicalEvidence;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET\BuildLegacySignals;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class IpTelemetryBuildersTest extends ShieldIntegrationTestCase {

	public function testCanonicalBuilderMapsSignalsIntoSparseEvidenceBuckets() :void {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );

		$now = Services::Request()->ts();
		TestDataFactory::insertBotSignal( '198.51.100.41', [
			'notbot_at'          => $now,
			'altcha_at'          => $now,
			'auth_at'            => $now,
			'btloginfail_at'     => $now,
			'btlogininvalid_at'  => $now,
			'bt404_at'           => $now,
			'btfake_at'          => $now,
			'btcheese_at'        => $now,
			'btinvalidscript_at' => $now,
			'btauthorfishing_at' => $now,
			'btxml_at'           => $now,
			'humanspam_at'       => $now,
			'markspam_at'        => $now,
			'ratelimit_at'       => $now,
			'firewall_at'        => $now,
			'offense_at'         => $now,
			'blocked_at'         => $now,
			'frontpage_at'       => $now,
			'loginpage_at'       => $now,
			'cooldown_at'        => $now,
			'unblocked_at'       => $now,
			'bypass_at'          => $now,
			'unmarkspam_at'      => $now,
		] );

		$data = ( new BuildCanonicalEvidence() )->build( true );

		$this->assertCount( 1, $data );
		$this->assertSame( '198.51.100.41', $data[ 0 ][ 'ip' ] );
		$this->assertSame( [
			'human_verified',
			'authenticated',
			'credential_attack',
			'recon',
			'xmlrpc_abuse',
			'spam',
			'enforcement',
		], $data[ 0 ][ 'evidence' ] );
	}

	public function testLegacyBuilderOnlySelectsUnsentOrChangedRows() :void {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );

		$now = Services::Request()->ts();
		$dbh = $this->requireController()->db_con->bot_signals;

		$eligibleId = TestDataFactory::insertBotSignal( '198.51.100.42', [
			'bt404_at'    => $now - 50,
			'updated_at'  => $now - 10,
			'snsent_at'   => $now - 20,
			'created_at'  => $now - 50,
		] );
		$unchangedId = TestDataFactory::insertBotSignal( '198.51.100.43', [
			'btfake_at'   => $now - 50,
			'updated_at'  => $now - 30,
			'snsent_at'   => $now - 10,
			'created_at'  => $now - 50,
		] );
		$unsentId = TestDataFactory::insertBotSignal( '198.51.100.44', [
			'auth_at'     => $now - 50,
			'updated_at'  => $now - 15,
			'snsent_at'   => 0,
			'created_at'  => $now - 50,
		] );

		$dbh->getQueryUpdater()->updateById( $eligibleId, [
			'updated_at' => $now - 10,
			'snsent_at'  => $now - 20,
		] );
		$dbh->getQueryUpdater()->updateById( $unchangedId, [
			'updated_at' => $now - 30,
			'snsent_at'  => $now - 10,
		] );
		$dbh->getQueryUpdater()->updateById( $unsentId, [
			'updated_at' => $now - 15,
			'snsent_at'  => 0,
		] );

		$data = ( new BuildLegacySignals() )->build( true );
		$ips = \array_column( $data, 'ip' );

		$this->assertContains( '198.51.100.42', $ips );
		$this->assertContains( '198.51.100.44', $ips );
		$this->assertNotContains( '198.51.100.43', $ips );
	}

	public function testBuildMarksSelectedRowsBeforeFilteringThemOut() :void {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );

		$id = TestDataFactory::insertBotSignal( '198.51.100.45', [] );

		$data = ( new BuildCanonicalEvidence() )->build();
		$record = $this->requireController()->db_con->bot_signals->getQuerySelector()->byId( $id );

		$this->assertSame( [], $data );
		$this->assertGreaterThan( 0, (int)$record->snsent_at );
	}
}
