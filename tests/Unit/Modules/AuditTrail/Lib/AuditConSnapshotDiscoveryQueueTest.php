<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\AuditTrail\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Snapshots\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\AuditCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Snapshots\Queues\SnapshotDiscovery;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;

class AuditConSnapshotDiscoveryQueueTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	public function testNonPrimeDiscoveryQueuesAllAuditorsWithoutSnapshotLookups() :void {
		$subject = new TestAuditCon( [
			new ExistingSnapshotAuditor(),
			new MissingSnapshotAuditor(),
		], [
			ExistingSnapshotAuditor::Slug(),
		] );
		$queue = $this->runSnapshotDiscovery( $subject, false );

		$this->assertSame( [
			ExistingSnapshotAuditor::Slug(),
			MissingSnapshotAuditor::Slug(),
		], $queue->queued );
		$this->assertSame( [], $subject->snapshotLookups );
		$this->assertSame( 1, $queue->saveCount );
		$this->assertSame( 1, $queue->dispatchCount );
	}

	public function testDataPrimeDiscoveryQueuesOnlyAuditorsWithMissingSnapshots() :void {
		$subject = new TestAuditCon( [
			new ExistingSnapshotAuditor(),
			new MissingSnapshotAuditor(),
		], [
			ExistingSnapshotAuditor::Slug(),
		] );
		$queue = $this->runSnapshotDiscovery( $subject, true );

		$this->assertSame( [
			MissingSnapshotAuditor::Slug(),
		], $queue->queued );
		$this->assertSame( [
			ExistingSnapshotAuditor::Slug(),
			MissingSnapshotAuditor::Slug(),
		], $subject->snapshotLookups );
		$this->assertSame( 1, $queue->saveCount );
		$this->assertSame( 1, $queue->dispatchCount );
	}

	private function runSnapshotDiscovery( TestAuditCon $subject, bool $isDataPrime ) :RecordingSnapshotDiscovery {
		$queue = ( new \ReflectionClass( RecordingSnapshotDiscovery::class ) )
			->newInstanceWithoutConstructor();

		$queueProperty = new \ReflectionProperty( AuditCon::class, 'snapshotDiscoveryQueue' );
		$queueProperty->setAccessible( true );
		$queueProperty->setValue( $subject, $queue );

		$this->invokeNonPublicMethod( $subject, 'runAsyncSnapshotDiscovery', [ $isDataPrime ] );

		return $queue;
	}
}

class TestAuditCon extends AuditCon {

	/**
	 * @var list<Auditors\Base>
	 */
	private array $testAuditors;

	/**
	 * @var list<string>
	 */
	private array $existingSnapshotSlugs;

	/**
	 * @var list<string>
	 */
	public array $snapshotLookups = [];

	/**
	 * @param list<Auditors\Base> $auditors
	 * @param list<string>        $existingSnapshotSlugs
	 */
	public function __construct( array $auditors, array $existingSnapshotSlugs ) {
		$this->testAuditors = $auditors;
		$this->existingSnapshotSlugs = $existingSnapshotSlugs;
	}

	public function getAuditors() :array {
		return $this->testAuditors;
	}

	public function getSnapshot( string $slug ) :Record {
		$this->snapshotLookups[] = $slug;
		if ( !\in_array( $slug, $this->existingSnapshotSlugs, true ) ) {
			throw new \Exception();
		}
		return new Record();
	}
}

class RecordingSnapshotDiscovery extends SnapshotDiscovery {

	/**
	 * @var list<string>
	 */
	public array $queued = [];
	public int $saveCount = 0;
	public int $dispatchCount = 0;

	public function push_to_queue( $data ) :self {
		$this->queued[] = $data;
		return $this;
	}

	public function save() :self {
		$this->saveCount++;
		return $this;
	}

	public function dispatch() {
		$this->dispatchCount++;
		return $this;
	}
}

class ExistingSnapshotAuditor extends Auditors\Base {
}

class MissingSnapshotAuditor extends Auditors\Base {
}
