<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\I18n;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\I18n\LocaleLanguageMatcher;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class LocaleLanguageMatcherTest extends BaseUnitTest {

	/**
	 * @dataProvider providerLocaleToLang
	 */
	public function testLocaleToLang( string $locale, string $expectedLang ) :void {
		$this->assertSame( $expectedLang, ( new LocaleLanguageMatcher() )->localeToLang( $locale ) );
	}

	public static function providerLocaleToLang() :array {
		return [
			'locale de_DE'        => [ 'de_DE', 'de' ],
			'locale zh_CN'        => [ 'zh_CN', 'zh' ],
			'language only en'    => [ 'en', 'en' ],
			'uppercase language'  => [ 'PT', 'pt' ],
			'trimmed locale'      => [ ' fr_FR ', 'fr' ],
			'three letter prefix' => [ 'fil_PH', 'fi' ],
		];
	}

	/**
	 * @dataProvider providerFirstLocaleForLanguage
	 */
	public function testGetFirstLocaleForLanguage( array $locales, string $target, ?string $expected ) :void {
		$this->assertSame( $expected, ( new LocaleLanguageMatcher() )->getFirstLocaleForLanguage( $locales, $target ) );
	}

	public static function providerFirstLocaleForLanguage() :array {
		return [
			'exact locale target uses language fallback list' => [
				[ 'de_AT', 'de_DE', 'fr_FR' ],
				'de_CH',
				'de_AT',
			],
			'language target chooses first sorted locale'     => [
				[ 'zh_TW', 'zh_CN', 'zh_HK' ],
				'zh',
				'zh_CN',
			],
			'no matching language returns null'               => [
				[ 'de_DE', 'fr_FR' ],
				'en',
				null,
			],
		];
	}

	/**
	 * @dataProvider providerLocaleLanguageMatches
	 */
	public function testIsLocaleOrLanguageMatch( string $candidate, string $target, bool $expected ) :void {
		$this->assertSame( $expected, ( new LocaleLanguageMatcher() )->isLocaleOrLanguageMatch( $candidate, $target ) );
	}

	public static function providerLocaleLanguageMatches() :array {
		return [
			'exact locale match'               => [ 'fr_FR', 'fr_FR', true ],
			'language match from locale'       => [ 'de_DE', 'de', true ],
			'locale match by language prefix'  => [ 'pt_PT', 'pt_BR', true ],
			'case insensitive language compare' => [ 'en_US', 'EN', true ],
			'different languages do not match' => [ 'fr_FR', 'de_DE', false ],
		];
	}
}
