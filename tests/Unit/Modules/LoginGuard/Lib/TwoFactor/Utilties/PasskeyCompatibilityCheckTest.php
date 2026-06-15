<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\LoginGuard\Lib\TwoFactor\Utilties;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Utilties\PasskeyCompatibilityCheck;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class PasskeyCompatibilityCheckTest extends BaseUnitTest {

	public function test_passkey_runtime_requires_all_declared_capabilities() :void {
		$checker = new PasskeyCompatibilityCheckTestDouble(
			[ 'json', 'openssl' ],
			[ 'mb_strlen' ]
		);

		$this->assertTrue( $checker->run() );
	}

	public function test_missing_required_extension_fails_check() :void {
		$checker = new PasskeyCompatibilityCheckTestDouble(
			[ 'json' ],
			[ 'mb_strlen' ]
		);

		$this->assertFalse( $checker->run() );
	}

	public function test_missing_required_function_fails_check() :void {
		$checker = new PasskeyCompatibilityCheckTestDouble(
			[ 'json', 'openssl' ],
			[]
		);

		$this->assertFalse( $checker->run() );
	}
}

class PasskeyCompatibilityCheckTestDouble extends PasskeyCompatibilityCheck {

	/**
	 * @param string[] $loadedExtensions
	 * @param string[] $availableFunctions
	 */
	public function __construct(
		private array $loadedExtensions,
		private array $availableFunctions
	) {
	}

	protected function isExtensionLoaded( string $extension ) :bool {
		return \in_array( $extension, $this->loadedExtensions, true );
	}

	protected function isFunctionAvailable( string $function ) :bool {
		return \in_array( $function, $this->availableFunctions, true );
	}
}
