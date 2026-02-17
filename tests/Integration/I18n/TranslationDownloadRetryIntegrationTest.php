<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\TranslationDownloadController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class TranslationDownloadRetryIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->resetTranslationConfig();
	}

	public function tear_down() {
		$this->resetTranslationConfig();
		parent::tear_down();
	}

	private function resetTranslationConfig() :void {
		$controller = $this->controller();
		$this->addCfg( $controller, 'queue', [] );
		$this->addCfg( $controller, 'locales', [] );
		$this->addCfg( $controller, 'last_fetch_at', 0 );
		$this->addCfg( $controller, 'last_download_at', 0 );
	}

	private function controller() :TranslationDownloadController {
		$controller = $this->requireController()->comps->translation_downloads;
		$this->assertInstanceOf( TranslationDownloadController::class, $controller );
		return $controller;
	}

	/**
	 * @return mixed
	 */
	private function callPrivateMethod( TranslationDownloadController $controller, string $method, array $args = [] ) {
		$reflection = new \ReflectionClass( $controller );
		$m = $reflection->getMethod( $method );
		$m->setAccessible( true );
		return $m->invokeArgs( $controller, $args );
	}

	private function addCfg( TranslationDownloadController $controller, string $key, $value ) :void {
		$this->callPrivateMethod( $controller, 'addCfg', [ $key, $value ] );
	}

	private function seedQueueConfig(
		TranslationDownloadController $controller,
		string $locale,
		string $hash,
		int $lastFetchAt
	) :void {
		$this->addCfg( $controller, 'locales', [
			$locale => [
				'hash'      => $hash,
				'hash_type' => 'sha256',
			],
		] );
		$this->addCfg( $controller, 'queue', [ $locale ] );
		$this->addCfg( $controller, 'last_fetch_at', $lastFetchAt );
		$this->addCfg( $controller, 'last_download_at', 0 );
	}

	private function ensureLocaleCachePathAvailable( TranslationDownloadController $controller, string $locale ) :string {
		$path = (string)$this->callPrivateMethod( $controller, 'buildMoFilePath', [ $locale ] );
		if ( empty( $path ) ) {
			$this->markTestSkipped( 'Translation cache path is not available in this integration environment.' );
		}
		return $path;
	}

	private function clearLocaleMoFile( string $path ) :void {
		if ( Services::WpFs()->exists( $path ) ) {
			Services::WpFs()->deleteFile( $path );
		}
	}

	private function httpResponse( string $body ) :array {
		return [
			'headers'  => [],
			'body'     => $body,
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'cookies'  => [],
			'filename' => null,
		];
	}

	private function buildValidMoContent( string $payload ) :string {
		return \pack( 'N', 0x950412de ).$payload.\str_repeat( "\x00", 20 );
	}

	public function testHashMismatchRefreshesMetadataOnceAndRetrySucceeds() :void {
		$controller = $this->controller();
		$locale = 'es_ES';

		$path = $this->ensureLocaleCachePathAvailable( $controller, $locale );
		$this->clearLocaleMoFile( $path );

		$moContent = $this->buildValidMoContent( 'retry-success' );
		$freshHash = \hash( 'sha256', $moContent );
		$staleHash = \hash( 'sha256', 'stale-hash-value' );
		$this->seedQueueConfig( $controller, $locale, $staleHash, \time() - 3700 );

		$listCalls = 0;
		$downloadCalls = 0;

		$httpStub = function ( $pre, $args, $url ) use ( &$listCalls, &$downloadCalls, $locale, $freshHash, $moContent ) {
			if ( \str_contains( $url, '/translations/list' ) ) {
				$listCalls++;
				return $this->httpResponse( (string)\wp_json_encode( [
					'error_code' => 0,
					'locales'    => [
						$locale => [
							'hash'      => $freshHash,
							'hash_type' => 'sha256',
						],
					],
				] ) );
			}
			if ( \str_contains( $url, '/translations/download' ) ) {
				$downloadCalls++;
				return $this->httpResponse( $moContent );
			}
			return $pre;
		};

		$this->captureShieldEvents();
		add_filter( 'pre_http_request', $httpStub, 10, 3 );
		try {
			$controller->processQueue( true );
		}
		finally {
			remove_filter( 'pre_http_request', $httpStub, 10 );
		}

		$this->assertSame( 1, $listCalls, 'Metadata should be force-refreshed once after hash mismatch.' );
		$this->assertSame( 2, $downloadCalls, 'Download should run initial attempt plus one retry.' );

		$downloaded = $this->getCapturedEventsByKey( 'translation_downloaded' );
		$failed = $this->getCapturedEventsByKey( 'translation_download_failed' );
		$this->assertCount( 1, $downloaded );
		$this->assertCount( 0, $failed );
		$this->assertSame( $locale, $downloaded[ 0 ][ 'meta' ][ 'audit_params' ][ 'locale' ] ?? '' );

		$savedPath = $controller->getLocaleMoFilePath( $locale );
		$this->assertNotNull( $savedPath );
		$this->assertFileExists( (string)$savedPath );
		$this->assertSame( $freshHash, \hash_file( 'sha256', (string)$savedPath ) );
	}

	public function testHashMismatchFailsWithoutRefreshWhenGuardBlocks() :void {
		$controller = $this->controller();
		$locale = 'es_ES';

		$path = $this->ensureLocaleCachePathAvailable( $controller, $locale );
		$this->clearLocaleMoFile( $path );

		$moContent = $this->buildValidMoContent( 'guard-blocks' );
		$staleHash = \hash( 'sha256', 'guard-blocks-stale-hash' );
		$this->seedQueueConfig( $controller, $locale, $staleHash, \time() - 300 );

		$listCalls = 0;
		$downloadCalls = 0;

		$httpStub = function ( $pre, $args, $url ) use ( &$listCalls, &$downloadCalls, $moContent ) {
			if ( \str_contains( $url, '/translations/list' ) ) {
				$listCalls++;
				return $this->httpResponse( (string)\wp_json_encode( [ 'error_code' => 0, 'locales' => [] ] ) );
			}
			if ( \str_contains( $url, '/translations/download' ) ) {
				$downloadCalls++;
				return $this->httpResponse( $moContent );
			}
			return $pre;
		};

		$this->captureShieldEvents();
		add_filter( 'pre_http_request', $httpStub, 10, 3 );
		try {
			$controller->processQueue( true );
		}
		finally {
			remove_filter( 'pre_http_request', $httpStub, 10 );
		}

		$this->assertSame( 0, $listCalls, 'No metadata refresh should happen within 1-hour guard window.' );
		$this->assertSame( 1, $downloadCalls, 'Only the initial download attempt should run.' );

		$downloaded = $this->getCapturedEventsByKey( 'translation_downloaded' );
		$failed = $this->getCapturedEventsByKey( 'translation_download_failed' );
		$this->assertCount( 0, $downloaded );
		$this->assertCount( 1, $failed );
		$this->assertSame( 'hash_mismatch', $failed[ 0 ][ 'meta' ][ 'audit_params' ][ 'reason' ] ?? '' );
	}

	public function testHashMismatchFailsWhenRefreshHasNoLocaleMeta() :void {
		$controller = $this->controller();
		$locale = 'es_ES';

		$path = $this->ensureLocaleCachePathAvailable( $controller, $locale );
		$this->clearLocaleMoFile( $path );

		$staleHash = \hash( 'sha256', 'missing-meta-stale-hash' );
		$this->seedQueueConfig( $controller, $locale, $staleHash, \time() - 3700 );

		$listCalls = 0;
		$downloadCalls = 0;

		$httpStub = function ( $pre, $args, $url ) use ( &$listCalls, &$downloadCalls ) {
			if ( \str_contains( $url, '/translations/list' ) ) {
				$listCalls++;
				return $this->httpResponse( (string)\wp_json_encode( [
					'error_code' => 0,
					'locales'    => [
						'fr_FR' => [
							'hash'      => 'abc',
							'hash_type' => 'sha256',
						],
					],
				] ) );
			}
			if ( \str_contains( $url, '/translations/download' ) ) {
				$downloadCalls++;
				return $this->httpResponse( $this->buildValidMoContent( 'missing-meta' ) );
			}
			return $pre;
		};

		$this->captureShieldEvents();
		add_filter( 'pre_http_request', $httpStub, 10, 3 );
		try {
			$controller->processQueue( true );
		}
		finally {
			remove_filter( 'pre_http_request', $httpStub, 10 );
		}

		$this->assertSame( 1, $listCalls, 'Metadata refresh should run once after hash mismatch.' );
		$this->assertSame( 1, $downloadCalls, 'Retry should not run if refreshed metadata is missing.' );

		$downloaded = $this->getCapturedEventsByKey( 'translation_downloaded' );
		$failed = $this->getCapturedEventsByKey( 'translation_download_failed' );
		$this->assertCount( 0, $downloaded );
		$this->assertCount( 1, $failed );
		$this->assertSame(
			'missing_locale_meta_after_hash_mismatch',
			$failed[ 0 ][ 'meta' ][ 'audit_params' ][ 'reason' ] ?? ''
		);
	}

	public function testInvalidFileFailureDoesNotTriggerRefresh() :void {
		$controller = $this->controller();
		$locale = 'es_ES';

		$path = $this->ensureLocaleCachePathAvailable( $controller, $locale );
		$this->clearLocaleMoFile( $path );

		$validMoForExpectedHash = $this->buildValidMoContent( 'expected-hash' );
		$expectedHash = \hash( 'sha256', $validMoForExpectedHash );
		$this->seedQueueConfig( $controller, $locale, $expectedHash, \time() - 3700 );

		$listCalls = 0;
		$downloadCalls = 0;

		$httpStub = function ( $pre, $args, $url ) use ( &$listCalls, &$downloadCalls ) {
			if ( \str_contains( $url, '/translations/list' ) ) {
				$listCalls++;
				return $this->httpResponse( (string)\wp_json_encode( [ 'error_code' => 0, 'locales' => [] ] ) );
			}
			if ( \str_contains( $url, '/translations/download' ) ) {
				$downloadCalls++;
				return $this->httpResponse( 'invalid-not-mo-content' );
			}
			return $pre;
		};

		$this->captureShieldEvents();
		add_filter( 'pre_http_request', $httpStub, 10, 3 );
		try {
			$controller->processQueue( true );
		}
		finally {
			remove_filter( 'pre_http_request', $httpStub, 10 );
		}

		$this->assertSame( 0, $listCalls, 'Non-hash failures should not trigger metadata refresh.' );
		$this->assertSame( 1, $downloadCalls, 'Only one download attempt should run for invalid file failures.' );

		$downloaded = $this->getCapturedEventsByKey( 'translation_downloaded' );
		$failed = $this->getCapturedEventsByKey( 'translation_download_failed' );
		$this->assertCount( 0, $downloaded );
		$this->assertCount( 1, $failed );
		$this->assertSame( 'invalid_file', $failed[ 0 ][ 'meta' ][ 'audit_params' ][ 'reason' ] ?? '' );
	}

	public function testDailyRefreshQueuesStaleLocaleWithoutDownload() :void {
		$controller = $this->controller();
		$locale = 'de_DE';

		$path = $this->ensureLocaleCachePathAvailable( $controller, $locale );
		$this->clearLocaleMoFile( $path );
		Services::WpFs()->putFileContent( $path, 'daily-refresh-local-v1' );
		$this->addCfg( $controller, 'queue', [] );

		$listCalls = 0;
		$downloadCalls = 0;
		$remoteHash = \hash( 'sha256', 'daily-refresh-remote-v2' );

		$httpStub = function ( $pre, $args, $url ) use ( &$listCalls, &$downloadCalls, $locale, $remoteHash ) {
			if ( \str_contains( $url, '/translations/list' ) ) {
				$listCalls++;
				return $this->httpResponse( (string)\wp_json_encode( [
					'error_code' => 0,
					'locales'    => [
						$locale => [
							'hash'      => $remoteHash,
							'hash_type' => 'sha256',
						],
					],
				] ) );
			}
			if ( \str_contains( $url, '/translations/download' ) ) {
				$downloadCalls++;
			}
			return $pre;
		};

		add_filter( 'pre_http_request', $httpStub, 10, 3 );
		try {
			$controller->queueStaleCachedLocalesForDownload();
		}
		finally {
			remove_filter( 'pre_http_request', $httpStub, 10 );
			$this->clearLocaleMoFile( $path );
		}

		$this->assertSame( 1, $listCalls );
		$this->assertSame( 0, $downloadCalls );
		$this->assertContains( $locale, $controller->getQueue() );
	}

	public function testDailyRefreshSkipsMatchingHash() :void {
		$controller = $this->controller();
		$locale = 'fr_FR';

		$path = $this->ensureLocaleCachePathAvailable( $controller, $locale );
		$this->clearLocaleMoFile( $path );
		$content = 'daily-refresh-match';
		Services::WpFs()->putFileContent( $path, $content );
		$this->addCfg( $controller, 'queue', [] );

		$httpStub = function ( $pre, $args, $url ) use ( $locale, $content ) {
			if ( \str_contains( $url, '/translations/list' ) ) {
				return $this->httpResponse( (string)\wp_json_encode( [
					'error_code' => 0,
					'locales'    => [
						$locale => [
							'hash'      => \hash( 'sha256', $content ),
							'hash_type' => 'sha256',
						],
					],
				] ) );
			}
			return $pre;
		};

		add_filter( 'pre_http_request', $httpStub, 10, 3 );
		try {
			$controller->queueStaleCachedLocalesForDownload();
		}
		finally {
			remove_filter( 'pre_http_request', $httpStub, 10 );
			$this->clearLocaleMoFile( $path );
		}

		$this->assertNotContains( $locale, $controller->getQueue() );
	}

	public function testDailyRefreshSkipsMissingLocalFile() :void {
		$controller = $this->controller();
		$locale = 'es_ES';

		$path = $this->ensureLocaleCachePathAvailable( $controller, $locale );
		$this->clearLocaleMoFile( $path );
		$this->addCfg( $controller, 'queue', [] );

		$httpStub = function ( $pre, $args, $url ) use ( $locale ) {
			if ( \str_contains( $url, '/translations/list' ) ) {
				return $this->httpResponse( (string)\wp_json_encode( [
					'error_code' => 0,
					'locales'    => [
						$locale => [
							'hash'      => \hash( 'sha256', 'remote-only' ),
							'hash_type' => 'sha256',
						],
					],
				] ) );
			}
			return $pre;
		};

		add_filter( 'pre_http_request', $httpStub, 10, 3 );
		try {
			$controller->queueStaleCachedLocalesForDownload();
		}
		finally {
			remove_filter( 'pre_http_request', $httpStub, 10 );
		}

		$this->assertNotContains( $locale, $controller->getQueue() );
	}

	public function testDailyRefreshSkipsUnsupportedHashType() :void {
		$controller = $this->controller();
		$locale = 'nl_NL';

		$path = $this->ensureLocaleCachePathAvailable( $controller, $locale );
		$this->clearLocaleMoFile( $path );
		Services::WpFs()->putFileContent( $path, 'daily-refresh-local' );
		$this->addCfg( $controller, 'queue', [] );

		$httpStub = function ( $pre, $args, $url ) use ( $locale ) {
			if ( \str_contains( $url, '/translations/list' ) ) {
				return $this->httpResponse( (string)\wp_json_encode( [
					'error_code' => 0,
					'locales'    => [
						$locale => [
							'hash'      => \hash( 'sha256', 'daily-refresh-remote' ),
							'hash_type' => 'not-a-real-algo',
						],
					],
				] ) );
			}
			return $pre;
		};

		add_filter( 'pre_http_request', $httpStub, 10, 3 );
		try {
			$controller->queueStaleCachedLocalesForDownload();
		}
		finally {
			remove_filter( 'pre_http_request', $httpStub, 10 );
			$this->clearLocaleMoFile( $path );
		}

		$this->assertNotContains( $locale, $controller->getQueue() );
	}

	public function testDailyRefreshSkipsOnMetadataFailureWithoutMutatingConfig() :void {
		$controller = $this->controller();
		$seededLocales = [
			'it_IT' => [ 'hash' => 'seeded-hash', 'hash_type' => 'sha256', ],
		];
		$seededQueue = [ 'it_IT' ];
		$seededLastFetchAt = \time() - 120;

		$this->addCfg( $controller, 'locales', $seededLocales );
		$this->addCfg( $controller, 'queue', $seededQueue );
		$this->addCfg( $controller, 'last_fetch_at', $seededLastFetchAt );

		$httpStub = function ( $pre, $args, $url ) {
			if ( \str_contains( $url, '/translations/list' ) ) {
				return $this->httpResponse( (string)\wp_json_encode( [
					'error_code' => 1,
					'locales'    => [],
				] ) );
			}
			return $pre;
		};

		add_filter( 'pre_http_request', $httpStub, 10, 3 );
		try {
			$controller->queueStaleCachedLocalesForDownload();
		}
		finally {
			remove_filter( 'pre_http_request', $httpStub, 10 );
		}

		$this->assertSame( $seededLocales, $controller->getCachedLocales() );
		$this->assertSame( $seededQueue, $controller->getQueue() );
		$this->assertSame( $seededLastFetchAt, $controller->cfg()[ 'last_fetch_at' ] ?? 0 );
	}

	public function testDailyRefreshQueueDeduplicatesLocale() :void {
		$controller = $this->controller();
		$locale = 'pt_PT';

		$path = $this->ensureLocaleCachePathAvailable( $controller, $locale );
		$this->clearLocaleMoFile( $path );
		Services::WpFs()->putFileContent( $path, 'daily-refresh-local' );
		$this->addCfg( $controller, 'queue', [ $locale ] );

		$httpStub = function ( $pre, $args, $url ) use ( $locale ) {
			if ( \str_contains( $url, '/translations/list' ) ) {
				return $this->httpResponse( (string)\wp_json_encode( [
					'error_code' => 0,
					'locales'    => [
						$locale => [
							'hash'      => \hash( 'sha256', 'daily-refresh-remote' ),
							'hash_type' => 'sha256',
						],
					],
				] ) );
			}
			return $pre;
		};

		add_filter( 'pre_http_request', $httpStub, 10, 3 );
		try {
			$controller->queueStaleCachedLocalesForDownload();
		}
		finally {
			remove_filter( 'pre_http_request', $httpStub, 10 );
			$this->clearLocaleMoFile( $path );
		}

		$count = \array_count_values( $controller->getQueue() )[ $locale ] ?? 0;
		$this->assertSame( 1, $count );
	}
}
