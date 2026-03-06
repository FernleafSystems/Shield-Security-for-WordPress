<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayText;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class CommonDisplayTextTest extends BaseUnitTest {

	public function test_truncate_returns_original_text_when_under_limit() :void {
		$this->assertSame( 'Shield', CommonDisplayText::truncate( 'Shield', 20 ) );
	}

	public function test_truncate_applies_existing_suffix_when_over_limit() :void {
		$this->assertSame(
			'Shield (...truncated)',
			CommonDisplayText::truncate( 'Shield Security', 6 )
		);
	}

	public function test_truncate_respects_custom_length() :void {
		$this->assertSame(
			'abc (...truncated)',
			CommonDisplayText::truncate( 'abcdef', 3 )
		);
	}
}
