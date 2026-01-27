<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ShieldNetApi\Translations;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Translations\DownloadTranslation;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

/**
 * Unit tests for DownloadTranslation ShieldNet API client
 */
class DownloadTranslationTest extends BaseUnitTest {

	public function testApiActionIsCorrect() :void {
		$this->assertEquals( 'translations/download', DownloadTranslation::API_ACTION );
	}

	public function testExtendsBaseShieldNetApiV2() :void {
		$api = new DownloadTranslation();
		$this->assertInstanceOf( BaseShieldNetApiV2::class, $api );
	}

	public function testIsValidLocaleFormatAcceptsValidLocales() :void {
		$api = new DownloadTranslation();
		$reflection = new \ReflectionClass( $api );
		$method = $reflection->getMethod( 'isValidLocaleFormat' );
		$method->setAccessible( true );

		// Valid formats
		$validLocales = [ 'de_DE', 'fr_FR', 'en_US', 'ja', 'zh_CN', 'pt_BR' ];
		foreach ( $validLocales as $locale ) {
			$this->assertTrue(
				$method->invoke( $api, $locale ),
				"Locale '$locale' should be valid"
			);
		}
	}

	public function testIsValidLocaleFormatRejectsInvalidLocales() :void {
		$api = new DownloadTranslation();
		$reflection = new \ReflectionClass( $api );
		$method = $reflection->getMethod( 'isValidLocaleFormat' );
		$method->setAccessible( true );

		// Invalid formats
		$invalidLocales = [ '', 'invalid', 'DE_de', 'de_de', 'de-DE', 'de_DEE' ];
		foreach ( $invalidLocales as $locale ) {
			$this->assertFalse(
				$method->invoke( $api, $locale ),
				"Locale '$locale' should be invalid"
			);
		}
	}

	public function testLooksLikeJsonErrorDetectsErrors() :void {
		$api = new DownloadTranslation();
		$reflection = new \ReflectionClass( $api );
		$method = $reflection->getMethod( 'looksLikeJsonError' );
		$method->setAccessible( true );

		// JSON error response
		$jsonError = '{"error_code": 404, "message": "Not found"}';
		$this->assertTrue( $method->invoke( $api, $jsonError ) );

		// Binary content (valid .mo file start)
		$binaryContent = \pack( 'N', 0x950412de ) . 'binary data';
		$this->assertFalse( $method->invoke( $api, $binaryContent ) );
	}

	public function testDownloadReturnsNullForInvalidLocale() :void {
		$api = new DownloadTranslation();
		$result = $api->download( '' );
		$this->assertNull( $result );

		$result = $api->download( 'invalid' );
		$this->assertNull( $result );
	}
}
