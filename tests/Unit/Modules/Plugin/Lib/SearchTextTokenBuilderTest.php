<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SearchTextTokenBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class SearchTextTokenBuilderTest extends BaseUnitTest {

	public function test_build_normalizes_terms_and_adds_singular_plural_variants() :void {
		$tokens = \explode( ' ', ( new SearchTextTokenBuilder() )->build( [
			'Settings: silentCAPTCHA bots',
			'IP',
		] ) );

		$this->assertContains( 'settings', $tokens );
		$this->assertContains( 'setting', $tokens );
		$this->assertContains( 'silentcaptcha', $tokens );
		$this->assertContains( 'bots', $tokens );
		$this->assertContains( 'bot', $tokens );
		$this->assertNotContains( 'ip', $tokens );
	}
}
