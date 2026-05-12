<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rules;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\{
	HookAddFilter,
	HttpRedirect,
	SetRequestToBeLogged
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\{
	ResponseParamsNormalizer,
	VerifyParams
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class VerifyParamsTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
	}

	public function test_verify_params_returns_normalized_values_and_defaults() :void {
		$verified = ( new VerifyParams() )->verifyParams(
			[
				'priority'   => '25',
				'is_trusted' => 'N',
			],
			[
				'priority'      => [
					'type'    => EnumParameters::TYPE_INT,
					'default' => 10,
				],
				'accepted_args' => [
					'type'    => EnumParameters::TYPE_INT,
					'default' => 1,
				],
				'is_trusted'    => [
					'type'    => EnumParameters::TYPE_BOOL,
					'default' => true,
				],
			]
		);

		$this->assertSame( 25, $verified[ 'priority' ] );
		$this->assertSame( 1, $verified[ 'accepted_args' ] );
		$this->assertFalse( $verified[ 'is_trusted' ] );
	}

	public function test_legacy_hook_args_are_normalized_to_accepted_args() :void {
		$normalized = ( new ResponseParamsNormalizer() )->normalize(
			HookAddFilter::class,
			[
				'hook'     => 'xmlrpc_enabled',
				'callback' => '__return_false',
				'priority' => '1000',
				'args'     => '0',
			]
		);

		$this->assertArrayNotHasKey( 'args', $normalized );
		$this->assertArrayHasKey( 'accepted_args', $normalized );
	}

	public function test_legacy_log_request_hook_priority_is_normalized_to_priority() :void {
		$normalized = ( new ResponseParamsNormalizer() )->normalize(
			SetRequestToBeLogged::class,
			[
				'do_log'        => true,
				'hook_priority' => '25',
			]
		);

		$this->assertArrayNotHasKey( 'hook_priority', $normalized );
		$this->assertSame( '25', $normalized[ 'priority' ] );

		$verified = ( new VerifyParams() )->verifyParams(
			$normalized,
			( new SetRequestToBeLogged() )->getParamsDef()
		);

		$this->assertTrue( $verified[ 'do_log' ] );
		$this->assertSame( 25, $verified[ 'priority' ] );
	}

	public function test_current_log_request_priority_wins_over_legacy_hook_priority() :void {
		$normalized = ( new ResponseParamsNormalizer() )->normalize(
			SetRequestToBeLogged::class,
			[
				'do_log'        => true,
				'priority'      => '30',
				'hook_priority' => '25',
			]
		);

		$this->assertArrayNotHasKey( 'hook_priority', $normalized );
		$this->assertSame( '30', $normalized[ 'priority' ] );
	}

	public function test_callback_args_remain_argument_array() :void {
		$firstArg = new \stdClass();
		$verified = ( new VerifyParams() )->verifyParams(
			[
				'args' => [ $firstArg, 2 ],
			],
			[
				'args' => [
					'type'    => EnumParameters::TYPE_ARRAY,
					'default' => [],
				],
			]
		);

		$this->assertSame( [ $firstArg, 2 ], $verified[ 'args' ] );
	}

	public function test_redirect_status_code_definition_exposes_integer_contract() :void {
		$statusCodeDef = ( new HttpRedirect() )->getParamsDef()[ 'status_code' ];

		$this->assertSame( [ 301, 302 ], $statusCodeDef[ 'type_enum' ] );
		$this->assertSame( 302, $statusCodeDef[ 'default' ] );
	}

	public function test_redirect_status_code_verification_returns_canonical_integer_value() :void {
		$paramsDef = ( new HttpRedirect() )->getParamsDef();

		$verified = ( new VerifyParams() )->verifyParams(
			[
				'redirect_url' => '/',
				'status_code' => '302',
			],
			$paramsDef
		);

		$this->assertSame( 302, $verified[ 'status_code' ] );
	}

	public function test_redirect_status_code_default_verifies_to_integer_value() :void {
		$verified = ( new VerifyParams() )->verifyParams(
			[
				'redirect_url' => '/',
			],
			( new HttpRedirect() )->getParamsDef()
		);

		$this->assertSame( 302, $verified[ 'status_code' ] );
	}
}
