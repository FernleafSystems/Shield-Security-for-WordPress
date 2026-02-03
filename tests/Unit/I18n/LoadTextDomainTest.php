<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\LoadTextDomain;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class LoadTextDomainTest extends BaseUnitTest {

	public function testClassExists() :void {
		$this->assertTrue( \class_exists( LoadTextDomain::class ) );
	}

	/**
	 * @dataProvider providerLocaleToLang
	 */
	public function testLocaleToLangExtractsLanguageCode( string $locale, string $expected ) :void {
		$loader = new LoadTextDomain();
		$reflection = new \ReflectionClass( $loader );
		$method = $reflection->getMethod( 'localeToLang' );
		$method->setAccessible( true );

		$this->assertEquals( $expected, $method->invoke( $loader, $locale ) );
	}

	public static function providerLocaleToLang() :array {
		return [
			'full locale ar_EG' => [ 'ar_EG', 'ar' ],
			'full locale de_DE' => [ 'de_DE', 'de' ],
			'full locale pt_BR' => [ 'pt_BR', 'pt' ],
			'full locale zh_CN' => [ 'zh_CN', 'zh' ],
			'short locale ar' => [ 'ar', 'ar' ],
			'short locale de' => [ 'de', 'de' ],
			'three letter fil_PH' => [ 'fil_PH', 'fi' ],
		];
	}

	/**
	 * Test the locale fallback resolution logic pattern.
	 * This tests the algorithm without needing full plugin context.
	 *
	 * @dataProvider providerLocaleFallbackResolution
	 */
	public function testLocaleFallbackResolutionLogic(
		array $availableLocales,
		string $targetLocale,
		?string $expectedQueue
	) :void {
		$localeToLang = fn( string $loc ) => \substr( $loc, 0, 2 );
		$targetLang = $localeToLang( $targetLocale );

		$localeToQueue = null;

		// Exact match first
		if ( isset( $availableLocales[ $targetLocale ] ) ) {
			$localeToQueue = $targetLocale;
		}
		// Language fallback
		elseif ( !empty( $targetLang ) ) {
			foreach ( \array_keys( $availableLocales ) as $maybeLocale ) {
				if ( $targetLang === $localeToLang( $maybeLocale ) ) {
					$localeToQueue = $maybeLocale;
					break;
				}
			}
		}

		$this->assertSame( $expectedQueue, $localeToQueue );
	}

	public static function providerLocaleFallbackResolution() :array {
		return [
			'exact locale available' => [
				[ 'ar_EG' => [], 'de_DE' => [] ],
				'ar_EG',
				'ar_EG',
			],
			'language fallback when exact not available' => [
				[ 'ar' => [], 'de_DE' => [] ],
				'ar_EG',
				'ar',
			],
			'no fallback when neither available' => [
				[ 'de_DE' => [], 'fr_FR' => [] ],
				'ar_EG',
				null,
			],
			'exact preferred over language fallback' => [
				[ 'ar' => [], 'ar_EG' => [] ],
				'ar_EG',
				'ar_EG',
			],
			'language fallback picks first match' => [
				[ 'ar_SA' => [], 'ar_EG' => [] ],
				'ar_MA',
				'ar_SA',
			],
			'zh_CN with only zh_TW available' => [
				[ 'zh_TW' => [], 'de_DE' => [] ],
				'zh_CN',
				'zh_TW',
			],
			'pt_BR with only pt_PT available' => [
				[ 'pt_PT' => [], 'de_DE' => [] ],
				'pt_BR',
				'pt_PT',
			],
			'short locale en with en_US available' => [
				[ 'en_US' => [], 'en_GB' => [] ],
				'en',
				'en_US',
			],
		];
	}
}
