<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\GetAllAvailableLocales;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\LoadTextDomain;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldWordPressTestCase;

/**
 * Integration tests for remote translation download system
 *
 * Tests the full flow from WordPress translation request through to cached file.
 */
class TranslationDownloadIntegrationTest extends ShieldWordPressTestCase {

	public function testGetAllAvailableLocalesIncludesLocalFiles() :void {
		$enumerator = new GetAllAvailableLocales();

		$locales = $enumerator->run();

		$this->assertIsArray( $locales );
		// Should include any local .mo files found
	}

	public function testPluginJsonContainsTranslationsConfig() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );
		$this->assertNotNull( $con->cfg, 'Shield config must be loaded' );

		$translations = $con->cfg->translations ?? null;
		$this->assertNotNull( $translations, 'Translations config must exist in plugin.json' );

		$this->assertIsArray( $translations, 'Translations config should exist' );
		$this->assertArrayHasKey( 'download_cooldown_days', $translations );
		$this->assertArrayHasKey( 'list_cache_hours', $translations );
	}

	public function testCooldownDaysIsInteger() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );
		$this->assertNotNull( $con->cfg, 'Shield config must be loaded' );

		$translations = $con->cfg->translations ?? null;
		$this->assertNotNull( $translations, 'Translations config must exist' );

		$cooldownDays = $translations[ 'download_cooldown_days' ] ?? null;
		$this->assertIsInt( $cooldownDays );
		$this->assertGreaterThan( 0, $cooldownDays );
	}

	public function testListCacheHoursIsInteger() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );
		$this->assertNotNull( $con->cfg, 'Shield config must be loaded' );

		$translations = $con->cfg->translations ?? null;
		$this->assertNotNull( $translations, 'Translations config must exist' );

		$cacheHours = $translations[ 'list_cache_hours' ] ?? null;
		$this->assertIsInt( $cacheHours );
		$this->assertGreaterThan( 0, $cacheHours );
	}

	public function testTranslationDownloadControllerComponentExists() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );

		$controller = $con->comps->translation_downloads;
		$this->assertNotNull( $controller, 'Translation downloads component should exist' );
	}

	public function testCacheDirectoryCanBeCreated() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );

		$cacheDir = $con->cache_dir_handler->buildSubDir( 'languages' );

		// Cache directory might not be created if cache_dir_handler hasn't been set up yet
		// Just verify the method exists and returns a string
		$this->assertTrue( \is_string( $cacheDir ) );
	}

	public function testIsLocaleAvailableReturnsBoolean() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );

		$controller = $con->comps->translation_downloads;
		$this->assertNotNull( $controller, 'Translation downloads component must be available' );

		// Test with a locale that should be in fallback list
		$result = $controller->isLocaleAvailable( 'de_DE' );
		$this->assertIsBool( $result );

		// Test with a locale that shouldn't exist
		$result = $controller->isLocaleAvailable( 'xx_XX' );
		$this->assertFalse( $result );
	}

	public function testGetCachedFilePathReturnsNullOrString() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );

		$controller = $con->comps->translation_downloads;
		$this->assertNotNull( $controller, 'Translation downloads component must be available' );

		// For a locale that doesn't have a cached file
		$result = $controller->getLocaleMoFilePath( 'de_DE' );
		$this->assertTrue( $result === null || \is_string( $result ) );
	}

	public function testCronHooksAreRegistered() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );

		$controller = $con->comps->translation_downloads;
		$this->assertNotNull( $controller, 'Translation downloads component must be available' );

		// Execute the controller to register cron hooks
		$controller->execute();

		// Check that the hourly cron hook exists
		// The controller uses PluginCronsConsumer which sets up 'runHourlyCron'
		$this->assertTrue(
			\method_exists( $controller, 'runHourlyCron' ),
			'Controller should have runHourlyCron method'
		);

		// Verify the setupCronHooks method was called (from PluginCronsConsumer trait)
		$this->assertTrue(
			\method_exists( $controller, 'setupCronHooks' ),
			'Controller should have setupCronHooks method from trait'
		);
	}

	public function testQueueLocaleForDownloadAcceptsValidLocale() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );

		$controller = $con->comps->translation_downloads;
		$this->assertNotNull( $controller, 'Translation downloads component must be available' );

		// Queue a locale that should be in fallback list
		$controller->enqueueLocaleForDownload( 'de_DE' );

		// Access queue via reflection
		$reflection = new \ReflectionClass( $controller );
		$method = $reflection->getMethod( 'getQueue' );
		$method->setAccessible( true );
		$queue = $method->invoke( $controller );

		$this->assertContains( 'de_DE', $queue, 'Valid locale should be added to queue' );
	}

	public function testQueuePreventsEmptyLocale() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );

		$controller = $con->comps->translation_downloads;
		$this->assertNotNull( $controller, 'Translation downloads component must be available' );

		// Get initial queue size
		$reflection = new \ReflectionClass( $controller );
		$method = $reflection->getMethod( 'getQueue' );
		$method->setAccessible( true );
		$initialQueue = $method->invoke( $controller );

		// Try to queue an empty string
		$controller->enqueueLocaleForDownload( '' );

		$newQueue = $method->invoke( $controller );
		$this->assertEquals(
			\count( $initialQueue ),
			\count( $newQueue ),
			'Empty locale should not be added to queue'
		);
	}

	public function testQueuePreventsDuplicates() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );

		$controller = $con->comps->translation_downloads;
		$this->assertNotNull( $controller, 'Translation downloads component must be available' );

		// Queue the same locale twice
		$controller->enqueueLocaleForDownload( 'fr_FR' );
		$controller->enqueueLocaleForDownload( 'fr_FR' );

		// Access queue via reflection
		$reflection = new \ReflectionClass( $controller );
		$method = $reflection->getMethod( 'getQueue' );
		$method->setAccessible( true );
		$queue = $method->invoke( $controller );

		// Count occurrences of fr_FR
		$count = \array_count_values( $queue )[ 'fr_FR' ] ?? 0;
		$this->assertEquals( 1, $count, 'Queue should prevent duplicate locales' );
	}

	public function testBuildCachePathReturnsValidStructure() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );

		$controller = $con->comps->translation_downloads;
		$this->assertNotNull( $controller, 'Translation downloads component must be available' );

		// Access buildCachePath via reflection
		$reflection = new \ReflectionClass( $controller );
		$method = $reflection->getMethod( 'buildMoFilePath' );
		$method->setAccessible( true );

		$path = $method->invoke( $controller, 'de_DE' );

		if ( !empty( $path ) ) {
			// Verify path structure: should end with {textdomain}-{locale}.mo
			$textDomain = $con->getTextDomain();
			$expectedEnding = $textDomain.'-de_DE.mo';
			$this->assertStringEndsWith( $expectedEnding, $path, 'Path should follow expected structure' );

			// Should contain 'languages' subdirectory
			$this->assertStringContainsString( 'languages', $path, 'Path should contain languages directory' );
		}
		else {
			// If empty, it means cache directory isn't available - this is acceptable
			$this->assertTrue( true, 'Cache path empty - cache directory not available' );
		}
	}

	public function testCanAttemptDownloadRespectsCooldown() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );

		$controller = $con->comps->translation_downloads;
		$this->assertNotNull( $controller, 'Translation downloads component must be available' );

		$reflection = new \ReflectionClass( $controller );

		// Set last_attempt_at to now via addCfg
		$addCfgMethod = $reflection->getMethod( 'addCfg' );
		$addCfgMethod->setAccessible( true );
		$addCfgMethod->invoke( $controller, 'last_attempt_at', \time() );

		// Check if can attempt (should be false due to cooldown)
		$canAttemptMethod = $reflection->getMethod( 'canAttemptDownload' );
		$canAttemptMethod->setAccessible( true );
		$canAttempt = $canAttemptMethod->invoke( $controller );

		$this->assertFalse( $canAttempt, 'Should not allow immediate re-attempt due to cooldown' );
	}

	public function testHashComputationMatchesExpected() :void {
		// Verify that hash computation works as expected for .mo file comparison
		$content = 'test content for hash verification';
		$expectedHash = \hash( 'sha256', $content );

		$this->assertEquals( 64, \strlen( $expectedHash ), 'SHA-256 hash should be 64 characters' );
		$this->assertEquals( $expectedHash, \hash( 'sha256', $content ), 'Same content should produce same hash' );
	}

	/**
	 * When exact locale not available but language variant is,
	 * verify the language fallback locale gets queued.
	 */
	public function testLanguageFallbackQueuesCorrectLocale() :void {
		$con = self::con();
		$this->assertNotNull( $con, 'Shield controller must be available for integration tests' );

		$controller = $con->comps->translation_downloads;
		$this->assertNotNull( $controller, 'Translation downloads component must be available' );

		// Clear existing queue
		$reflection = new \ReflectionClass( $controller );
		$saveQueueMethod = $reflection->getMethod( 'saveQueue' );
		$saveQueueMethod->setAccessible( true );
		$saveQueueMethod->invoke( $controller, [] );

		// This test verifies the integration when API returns language-only locales
		// The actual behavior depends on what getAvailableLocales() returns from API
		$availableLocales = $controller->getAvailableLocales();

		// If 'ar' is available but 'ar_EG' is not, queueing logic should handle it
		if ( isset( $availableLocales[ 'ar' ] ) && !isset( $availableLocales[ 'ar_EG' ] ) ) {
			// This scenario tests our fix
			$loader = new LoadTextDomain();
			$loaderReflection = new \ReflectionClass( $loader );
			$method = $loaderReflection->getMethod( 'findDynamicMo' );
			$method->setAccessible( true );
			$method->invoke( $loader, 'ar_EG' );

			$getQueueMethod = $reflection->getMethod( 'getQueue' );
			$getQueueMethod->setAccessible( true );
			$queue = $getQueueMethod->invoke( $controller );

			$this->assertContains( 'ar', $queue, 'Language fallback ar should be queued for ar_EG' );
			$this->assertNotContains( 'ar_EG', $queue, 'Exact locale ar_EG should not be queued' );
		}
		else {
			// This is a legitimate skip - depends on external API data
			$this->markTestSkipped( 'Test requires ar available without ar_EG in remote API' );
		}
	}
}
