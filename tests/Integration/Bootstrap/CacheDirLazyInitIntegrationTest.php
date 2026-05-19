<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bootstrap;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Services;

class CacheDirLazyInitIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use TempDirLifecycleTrait;

	private string $originalPreferredTempDir = '';

	private array $originalLastKnownCacheDirs = [];

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$con = $this->requireController();
		$this->originalPreferredTempDir = (string)$con->opts->optGet( 'preferred_temp_dir' );
		$this->originalLastKnownCacheDirs = (array)$con->opts->optGet( 'last_known_cache_basedirs' );
	}

	public function tear_down() {
		$con = $this->requireController();
		$con->opts
			->optSet( 'preferred_temp_dir', $this->originalPreferredTempDir )
			->optSet( 'last_known_cache_basedirs', $this->originalLastKnownCacheDirs );
		if ( $con->opts->hasChanges() ) {
			$con->opts->store();
		}
		$this->resetCacheDirHandlerState();
		$this->cleanupTrackedTempDirs();
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_plain_request_does_not_create_cache_dir_until_feature_uses_it() :void {
		$con = $this->requireController();
		$preferredTempDir = $this->createRuntimeTrackedTempDir( 'shield-cache-dir-' );

		$con->opts
			->optSet( 'preferred_temp_dir', $preferredTempDir )
			->optSet( 'last_known_cache_basedirs', [] );
		$con->opts->store();
		$this->resetCacheDirHandlerState();

		$resolvedCacheDir = $con->cache_dir_handler->dir();
		$this->assertNotSame( '', $resolvedCacheDir );
		Services::WpFs()->deleteDir( $resolvedCacheDir );
		$this->assertFalse( Services::WpFs()->isDir( $resolvedCacheDir ) );

		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/',
		] );
		$con->onWpShutdown();

		$this->assertFalse( Services::WpFs()->isDir( $resolvedCacheDir ), 'Plain request should not eagerly create the cache directory.' );

		$con->comps->translation_downloads->getLocaleMoFilePath( 'fr_FR' );

		$this->assertTrue( Services::WpFs()->isDir( $resolvedCacheDir ) );
		$this->assertTrue( Services::WpFs()->isDir( \path_join( $resolvedCacheDir, 'languages' ) ) );
	}

	public function test_legacy_url_keyed_cache_dir_still_seeds_handler_without_migration() :void {
		$con = $this->requireController();
		$legacyBaseDir = $this->createRuntimeTrackedTempDir( 'shield-cache-legacy-base-' );

		$con->opts
			->optSet( 'preferred_temp_dir', '' )
			->optSet( 'last_known_cache_basedirs', [
				'https://legacy.example/' => $legacyBaseDir,
			] );
		$con->opts->store();
		$this->resetCacheDirHandlerState();

		$this->assertSame(
			\wp_normalize_path( \path_join( $legacyBaseDir, (string)$con->cfg->paths[ 'cache' ] ) ),
			$con->cache_dir_handler->dir()
		);
		$this->assertSame( [
			'https://legacy.example/' => $legacyBaseDir,
		], $con->opts->optGet( 'last_known_cache_basedirs' ) );
	}

	private function resetCacheDirHandlerState() :void {
		$con = $this->requireController();
		$con->cache_dir_handler = null;

		$ref = new \ReflectionClass( $con->plugin );
		$prop = $ref->getProperty( 'cacheDirHandler' );
		$prop->setAccessible( true );
		$prop->setValue( $con->plugin, null );
	}

	private function createRuntimeTrackedTempDir( string $prefix ) :string {
		$path = \wp_normalize_path( \path_join( \get_temp_dir(), $prefix.\bin2hex( \random_bytes( 6 ) ) ) );
		if ( !Services::WpFs()->isDir( $path ) && !Services::WpFs()->mkdir( $path ) ) {
			throw new \RuntimeException( 'Failed to create runtime temporary directory: '.$path );
		}
		$this->trackedTempDirs[] = $path;
		return $path;
	}
}
