<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalWpTestsConfigGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class LocalWpTestsConfigGuardTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function test_missing_config_is_left_for_installer() :void {
		$wpTestsDir = $this->createTrackedTempDir( 'shield-wp-tests-config-missing-' );

		( new LocalWpTestsConfigGuard() )->removeIfStale( $wpTestsDir, $this->expectedConstants() );

		$this->assertFileDoesNotExist( $this->configPath( $wpTestsDir ) );
	}

	public function test_matching_config_is_preserved_and_accepted() :void {
		$wpTestsDir = $this->createTrackedTempDir( 'shield-wp-tests-config-matching-' );
		$configPath = $this->writeConfig( $wpTestsDir, $this->expectedConstants() );

		$guard = new LocalWpTestsConfigGuard();
		$guard->removeIfStale( $wpTestsDir, $this->expectedConstants() );
		$guard->assertMatches( $wpTestsDir, $this->expectedConstants() );

		$this->assertFileExists( $configPath );
	}

	public function test_stale_config_is_removed_before_installer() :void {
		$wpTestsDir = $this->createTrackedTempDir( 'shield-wp-tests-config-stale-' );
		$configPath = $this->writeConfig( $wpTestsDir, [
			'DB_NAME' => 'wrong_db',
			'DB_USER' => 'root',
			'DB_PASSWORD' => 'testpass',
			'DB_HOST' => 'localhost',
		] );

		( new LocalWpTestsConfigGuard() )->removeIfStale( $wpTestsDir, $this->expectedConstants() );

		$this->assertFileDoesNotExist( $configPath );
	}

	public function test_malformed_config_is_removed_before_installer() :void {
		$wpTestsDir = $this->createTrackedTempDir( 'shield-wp-tests-config-malformed-' );
		$configPath = $this->configPath( $wpTestsDir );
		\file_put_contents( $configPath, "<?php\n// missing DB constants\n" );

		( new LocalWpTestsConfigGuard() )->removeIfStale( $wpTestsDir, $this->expectedConstants() );

		$this->assertFileDoesNotExist( $configPath );
	}

	public function test_assert_matches_fails_clearly_for_stale_config() :void {
		$wpTestsDir = $this->createTrackedTempDir( 'shield-wp-tests-config-assert-stale-' );
		$this->writeConfig( $wpTestsDir, [
			'DB_NAME' => 'wordpress_test_local',
			'DB_USER' => 'root',
			'DB_PASSWORD' => 'testpass',
			'DB_HOST' => 'localhost',
		] );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'WordPress test DB config does not match integration-local database.' );
		$this->expectExceptionMessage( 'DB_HOST=localhost' );

		( new LocalWpTestsConfigGuard() )->assertMatches( $wpTestsDir, $this->expectedConstants() );
	}

	/**
	 * @return array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string}
	 */
	private function expectedConstants() :array {
		return [
			'DB_NAME' => 'wordpress_test_local',
			'DB_USER' => 'root',
			'DB_PASSWORD' => 'testpass',
			'DB_HOST' => '127.0.0.1:3311',
		];
	}

	/**
	 * @param array{DB_NAME:string,DB_USER:string,DB_PASSWORD:string,DB_HOST:string} $constants
	 */
	private function writeConfig( string $wpTestsDir, array $constants ) :string {
		$configPath = $this->configPath( $wpTestsDir );
		\file_put_contents(
			$configPath,
			"<?php\n"
			."define( 'DB_NAME', '{$constants['DB_NAME']}' );\n"
			."define( 'DB_USER', '{$constants['DB_USER']}' );\n"
			."define( 'DB_PASSWORD', '{$constants['DB_PASSWORD']}' );\n"
			."define( 'DB_HOST', '{$constants['DB_HOST']}' );\n"
		);

		return $configPath;
	}

	private function configPath( string $wpTestsDir ) :string {
		return Path::join( $wpTestsDir, 'wp-tests-config.php' );
	}
}
