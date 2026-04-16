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

	public function test_build_adds_compact_tokens_for_hyphenated_terms() :void {
		$tokens = \explode( ' ', ( new SearchTextTokenBuilder() )->build( [
			'Disable XML-RPC',
			'In-Plugin Notices',
		] ) );

		$this->assertContains( 'xml', $tokens );
		$this->assertContains( 'rpc', $tokens );
		$this->assertContains( 'xmlrpc', $tokens );
		$this->assertContains( 'plugin', $tokens );
		$this->assertContains( 'inplugin', $tokens );
	}

	public function test_extract_terms_normalizes_hyphenated_queries_for_matching() :void {
		$builder = new SearchTextTokenBuilder();

		$this->assertSame(
			[ 'xml', 'rpc', 'xmlrpc' ],
			$builder->extractTerms( 'xml-rpc' )
		);
		$this->assertSame(
			[ 'xmlrpc' ],
			$builder->extractTerms( 'xmlrpc' )
		);
		$this->assertSame(
			[ 'plugin', 'inplugin' ],
			$builder->extractTerms( 'In-Plugin' )
		);
	}
}
