<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\RequestPolicy;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\ActorTrust;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActorTrustTest extends BaseUnitTest {

	public function test_logged_in_bot_probability_alone_does_not_create_trusted_authentication() :void {
		$trust = new ActorTrust( [
			'is_logged_in'    => true,
			'bot_probability' => 100,
		] );

		$this->assertFalse( $trust->is_trusted_authenticated );
		$this->assertSame( 100, $trust->bot_probability );
	}

	public function test_logged_in_high_reputation_creates_trusted_authentication() :void {
		$trust = new ActorTrust( [
			'is_logged_in'           => true,
			'is_high_reputation_ip' => true,
		] );

		$this->assertTrue( $trust->is_trusted_authenticated );
	}

	public function test_trusted_authenticated_input_does_not_override_primitive_flags() :void {
		$trust = new ActorTrust( [
			'is_trusted_authenticated' => true,
		] );

		$this->assertFalse( $trust->is_trusted_authenticated );
	}
}
