<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\AllowBetaUpgrades;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class AllowBetaUpgradesTest extends BaseUnitTest {

	private function invokeCleanupCore( $updates, string $baseFile = 'wp-plugin-shield/icwp-wpsf.php', string $currentVersion = '21.1.8' ) {
		$subject = new AllowBetaUpgrades();
		$reflection = new \ReflectionClass( AllowBetaUpgrades::class );
		$method = $reflection->getMethod( 'removeStaleSelfUpdateNoticeCore' );
		$method->setAccessible( true );
		return $method->invoke( $subject, $updates, $baseFile, $currentVersion );
	}

	private function buildUpdates( array $response ) :\stdClass {
		$updates = new \stdClass();
		$updates->response = $response;
		return $updates;
	}

	public function testRemovesSelfUpdateWhenVersionMatchesCurrent() :void {
		$baseFile = 'wp-plugin-shield/icwp-wpsf.php';
		$updates = $this->buildUpdates( [
			$baseFile             => (object)[ 'new_version' => '21.1.8' ],
			'akismet/akismet.php' => (object)[ 'new_version' => '9.9.9' ],
		] );

		$result = $this->invokeCleanupCore( $updates, $baseFile, '21.1.8' );

		$this->assertArrayNotHasKey( $baseFile, $result->response );
		$this->assertArrayHasKey( 'akismet/akismet.php', $result->response );
	}

	public function testRemovesSelfUpdateWhenVersionIsLowerThanCurrent() :void {
		$baseFile = 'wp-plugin-shield/icwp-wpsf.php';
		$updates = $this->buildUpdates( [
			$baseFile             => (object)[ 'new_version' => '21.1.7' ],
			'akismet/akismet.php' => (object)[ 'new_version' => '9.9.9' ],
		] );

		$result = $this->invokeCleanupCore( $updates, $baseFile, '21.1.8' );

		$this->assertArrayNotHasKey( $baseFile, $result->response );
		$this->assertArrayHasKey( 'akismet/akismet.php', $result->response );
	}

	public function testKeepsSelfUpdateWhenVersionIsHigherThanCurrent() :void {
		$baseFile = 'wp-plugin-shield/icwp-wpsf.php';
		$updates = $this->buildUpdates( [
			$baseFile => (object)[ 'new_version' => '21.1.9' ],
		] );

		$result = $this->invokeCleanupCore( $updates, $baseFile, '21.1.8' );

		$this->assertArrayHasKey( $baseFile, $result->response );
	}

	public function testRemovesSelfUpdateWhenPayloadIsArray() :void {
		$baseFile = 'wp-plugin-shield/icwp-wpsf.php';
		$updates = $this->buildUpdates( [
			$baseFile => [ 'new_version' => '21.1.8' ],
		] );

		$result = $this->invokeCleanupCore( $updates, $baseFile, '21.1.8' );

		$this->assertArrayNotHasKey( $baseFile, $result->response );
	}

	public function testDoesNotRemoveEntryWithoutNewVersion() :void {
		$baseFile = 'wp-plugin-shield/icwp-wpsf.php';
		$updates = $this->buildUpdates( [
			$baseFile => (object)[
				'package' => 'https://downloads.wordpress.org/plugin/wp-plugin-shield.zip',
			],
		] );

		$result = $this->invokeCleanupCore( $updates, $baseFile, '21.1.8' );

		$this->assertArrayHasKey( $baseFile, $result->response );
	}

	public function testNoopWhenResponseMissing() :void {
		$updates = new \stdClass();

		$result = $this->invokeCleanupCore( $updates );

		$this->assertObjectNotHasProperty( 'response', $result );
	}

	public function testNoopWhenResponseIsNotArray() :void {
		$updates = new \stdClass();
		$updates->response = 'not-an-array';

		$result = $this->invokeCleanupCore( $updates );

		$this->assertSame( 'not-an-array', $result->response );
	}

	public function testNoopWhenBaseFileEntryMissing() :void {
		$updates = $this->buildUpdates( [
			'akismet/akismet.php' => (object)[ 'new_version' => '9.9.9' ],
		] );

		$result = $this->invokeCleanupCore( $updates, 'wp-plugin-shield/icwp-wpsf.php', '21.1.8' );

		$this->assertArrayHasKey( 'akismet/akismet.php', $result->response );
		$this->assertCount( 1, $result->response );
	}

	public function testNoopWhenBaseFileIsEmpty() :void {
		$updates = $this->buildUpdates( [
			'wp-plugin-shield/icwp-wpsf.php' => (object)[ 'new_version' => '21.1.8' ],
		] );

		$result = $this->invokeCleanupCore( $updates, '', '21.1.8' );

		$this->assertArrayHasKey( 'wp-plugin-shield/icwp-wpsf.php', $result->response );
	}

	public function testNoopWhenCurrentVersionIsEmpty() :void {
		$updates = $this->buildUpdates( [
			'wp-plugin-shield/icwp-wpsf.php' => (object)[ 'new_version' => '21.1.8' ],
		] );

		$result = $this->invokeCleanupCore( $updates, 'wp-plugin-shield/icwp-wpsf.php', '' );

		$this->assertArrayHasKey( 'wp-plugin-shield/icwp-wpsf.php', $result->response );
	}
}
