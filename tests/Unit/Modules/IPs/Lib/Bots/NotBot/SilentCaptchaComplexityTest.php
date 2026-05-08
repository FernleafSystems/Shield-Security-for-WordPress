<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\SilentCaptchaComplexity;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class SilentCaptchaComplexityTest extends BaseUnitTest {

	public function test_legacy_normalises_to_low() :void {
		$this->assertSame( SilentCaptchaComplexity::LOW, SilentCaptchaComplexity::normalise( 'legacy' ) );
	}

	public function test_unknown_complexity_normalises_to_medium() :void {
		$this->assertSame( SilentCaptchaComplexity::MEDIUM, SilentCaptchaComplexity::normalise( 'unexpected' ) );
	}

	public function test_current_public_values_remain_valid() :void {
		foreach ( [
			SilentCaptchaComplexity::NONE,
			SilentCaptchaComplexity::ADAPTIVE,
			SilentCaptchaComplexity::LOW,
			SilentCaptchaComplexity::MEDIUM,
			SilentCaptchaComplexity::HIGH,
		] as $value ) {
			$this->assertSame( $value, SilentCaptchaComplexity::normalise( $value ) );
		}
	}
}
