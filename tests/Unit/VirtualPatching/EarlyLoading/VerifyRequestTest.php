<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\EarlyLoading;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\VerifyRequest;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request
};

class VerifyRequestTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	private VerifyRequestTestRequest $request;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->request = new VerifyRequestTestRequest();
		ServicesState::installItems( [
			'service_wpgeneral' => new VerifyRequestTestGeneral(),
			'service_request'   => $this->request,
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function testOnlyTheIssuedTokenIsAcceptedUntilItExpires() :void {
		$verify = new VerifyRequest( 'test_early_check', '/var/www/html/shield-early-loader.php' );
		$token = $verify->issueToken();

		$this->assertTrue( $verify->isValidToken( $token ) );
		$this->assertFalse( $verify->isValidToken( \strrev( $token ) ) );
		$this->assertFalse( $verify->isValidToken( '' ) );

		$this->request->now += 121;
		$this->assertFalse( $verify->isValidToken( $token ) );
	}

	public function testIssuingANewTokenReplacesThePreviousOne() :void {
		$verify = new VerifyRequest( 'test_early_check', '/var/www/html/shield-early-loader.php' );
		$first = $verify->issueToken();
		$second = $verify->issueToken();

		$this->assertFalse( $verify->isValidToken( $first ) );
		$this->assertTrue( $verify->isValidToken( $second ) );
	}

	public function testOnlyThisSitesStubCountsAsLoaded() :void {
		$stub = '/var/www/html/shield-early-loader.php';

		$this->assertTrue( VerifyRequest::isOwnStub( $stub, $stub ) );
		$this->assertTrue( VerifyRequest::isOwnStub( '\\var\\www\\html\\shield-early-loader.php', $stub ) );
		$this->assertFalse( VerifyRequest::isOwnStub( '/var/www/shield-early-loader.php', $stub ), 'A parent folder site\'s stub' );
		$this->assertFalse( VerifyRequest::isOwnStub( true, $stub ), 'An older stub that only defined true' );
		$this->assertFalse( VerifyRequest::isOwnStub( null, $stub ) );
	}

	public function testParseLoadedReadsOnlyAVerificationResponse() :void {
		$this->assertTrue( VerifyRequest::parseLoaded( '{"shield_early":{"loaded":true}}' ) );
		$this->assertFalse( VerifyRequest::parseLoaded( '{"shield_early":{"loaded":false}}' ) );
		$this->assertNull( VerifyRequest::parseLoaded( '{"shield_early":{"loaded":"yes"}}' ) );
		$this->assertNull( VerifyRequest::parseLoaded( '0' ) );
		$this->assertNull( VerifyRequest::parseLoaded( '<html>cached page</html>' ) );
	}
}

class VerifyRequestTestGeneral extends General {

	private array $options = [];

	public function getOption( $sKey, $mDefault = false, $bIgnoreWPMS = false ) {
		return $this->options[ $sKey ] ?? $mDefault;
	}

	public function updateOption( $sKey, $sValue, $ignoreWPMS = false ) {
		$this->options[ $sKey ] = $sValue;
		return true;
	}
}

class VerifyRequestTestRequest extends Request {

	public int $now = 1000;

	public function ts( bool $update = true ) :int {
		return $this->now;
	}
}
