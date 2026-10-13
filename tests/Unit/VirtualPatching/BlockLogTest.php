<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\BlockLog;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class BlockLogTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testAppendedEntriesAreReadBackBehindAPhpExit() :void {
		$path = $this->createTrackedTempDir().'/events.php';

		$this->assertTrue( BlockLog::append( $path, [ 'rule' => 'a' ] ) );
		$this->assertTrue( BlockLog::append( $path, [ 'rule' => 'b', 'path' => "/\xff" ] ) );

		$this->assertStringStartsWith( BlockLog::HEADER, (string)\file_get_contents( $path ) );
		$this->assertSame( [ 'a', 'b' ], \array_column( BlockLog::read( $path ), 'rule' ) );
	}

	public function testEntryReadsTheIpLikeTheBypassListWithRemoteAddrFallback() :void {
		$rule = [ 'id' => 'r', 'advisories' => [ 'CVE-2026-0001' ] ];
		$facts = [ 'method' => 'POST', 'path' => '/x' ];
		$server = [ 'REMOTE_ADDR' => '10.0.0.5', 'HTTP_CF_CONNECTING_IP' => '203.0.113.9' ];

		$fromSource = BlockLog::entry( [ 'bypass' => [ 'ip_source' => 'HTTP_CF_CONNECTING_IP', 'ips' => [] ] ], $rule, $facts, $server, 'early', 'match' );
		$this->assertSame( [ 'r', [ 'CVE-2026-0001' ], '203.0.113.9', 'POST', '/x', 'early', 'match' ], [
			$fromSource[ 'rule' ], $fromSource[ 'advisories' ], $fromSource[ 'ip' ], $fromSource[ 'method' ], $fromSource[ 'path' ], $fromSource[ 'pass' ], $fromSource[ 'verdict' ],
		] );

		$this->assertSame( '10.0.0.5', BlockLog::entry( [], $rule, $facts, $server, 'load', 'match' )[ 'ip' ] );
	}

	public function testDrainEmptiesTheLog() :void {
		$dir = $this->createTrackedTempDir();
		$path = $dir.'/events.php';
		BlockLog::append( $path, [ 'rule' => 'a' ] );

		$this->assertSame( [ [ 'rule' => 'a' ] ], BlockLog::drain( $path ) );
		$this->assertSame( [], BlockLog::drain( $path ) );
		$this->assertSame( [], \array_values( \array_diff( \scandir( $dir ), [ '.', '..' ] ) ) );

		BlockLog::append( $path, [ 'rule' => 'b' ] );
		$this->assertSame( [ [ 'rule' => 'b' ] ], BlockLog::read( $path ) );
	}

	public function testFullLogDropsNewEntries() :void {
		$path = $this->createTrackedTempDir().'/events.php';
		\file_put_contents( $path, BlockLog::HEADER.\str_repeat( 'x', BlockLog::MAX_BYTES - 20 ) );

		$this->assertFalse( BlockLog::append( $path, [ 'rule' => \str_repeat( 'a', 40 ) ] ) );
	}
}
