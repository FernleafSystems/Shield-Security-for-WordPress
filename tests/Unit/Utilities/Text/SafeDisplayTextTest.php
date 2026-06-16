<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Utilities\Text;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Text\SafeDisplayText;

class SafeDisplayTextTest extends BaseUnitTest {

	public function testInlineNormalisesMultilineAndControlCharacters() :void {
		$this->assertSame(
			'Alpha Beta Gamma Delta',
			SafeDisplayText::inline( " Alpha\r\nBeta\tGamma\x07Delta " )
		);
	}

	public function testInlineStringifiesScalarAndStructuredValues() :void {
		$this->assertSame( '', SafeDisplayText::inline( null ) );
		$this->assertSame( 'true', SafeDisplayText::inline( true ) );
		$this->assertSame( '12.5', SafeDisplayText::inline( 12.5 ) );
		$this->assertSame(
			'{"path":"/wp-admin","enabled":true}',
			SafeDisplayText::inline( [
				'path'    => '/wp-admin',
				'enabled' => true,
			] )
		);
	}

	public function testInlineAppliesDisplayBudgetAfterNormalisation() :void {
		$this->assertSame(
			'0123456789'.SafeDisplayText::TRUNCATION_SUFFIX,
			SafeDisplayText::inline( "0123456789\nabcdef", 10 )
		);
	}

	public function testTruncateReturnsOriginalTextWhenUnderLimit() :void {
		$this->assertSame( 'Shield', SafeDisplayText::truncate( 'Shield', 20 ) );
	}

	public function testTruncateKeepsRequestedByteBudgetBeforeSuffixWhenOverLimit() :void {
		$this->assertSame(
			'Shield Security Plug'.SafeDisplayText::TRUNCATION_SUFFIX,
			SafeDisplayText::truncate( 'Shield Security Plugin', 20 )
		);
	}
}
