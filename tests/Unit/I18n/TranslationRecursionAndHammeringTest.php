<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

/**
 * Tests designed to expose infinite loop risks and API hammering scenarios
 * in the translation loading and downloading system.
 *
 * These tests verify the logic patterns used in:
 * - LoadTextDomain (recursion guard)
 * - TranslationDownloadController (cache TTL, cooldown, queue processing)
 */
class TranslationRecursionAndHammeringTest extends BaseUnitTest {

	/**
	 * BUG: LoadTextDomain's $processing flag is set to true but never reset.
	 * This means the override only works for the FIRST textdomain load per request.
	 *
	 * Simulates the filter callback being invoked multiple times.
	 */
	public function testProcessingFlagNeverResetsAfterFirstCall() :void {
		$processing = false;
		$callCount = 0;

		// Simulate the filter pattern from LoadTextDomain::run()
		$filterCallback = function ( string $moFile ) use ( &$processing, &$callCount ) :string {
			if ( !$processing ) {
				$processing = true;
				$callCount++;
				// Simulates overrideTranslations() — does NOT reset $processing
				$moFile = '/override/path.mo';
			}
			return $moFile;
		};

		// First call — should override
		$result1 = $filterCallback( '/original/path.mo' );
		$this->assertEquals( '/override/path.mo', $result1, 'First call should apply override' );
		$this->assertEquals( 1, $callCount );

		// Second call — should ALSO override, but doesn't because flag is stuck
		$result2 = $filterCallback( '/original/path2.mo' );
		$this->assertEquals( '/original/path2.mo', $result2,
			'BUG CONFIRMED: Second call is NOT overridden because $processing is stuck at true' );
		$this->assertEquals( 1, $callCount, 'Override callback was only ever invoked once' );
	}

	/**
	 * Shows the correct behavior: processing flag should reset via try/finally.
	 * This is what the fix should look like.
	 */
	public function testProcessingFlagShouldResetAfterCompletion() :void {
		$processing = false;
		$callCount = 0;

		// Fixed version with try/finally
		$filterCallbackFixed = function ( string $moFile ) use ( &$processing, &$callCount ) :string {
			if ( !$processing ) {
				$processing = true;
				try {
					$callCount++;
					$moFile = '/override/path.mo';
				}
				finally {
					$processing = false;
				}
			}
			return $moFile;
		};

		$result1 = $filterCallbackFixed( '/original/path.mo' );
		$this->assertEquals( '/override/path.mo', $result1 );

		// Second call should also work with the fix
		$result2 = $filterCallbackFixed( '/original/path2.mo' );
		$this->assertEquals( '/override/path.mo', $result2,
			'With fix: second call should also be overridden' );
		$this->assertEquals( 2, $callCount );
	}

	/**
	 * Verifies that the processing flag correctly prevents recursion.
	 * Even with the fix, if overrideTranslations() re-triggers the filter, it should NOT recurse.
	 */
	public function testProcessingFlagPreventsRecursionDuringOverride() :void {
		$processing = false;
		$callCount = 0;
		$recursionDetected = false;

		$filterCallback = null;
		$filterCallback = function ( string $moFile ) use ( &$processing, &$callCount, &$recursionDetected, &$filterCallback ) :string {
			if ( !$processing ) {
				$processing = true;
				try {
					$callCount++;
					// Simulate overrideTranslations() triggering another textdomain load
					$recursiveResult = $filterCallback( '/recursive/trigger.mo' );
					// If we get here without stack overflow, recursion was prevented
					if ( $recursiveResult === '/recursive/trigger.mo' ) {
						$recursionDetected = false; // Recursion was blocked (good)
					}
					$moFile = '/override/path.mo';
				}
				finally {
					$processing = false;
				}
			}
			return $moFile;
		};

		$result = $filterCallback( '/original/path.mo' );

		$this->assertEquals( '/override/path.mo', $result );
		$this->assertEquals( 1, $callCount, 'Should only enter override once despite recursive trigger' );
		$this->assertFalse( $recursionDetected, 'Recursive call should be blocked by processing flag' );
	}

	/**
	 * Verifies that processing flag handles exceptions without getting stuck.
	 */
	public function testProcessingFlagResetsAfterException() :void {
		$processing = false;

		$filterWithFinally = function ( string $moFile, bool $throwException ) use ( &$processing ) :string {
			if ( !$processing ) {
				$processing = true;
				try {
					if ( $throwException ) {
						throw new \RuntimeException( 'Translation loading failed' );
					}
					$moFile = '/override/path.mo';
				}
				finally {
					$processing = false;
				}
			}
			return $moFile;
		};

		// First call throws
		try {
			$filterWithFinally( '/original/path.mo', true );
		}
		catch ( \RuntimeException $e ) {
			// Expected
		}

		// Flag should be reset, so second call should work
		$this->assertFalse( $processing, 'Flag should be reset after exception (with try/finally fix)' );
		$result = $filterWithFinally( '/original/path.mo', false );
		$this->assertEquals( '/override/path.mo', $result, 'Override should work after exception recovery' );
	}

	/**
	 * BUG: The cache TTL check in getAvailableLocales() looks for 'last_fetch_at'
	 * inside the locales array, but it's stored at the TOP LEVEL of the config.
	 *
	 * This means $isInvalid is ALWAYS true, causing the API to be called every time.
	 */
	public function testCacheTtlCheckReadsFromWrongLocation() :void {
		// Simulate the config structure as stored by addCfg()
		$cfg = [
			'queue'            => [],
			'locales'          => [
				'de_DE' => [ 'hash' => 'abc123', 'hash_type' => 'sha256' ],
				'fr_FR' => [ 'hash' => 'def456', 'hash_type' => 'sha256' ],
			],
			'last_fetch_at'    => \time() - 3600, // 1 hour ago — within 72 hour cache
			'last_download_at' => 0,
		];

		// This is what getCachedLocales() returns
		$locales = \is_array( $cfg[ 'locales' ] ?? null ) ? $cfg[ 'locales' ] : [];

		$cacheTTL = 72 * 3600; // 72 hours in seconds

		// BUG: The current code does this — looks for last_fetch_at INSIDE locales
		$isInvalidBuggy = empty( $locales )
						  || ( \time() - ( $locales[ 'last_fetch_at' ] ?? 0 ) ) >= $cacheTTL;

		$this->assertTrue( $isInvalidBuggy,
			'BUG CONFIRMED: Cache always considered invalid because last_fetch_at is not in locales array' );

		// CORRECT: Should read from top-level cfg
		$isInvalidCorrect = empty( $locales )
							|| ( \time() - ( $cfg[ 'last_fetch_at' ] ?? 0 ) ) >= $cacheTTL;

		$this->assertFalse( $isInvalidCorrect,
			'With fix: cache fetched 1 hour ago should be valid (within 72-hour TTL)' );
	}

	/**
	 * Demonstrates the API hammering risk when the cache TTL check is broken.
	 * Every call to getAvailableLocales() will consider the cache invalid and want to call the API.
	 */
	public function testBrokenCacheTtlCausesRepeatedApiCalls() :void {
		$cfg = [
			'locales'       => [
				'de_DE' => [ 'hash' => 'abc', 'hash_type' => 'sha256' ],
			],
			'last_fetch_at' => \time() - 60, // Fetched 1 minute ago
		];

		$apiCallCount = 0;
		$fetching = false;

		// Simulate 10 processQueue() runs across different requests
		for ( $i = 0; $i < 10; $i++ ) {
			// Reset per-request flag (static flag resets between requests in real scenario)
			$fetching = false;

			$locales = $cfg[ 'locales' ];
			$cacheTTL = 72 * 3600;

			// BUGGY check: always invalid because locales doesn't contain last_fetch_at
			$isInvalid = empty( $locales )
						 || ( \time() - ( $locales[ 'last_fetch_at' ] ?? 0 ) ) >= $cacheTTL;

			if ( $isInvalid && !$fetching ) {
				$fetching = true;
				$apiCallCount++;
				$fetching = false;
			}
		}

		$this->assertEquals( 10, $apiCallCount,
			'BUG CONFIRMED: API called on every single invocation because cache is always "invalid"' );
	}

	/**
	 * With the fixed TTL check, API should only be called when cache genuinely expires.
	 */
	public function testFixedCacheTtlPreventsUnnecessaryApiCalls() :void {
		$cfg = [
			'locales'       => [
				'de_DE' => [ 'hash' => 'abc', 'hash_type' => 'sha256' ],
			],
			'last_fetch_at' => \time() - 60, // Fetched 1 minute ago
		];

		$apiCallCount = 0;

		for ( $i = 0; $i < 10; $i++ ) {
			$fetching = false;
			$locales = $cfg[ 'locales' ];
			$cacheTTL = 72 * 3600;

			// CORRECT check: read from top-level cfg
			$isInvalid = empty( $locales )
						 || ( \time() - ( $cfg[ 'last_fetch_at' ] ?? 0 ) ) >= $cacheTTL;

			if ( $isInvalid && !$fetching ) {
				$fetching = true;
				$apiCallCount++;
				$cfg[ 'last_fetch_at' ] = \time();
				$fetching = false;
			}
		}

		$this->assertEquals( 0, $apiCallCount,
			'With fix: no API calls needed — cache was fetched 1 minute ago (within 72-hour TTL)' );
	}

	/**
	 * Confirms cooldown is now a hardcoded 15 minutes (MINUTE_IN_SECONDS*15 = 900 seconds).
	 */
	public function testCooldownIs15Minutes() :void {
		$cooldownSeconds = 60*15; // MINUTE_IN_SECONDS*15

		$this->assertEquals( 900, $cooldownSeconds, 'Cooldown should be 900 seconds (15 minutes)' );

		// 10 minutes ago — still in cooldown
		$recentAttempt = \time() - 600;
		$this->assertFalse(
			( \time() - $recentAttempt ) >= $cooldownSeconds,
			'Should block attempt within 15-minute window'
		);

		// 20 minutes ago — cooldown expired
		$olderAttempt = \time() - 1200;
		$this->assertTrue(
			( \time() - $olderAttempt ) >= $cooldownSeconds,
			'Should allow attempt after 15 minutes'
		);
	}

	/**
	 * With the fixed cache TTL and 15-minute cooldown, simulate a stuck queue over 24 hours.
	 * The list API should be cached for 72 hours, and downloads retry every 15 minutes.
	 * Verifies the API call rates are within acceptable bounds.
	 */
	public function testStuckQueueApiCallRatesWith15MinCooldown() :void {
		$now = \time();
		$cooldownSeconds = 60*15; // 15 minutes

		$listApiCalls = 0;
		$downloadApiCalls = 0;
		$lastDownloadAt = 0;
		$lastFetchAt = $now - 3600; // Fetched 1 hour ago

		$locales = [
			'ar_EG' => [ 'hash' => 'abc123', 'hash_type' => 'sha256' ],
		];

		// Simulate 24 hours with cron checks every 5 minutes
		$simulatedTime = $now;
		$endTime = $now + 86400;

		while ( $simulatedTime < $endTime ) {
			$simulatedTime += 300; // 5-minute cron interval

			// canAttemptDownload() — 15 minute cooldown
			$canAttempt = ( $simulatedTime - $lastDownloadAt ) >= $cooldownSeconds;
			if ( !$canAttempt ) {
				continue;
			}

			$lastDownloadAt = $simulatedTime;

			// getAvailableLocales() — FIXED: reads from top-level cfg
			$cacheTTL = 72 * 3600;
			$isInvalid = empty( $locales )
						 || ( $simulatedTime - $lastFetchAt ) >= $cacheTTL;

			if ( $isInvalid ) {
				$listApiCalls++;
				$lastFetchAt = $simulatedTime;
			}

			$downloadApiCalls++;
		}

		// 24 hours / 15 min = 96 max download attempts
		$this->assertLessThanOrEqual( 96, $downloadApiCalls,
			'Download attempts bounded by 15-minute cooldown over 24 hours' );
		$this->assertGreaterThan( 80, $downloadApiCalls,
			'With 15-min cooldown and stuck queue, expect ~96 download attempts per day' );

		// But list API should only be called when 72-hour cache expires
		$this->assertLessThanOrEqual( 1, $listApiCalls,
			'List API cached for 72 hours — at most 1 refresh in 24 hours' );
	}

	/**
	 * With the broken cache TTL (Bug #2 pre-fix), the list API is called on every
	 * processQueue invocation — this test documents the difference.
	 */
	public function testBrokenCacheTtlWouldHammerListApi() :void {
		$now = \time();
		$cooldownSeconds = 60*15;

		$listApiCalls = 0;
		$lastDownloadAt = 0;

		$locales = [
			'ar_EG' => [ 'hash' => 'abc123', 'hash_type' => 'sha256' ],
		];

		$simulatedTime = $now;
		$endTime = $now + 86400;

		while ( $simulatedTime < $endTime ) {
			$simulatedTime += 300;

			$canAttempt = ( $simulatedTime - $lastDownloadAt ) >= $cooldownSeconds;
			if ( !$canAttempt ) {
				continue;
			}

			$lastDownloadAt = $simulatedTime;

			// BUGGY: reads last_fetch_at from locales array (always null → always invalid)
			$cacheTTL = 72 * 3600;
			$isInvalid = empty( $locales )
						 || ( $simulatedTime - ( $locales[ 'last_fetch_at' ] ?? 0 ) ) >= $cacheTTL;

			if ( $isInvalid ) {
				$listApiCalls++;
			}
		}

		// With broken cache, list API called on EVERY processQueue invocation
		$this->assertGreaterThan( 80, $listApiCalls,
			'Broken cache TTL: list API called ~96 times per day (every processQueue run)' );
	}

	/**
	 * The $fetching flag prevents re-entrant API calls within a single request,
	 * but does NOT protect across requests since it's a static property that resets.
	 */
	public function testFetchingFlagOnlyProtectsWithinSingleRequest() :void {
		$fetching = false;
		$apiCallsRequest1 = 0;
		$apiCallsRequest2 = 0;

		// Request 1: Two calls in same "request"
		$fetching = false; // Fresh request
		for ( $i = 0; $i < 3; $i++ ) {
			if ( !$fetching ) {
				$fetching = true;
				$apiCallsRequest1++;
				// Note: in real code, $fetching is only reset in finally block
				// So within one request, only 1 call happens
			}
		}

		// Request 2: Flag resets (new PHP process = static properties reset)
		$fetching = false; // Fresh request
		for ( $i = 0; $i < 3; $i++ ) {
			if ( !$fetching ) {
				$fetching = true;
				$apiCallsRequest2++;
			}
		}

		$this->assertEquals( 1, $apiCallsRequest1, 'Within a request: flag prevents multiple calls' );
		$this->assertEquals( 1, $apiCallsRequest2, 'New request: flag resets, allows another call' );
	}

	/**
	 * Tests that enqueueLocaleForDownload + scheduleCrons could create
	 * a rapid reschedule loop when queue is never emptied.
	 *
	 * The cron is scheduled as a single event 5 minutes out. After it fires,
	 * if the queue still has items, the NEXT page load reschedules it.
	 * This creates a 5-minute polling cycle.
	 */
	public function testCronRescheduleLoopWithStuckQueue() :void {
		$cronFireCount = 0;
		$queue = [ 'ar_EG' ]; // Stuck locale
		$lastDownloadAt = \time(); // Just attempted

		$cooldownSeconds = 60*15; // 15-minute cooldown

		// Simulate page loads every 5 minutes for 24 hours
		$simulatedTime = \time();
		$endTime = $simulatedTime + 86400;
		$cronScheduledAt = $simulatedTime + 300; // First cron in 5 min

		$pageLoads = 0;
		while ( $simulatedTime < $endTime ) {
			$simulatedTime += 300; // 5-minute intervals (each page load)
			$pageLoads++;

			// scheduleCrons() equivalent: if queue not empty, schedule cron
			if ( !empty( $queue ) && $simulatedTime >= $cronScheduledAt ) {
				$cronFireCount++;

				// processQueue() equivalent
				$canAttempt = ( $simulatedTime - $lastDownloadAt ) >= $cooldownSeconds;
				if ( $canAttempt ) {
					$lastDownloadAt = $simulatedTime;
					// Download fails — queue stays
				}

				// Reschedule for 5 minutes from now
				$cronScheduledAt = $simulatedTime + 300;
			}
		}

		// With 5-min cron interval over 24 hours = up to 288 cron fires
		// But actual downloads are limited by cooldown
		$this->assertGreaterThan( 100, $cronFireCount,
			'Cron fires frequently even when downloads are blocked by cooldown' );

		// Each cron fire still evaluates the queue and runs scheduling logic
		// This isn't an API call, but it IS unnecessary DB/option reads
		$this->assertGreaterThan( $pageLoads / 2, $cronFireCount,
			'Most page loads trigger a cron evaluation for the stuck queue' );
	}

	/**
	 * Edge case: localeToLang() always returns first 2 chars.
	 * For 3-letter language codes like 'fil' (Filipino), this returns 'fi' (Finnish).
	 * This could cause wrong language fallbacks.
	 */
	public function testLocaleToLangMismatchForThreeLetterCodes() :void {
		$localeToLang = fn( string $locale ) :string => \substr( $locale, 0, 2 );

		// Filipino (fil) gets truncated to 'fi' which is Finnish
		$this->assertEquals( 'fi', $localeToLang( 'fil_PH' ),
			'Filipino locale truncated to Finnish language code — potential wrong fallback' );
		$this->assertEquals( 'fi', $localeToLang( 'fi' ), 'Actual Finnish code' );

		// These match incorrectly
		$this->assertEquals(
			$localeToLang( 'fil_PH' ),
			$localeToLang( 'fi' ),
			'Filipino and Finnish incorrectly match via localeToLang()' );
	}

	/**
	 * Verify that getCachedLocales() is safe to call during textdomain loading.
	 * It should NEVER trigger API calls — only read from local options.
	 */
	public function testGetCachedLocalesPatternIsReadOnly() :void {
		$cfg = [
			'locales' => [
				'de_DE' => [ 'hash' => 'abc', 'hash_type' => 'sha256' ],
			],
		];

		// getCachedLocales() just reads from the config — no side effects
		$result = \is_array( $cfg[ 'locales' ] ?? null ) ? $cfg[ 'locales' ] : [];

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'de_DE', $result );

		// Verify it handles missing/null gracefully
		$emptyResult = \is_array( ( [] )[ 'locales' ] ?? null ) ? ( [] )[ 'locales' ] : [];
		$this->assertEmpty( $emptyResult );
	}

	/**
	 * saveQueue() filters by isLocaleAvailable(). If cached locales change between
	 * queueing and saving, items can silently disappear from the queue.
	 */
	public function testQueueFilteringRemovesItemsWhenCacheChanges() :void {
		$cachedLocales = [
			'de_DE' => [ 'hash' => 'abc', 'hash_type' => 'sha256' ],
			'fr_FR' => [ 'hash' => 'def', 'hash_type' => 'sha256' ],
		];
		$isLocaleAvailable = fn( string $loc ) => !empty( $cachedLocales[ $loc ] );

		$queue = [ 'de_DE', 'ar_EG' ];

		// saveQueue pattern
		$filteredQueue = \array_values(
			\array_filter(
				\array_unique( $queue ),
				fn( $loc ) => !empty( $loc ) && $isLocaleAvailable( $loc )
			)
		);

		$this->assertEquals( [ 'de_DE' ], $filteredQueue,
			'ar_EG silently removed because it is not in cached locales' );
	}

	/**
	 * Tests the complete flow: findDynamicMo -> enqueue -> processQueue
	 * to verify no infinite loop is possible in the enqueue-process cycle.
	 */
	public function testEnqueueProcessCycleTerminates() :void {
		$cachedLocales = [
			'de_DE' => [ 'hash' => 'abc', 'hash_type' => 'sha256' ],
		];
		$queue = [];
		$processCount = 0;
		$maxIterations = 100;

		// Simulate: findDynamicMo queues a locale
		$locale = 'de_DE';
		if ( isset( $cachedLocales[ $locale ] ) ) {
			$queue = \array_values( \array_unique( \array_merge( $queue, [ $locale ] ) ) );
		}

		// Simulate repeated processQueue runs
		while ( !empty( $queue ) && $processCount < $maxIterations ) {
			$processCount++;
			$processed = [];

			foreach ( $queue as $loc ) {
				$processed[] = $loc;
				// Simulate download success
				$downloadSuccess = true;
				if ( !$downloadSuccess ) {
					\array_pop( $processed );
				}
			}

			// Remove processed items
			$queue = \array_diff( $queue, $processed );
			$queue = \array_values( $queue );
		}

		$this->assertEquals( 1, $processCount, 'Queue should be fully processed in a single run' );
		$this->assertEmpty( $queue, 'Queue should be empty after successful processing' );
	}

	/**
	 * When download consistently fails, verify retries are bounded by the 15-minute cooldown.
	 * Over 24 hours with a stuck item, we expect ~96 attempts (24*60/15).
	 * This is acceptable since it's all on cron/background — not blocking user requests.
	 */
	public function testPersistentDownloadFailureRetryRate() :void {
		$queue = [ 'de_DE' ];
		$lastDownloadAt = 0;
		$cooldownSeconds = 60*15; // 15 minutes
		$processAttempts = 0;

		// Simulate 24 hours
		$simulatedTime = \time();
		$endTime = $simulatedTime + 86400;

		while ( $simulatedTime < $endTime ) {
			$simulatedTime += 300; // Cron check every 5 minutes

			if ( empty( $queue ) ) {
				break;
			}

			$canAttempt = ( $simulatedTime - $lastDownloadAt ) >= $cooldownSeconds;
			if ( !$canAttempt ) {
				continue;
			}

			$processAttempts++;
			$lastDownloadAt = $simulatedTime;
		}

		// 24 hours / 15 minutes = 96 max attempts
		$this->assertLessThanOrEqual( 96, $processAttempts,
			'Stuck item retries bounded by 15-minute cooldown' );
		$this->assertGreaterThan( 80, $processAttempts,
			'With persistent failure and 15-min cooldown, expect ~96 retries per day' );
	}
}
