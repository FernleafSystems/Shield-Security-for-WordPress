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
		$preferredTempDir = $this->createTrackedTempDir( 'shield-cache-dir-' );
		$expectedCacheDir = \path_join( $preferredTempDir, (string)$con->cfg->paths[ 'cache' ] );

		$con->opts
			->optSet( 'preferred_temp_dir', $preferredTempDir )
			->optSet( 'last_known_cache_basedirs', [ Services::WpGeneral()->getWpUrl() => '' ] );
		$con->opts->store();
		$this->resetCacheDirHandlerState();

		Services::WpFs()->deleteDir( $expectedCacheDir );
		$this->assertFalse( Services::WpFs()->isDir( $expectedCacheDir ) );

		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/',
		] );
		$con->onWpShutdown();

		$this->assertFalse( Services::WpFs()->isDir( $expectedCacheDir ), 'Plain request should not eagerly create the cache directory.' );

		$con->comps->translation_downloads->getLocaleMoFilePath( 'fr_FR' );

		$this->assertTrue( Services::WpFs()->isDir( $expectedCacheDir ) );
		$this->assertTrue( Services::WpFs()->isDir( \path_join( $expectedCacheDir, 'languages' ) ) );
		$this->assertTrue( Services::WpFs()->isAccessibleFile( \path_join( $expectedCacheDir, '.htaccess' ) ) );
		$this->assertTrue( Services::WpFs()->isAccessibleFile( \path_join( $expectedCacheDir, 'index.php' ) ) );
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
