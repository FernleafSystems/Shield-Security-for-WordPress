<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\RequestPolicy;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\{
	PolicyEvidence,
	PolicyEvidenceRecorder,
	PolicyState,
	PolicyStateRepository
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class PolicyEvidenceRecorderTest extends BaseUnitTest {

	public function testNonCriticalEvidenceDoesNotPersistSuspiciousRisk() :void {
		$state = $this->recorder()->record( '198.51.100.10', new PolicyEvidence( [
			'type'         => PolicyEvidence::TYPE_PROBE_ABUSE,
			'severity'     => PolicyEvidence::SEVERITY_SIGNAL,
			'source_event' => 'bottrack_404',
		] ) );

		$this->assertSame( PolicyState::BAND_NORMAL, $state->risk_band );
		$this->assertSame( 1, $state->counter( PolicyEvidence::TYPE_PROBE_ABUSE, '15m' ) );
		$this->assertSame( 'bottrack_404', $state->meta[ 'last_evidence' ][ PolicyEvidence::TYPE_PROBE_ABUSE ][ 'event' ] ?? null );
		$this->assertTrue( $state->dirty );
	}

	public function testCriticalEvidencePersistsHostileRisk() :void {
		$state = $this->recorder()->record( '198.51.100.11', new PolicyEvidence( [
			'type'         => PolicyEvidence::TYPE_RATE_ABUSE,
			'severity'     => PolicyEvidence::SEVERITY_CRITICAL,
			'source_event' => 'request_limit_exceeded',
		] ) );

		$this->assertSame( PolicyState::BAND_HOSTILE, $state->risk_band );
		$this->assertSame( 1, $state->counter( PolicyEvidence::TYPE_RATE_ABUSE, '15m' ) );
	}

	private function recorder() :PolicyEvidenceRecorder {
		return new PolicyEvidenceRecorderWithoutHooks( new PolicyEvidenceRecorderRepositoryStub() );
	}
}

class PolicyEvidenceRecorderWithoutHooks extends PolicyEvidenceRecorder {

	public function __construct( PolicyStateRepository $repository ) {
		$property = new \ReflectionProperty( PolicyEvidenceRecorder::class, 'repository' );
		$property->setAccessible( true );
		$property->setValue( $this, $repository );
	}

	protected function now() :int {
		return 1700000000;
	}
}

class PolicyEvidenceRecorderRepositoryStub extends PolicyStateRepository {

	private array $states = [];

	public function forIp( string $ip ) :PolicyState {
		return $this->states[ $ip ] ??= new PolicyState( [
			'ip' => $ip,
		] );
	}
}
