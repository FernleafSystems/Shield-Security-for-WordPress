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
		$preferredTempDir = $this->createTrackedTempDir( 'cache-dir-', \get_temp_dir() );
		$expectedLastKnown = [];

		$con->opts
			->optSet( 'preferred_temp_dir', $preferredTempDir )
			->optSet( 'last_known_cache_basedirs', $expectedLastKnown );
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
		$this->assertSame( $preferredTempDir, $con->opts->optGet( 'preferred_temp_dir' ) );
		$this->assertSame( $expectedLastKnown, $con->opts->optGet( 'last_known_cache_basedirs' ) );

		$con->comps->translation_downloads->getLocaleMoFilePath( 'fr_FR' );

		$this->assertTrue( Services::WpFs()->isDir( $resolvedCacheDir ) );
		$this->assertTrue( Services::WpFs()->isDir( \path_join( $resolvedCacheDir, 'languages' ) ) );
	}

	public function test_legacy_url_keyed_cache_dir_still_seeds_handler_without_migration() :void {
		$con = $this->requireController();
		$legacyBaseDir = $this->createTrackedTempDir( 'cache-legacy-base-', \get_temp_dir() );
		$storedDirs = [
			'https://legacy.example/' => $legacyBaseDir,
		];

		$con->opts
			->optSet( 'preferred_temp_dir', '' )
			->optSet( 'last_known_cache_basedirs', $storedDirs );
		$con->opts->store();
		$this->resetCacheDirHandlerState();

		$resolved = $con->cache_dir_handler->dir();
		$this->assertSame( $legacyBaseDir, \wp_normalize_path( \dirname( $resolved ) ) );
		$this->assertMatchesRegularExpression( '#^shield-v2-[a-f0-9]{32}$#', \basename( $resolved ) );
		$this->assertTrue( Services::WpFs()->isDir( $resolved ) );
		$this->assertFalse( Services::WpFs()->isDir( \path_join( $legacyBaseDir, (string)$con->cfg->paths[ 'cache' ] ) ) );
		$this->assertSame( $storedDirs, $con->opts->optGet( 'last_known_cache_basedirs' ) );
	}

	private function resetCacheDirHandlerState() :void {
		$con = $this->requireController();
		$con->cache_dir_handler = null;

		$ref = new \ReflectionClass( $con->plugin );
		$prop = $ref->getProperty( 'cacheDirHandler' );
		$prop->setAccessible( true );
		$prop->setValue( $con->plugin, null );
	}
}
