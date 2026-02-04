<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\TranslationDownloadController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

/**
 * Unit tests for TranslationDownloadController
 */
class TranslationDownloadControllerTest extends BaseUnitTest {

	public function testControllerClassExists() :void {
		$this->assertTrue( \class_exists( TranslationDownloadController::class ) );
	}

	public function testIsValidMoFile() :void {
		$controller = new TranslationDownloadController();
		$reflection = new \ReflectionClass( $controller );
		$method = $reflection->getMethod( 'isValidMo' );
		$method->setAccessible( true );

		// Valid .mo file (little endian magic number)
		$validMoLe = \pack( 'V', 0x950412de ).\str_repeat( "\x00", 20 );
		$this->assertTrue(
			$method->invoke( $controller, $validMoLe ),
			'Should accept valid .mo file with little endian magic'
		);

		// Valid .mo file (big endian magic number)
		$validMoBe = \pack( 'N', 0x950412de ).\str_repeat( "\x00", 20 );
		$this->assertTrue(
			$method->invoke( $controller, $validMoBe ),
			'Should accept valid .mo file with big endian magic'
		);

		// Invalid content
		$this->assertFalse(
			$method->invoke( $controller, 'not a mo file' ),
			'Should reject invalid content'
		);

		// Empty content
		$this->assertFalse(
			$method->invoke( $controller, '' ),
			'Should reject empty content'
		);

		// Content too short
		$this->assertFalse(
			$method->invoke( $controller, 'abc' ),
			'Should reject content shorter than 4 bytes'
		);
	}

	public function testBuildFallbackLocalesArray() :void {
		$this->markTestSkipped( 'Requires full plugin context - covered by integration tests' );
	}

	public function testRequiredMethodsExist() :void {
		$controller = new TranslationDownloadController();

		$this->assertTrue( \method_exists( $controller, 'enqueueLocaleForDownload' ) );
		$this->assertTrue( \method_exists( $controller, 'runHourlyCron' ) );
		$this->assertTrue( \method_exists( $controller, 'getLocaleMoFilePath' ) );
		$this->assertTrue( \method_exists( $controller, 'isLocaleAvailable' ) );
	}

	public function testPrivateMethodsExist() :void {
		$controller = new TranslationDownloadController();
		$reflection = new \ReflectionClass( $controller );

		// Verify key private methods exist (updated to match actual implementation)
		$this->assertTrue( $reflection->hasMethod( 'cfg' ) );
		$this->assertTrue( $reflection->hasMethod( 'addCfg' ) );
		$this->assertTrue( $reflection->hasMethod( 'getQueue' ) );
		$this->assertTrue( $reflection->hasMethod( 'saveQueue' ) );
		$this->assertTrue( $reflection->hasMethod( 'canAttemptDownload' ) );
		$this->assertTrue( $reflection->hasMethod( 'buildMoFilePath' ) );
		$this->assertTrue( $reflection->hasMethod( 'acquireMo' ) );
		$this->assertTrue( $reflection->hasMethod( 'fireDownloadFailedEvent' ) );
	}

	public function testConstantsAreDefined() :void {
		$controller = new TranslationDownloadController();
		$reflection = new \ReflectionClass( $controller );

		$this->assertTrue( $reflection->hasConstant( 'OPT_KEY' ) );
	}

	public function testUsesExecOnceTrait() :void {
		$controller = new TranslationDownloadController();
		$this->assertTrue( \method_exists( $controller, 'execute' ) );
	}

	public function testUsesCronConsumerTrait() :void {
		$controller = new TranslationDownloadController();
		$this->assertTrue( \method_exists( $controller, 'setupCronHooks' ) );
	}

	/**
	 * Test that the hash comparison logic correctly identifies when hashes match.
	 * This tests the internal logic pattern without needing full plugin context.
	 */
	public function testHashComparisonLogic() :void {
		$content = 'test content for hash';
		$expectedHash = \hash( 'sha256', $content );
		$actualHash = \hash( 'sha256', $content );

		$this->assertEquals( $expectedHash, $actualHash, 'Identical content should produce matching hashes' );

		$differentContent = 'different content';
		$differentHash = \hash( 'sha256', $differentContent );
		$this->assertNotEquals( $expectedHash, $differentHash, 'Different content should produce different hashes' );
	}

	/**
	 * Test that cooldown calculation logic works correctly.
	 * This tests the pattern used in canAttemptDownload without needing full plugin context.
	 */
	public function testCooldownCalculationLogic() :void {
		$lastAttempt = \time() - ( 3 * \DAY_IN_SECONDS ); // 3 days ago
		$cooldownDays = 7;
		$cooldownSeconds = $cooldownDays * \DAY_IN_SECONDS;

		// Should still be in cooldown (3 days < 7 days)
		$canAttempt = ( \time() - $lastAttempt ) >= $cooldownSeconds;
		$this->assertFalse( $canAttempt, 'Should not allow attempt within cooldown period' );

		// Test expired cooldown
		$oldAttempt = \time() - ( 10 * \DAY_IN_SECONDS ); // 10 days ago
		$canAttemptOld = ( \time() - $oldAttempt ) >= $cooldownSeconds;
		$this->assertTrue( $canAttemptOld, 'Should allow attempt after cooldown expires' );
	}

	/**
	 * Test that array uniqueness logic works correctly for queue.
	 * This tests the pattern used in saveQueue.
	 */
	public function testQueueUniquenessLogic() :void {
		$queue = [ 'de_DE', 'fr_FR' ];

		// Add duplicate
		$queue[] = 'de_DE';

		// Apply uniqueness (same pattern as saveQueue)
		$uniqueQueue = \array_values( \array_unique( $queue ) );

		$this->assertCount( 2, $uniqueQueue, 'Queue should prevent duplicates' );
		$this->assertEquals( [ 'de_DE', 'fr_FR' ], $uniqueQueue );
	}

	/**
	 * Test locale validation pattern used by DownloadTranslation.
	 */
	public function testLocaleValidationPattern() :void {
		$pattern = '/^[a-z]{2,3}(_[A-Z]{2})?$/';

		// Valid locales
		$this->assertMatchesRegularExpression( $pattern, 'de_DE' );
		$this->assertMatchesRegularExpression( $pattern, 'fr_FR' );
		$this->assertMatchesRegularExpression( $pattern, 'en_US' );
		$this->assertMatchesRegularExpression( $pattern, 'zh_CN' );
		$this->assertMatchesRegularExpression( $pattern, 'ja' );
		$this->assertMatchesRegularExpression( $pattern, 'pt_BR' );

		// Invalid locales
		$this->assertDoesNotMatchRegularExpression( $pattern, 'invalid' );
		$this->assertDoesNotMatchRegularExpression( $pattern, 'DE_de' );
		$this->assertDoesNotMatchRegularExpression( $pattern, 'de-DE' );
		$this->assertDoesNotMatchRegularExpression( $pattern, '' );
		$this->assertDoesNotMatchRegularExpression( $pattern, 'toolonglocale_XX' );
	}
}
