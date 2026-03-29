<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ShieldNetApi\Reputation;

use FernleafSystems\Wordpress\Services\Core\General;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\SendCanonicalIpEvidence;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\SendLegacyIpReputation;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class SendIpTelemetryTest extends BaseUnitTest {

	private $origServiceItems;

	private $origServices;

	protected function setUp() :void {
		parent::setUp();
		$this->origServiceItems = $this->getServicesProperty( 'items' )->getValue();
		$this->origServices = $this->getServicesProperty( 'services' )->getValue();
	}

	protected function tearDown() :void {
		$this->getServicesProperty( 'items' )->setValue( null, $this->origServiceItems );
		$this->getServicesProperty( 'services' )->setValue( null, $this->origServices );
		parent::tearDown();
	}

	public function testLegacySenderUsesExpectedRouteAndBodyShape() :void {
		$payload = [
			[
				'ip'      => '198.51.100.61',
				'signals' => [ 'bt404' ],
			],
		];

		$sender = new class extends SendLegacyIpReputation {
			public array $capturedBody = [];

			protected function sendReq() :?array {
				$this->capturedBody = $this->params_body;
				return [];
			}
		};

		$this->assertSame( 'ip/reputation/receive', SendLegacyIpReputation::API_ACTION );
		$this->assertTrue( $sender->send( $payload ) );
		$this->assertSame( [ 'ip_signals' => $payload ], $sender->capturedBody );
	}

	public function testCanonicalSenderUsesExpectedRouteAndBodyShape() :void {
		$this->injectWpGeneralService( new class extends General {
			public function getHomeUrl( string $path = '', bool $wpms = false ) :string {
				return 'https://example.com';
			}
		} );

		$payload = [
			[
				'ip'       => '198.51.100.62',
				'evidence' => [ 'recon', 'enforcement' ],
			],
		];

		$sender = new class extends SendCanonicalIpEvidence {
			public array $capturedBody = [];

			protected function getShieldNetApiParams() :array {
				return [];
			}

			protected function sendReq() :?array {
				$this->capturedBody = $this->params_body;
				return [];
			}
		};

		$this->assertSame( 'ip/evidence/receive', SendCanonicalIpEvidence::API_ACTION );
		$this->assertTrue( $sender->send( $payload ) );
		$this->assertSame( [
			'reporting_url' => 'https://example.com',
			'ip_evidence'   => $payload,
		], $sender->capturedBody );
	}

	private function injectWpGeneralService( General $general ) :void {
		$this->getServicesProperty( 'items' )->setValue( null, [
			'service_wpgeneral' => $general,
		] );
		$this->getServicesProperty( 'services' )->setValue( null, null );
	}

	private function getServicesProperty( string $propertyName ) :\ReflectionProperty {
		$reflection = new \ReflectionClass( Services::class );
		$property = $reflection->getProperty( $propertyName );
		$property->setAccessible( true );
		return $property;
	}
}
