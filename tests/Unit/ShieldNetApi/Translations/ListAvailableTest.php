<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ShieldNetApi\Translations;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common\BaseShieldNetApiV2;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Translations\ListAvailable;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

/**
 * Unit tests for ListAvailable ShieldNet API client
 */
class ListAvailableTest extends BaseUnitTest {

	public function testApiActionIsCorrect() :void {
		$this->assertEquals( 'translations/list', ListAvailable::API_ACTION );
	}

	public function testExtendsBaseShieldNetApiV2() :void {
		$api = new ListAvailable();
		$this->assertInstanceOf( BaseShieldNetApiV2::class, $api );
	}

	public function testRetrieveReturnsLocalesFromSuccessfulResponse() :void {
		$locales = [
			'de_DE' => [
				'hash'      => 'abc123',
				'hash_type' => 'sha256',
			],
		];

		$api = new class( $locales ) extends ListAvailable {
			private array $locales;

			public function __construct( array $locales ) {
				$this->locales = $locales;
			}

			protected function sendReq() :?array {
				return [
					'error_code' => 0,
					'locales'    => $this->locales,
				];
			}
		};

		$this->assertSame( $locales, $api->retrieve() );
	}

	public function testRetrieveReturnsNullForFailedResponse() :void {
		$api = new class extends ListAvailable {
			protected function sendReq() :?array {
				return [ 'error_code' => 1 ];
			}
		};

		$this->assertNull( $api->retrieve() );
	}
}
