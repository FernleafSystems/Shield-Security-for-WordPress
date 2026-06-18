<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\AllowBetaUpgrades;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Services\Core\Plugins;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class AllowBetaUpgradesTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

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

	public function testDoesNotRemoveEntryWhenNewVersionIsMalformed() :void {
		$baseFile = 'wp-plugin-shield/icwp-wpsf.php';
		$updates = $this->buildUpdates( [
			$baseFile => (object)[ 'new_version' => [] ],
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

	public function testBetaLookupNoopsWhenPluginVoIsUnavailable() :void {
		UnitTestControllerFactory::install( null, null, (object)[
			'base_file' => 'wp-plugin-shield/icwp-wpsf.php',
			'cfg'       => new AllowBetaUpgradesTestConfig(),
		] );
		ServicesState::installItems( [
			'service_wpplugins' => new AllowBetaUpgradesPluginsStub(),
		] );

		$subject = new AllowBetaUpgrades();
		$reflection = new \ReflectionClass( AllowBetaUpgrades::class );
		$method = $reflection->getMethod( 'getBeta' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $subject ) );
	}
}

class AllowBetaUpgradesTestConfig {

	public function version() :string {
		return '22.1.3';
	}
}

class AllowBetaUpgradesPluginsStub extends Plugins {

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		return null;
	}
}
