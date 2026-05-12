<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\{
	BlockIpAddressCrowdsec,
	BlockIpAddressShield,
	BlockRecoveryRenderContracts,
	BlockTrafficRateLimitExceeded
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestRequest,
	UnitTestUsers
};

class BlockRecoveryRenderContractsTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static function ( $text ) :string {
				if ( !\is_string( $text ) ) {
					return '';
				}
				$sanitized = \preg_replace( '/[^a-z0-9_-]/', '', \strtolower( \trim( $text ) ) );
				return \is_string( $sanitized ) ? $sanitized : '';
			}
		);
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = 'auth' ) :string => 'hash-'.$scheme.'-'.$data
		);
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map(
						static fn( $item ) :string => \rawurlencode( (string)$item ),
						$value
					);
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				$query = \http_build_query( $params );
				return $query === '' ? $url : $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).$query;
			}
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest(),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers(),
		] );
		UnitTestControllerFactory::install( null, null, (object)[
			'labels'   => (object)[ 'Name' => 'Shield' ],
			'this_req' => (object)[ 'ip' => '203.0.113.44' ],
		] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	/**
	 * @dataProvider provideStableActionContracts
	 */
	public function test_action_contract_builds_exact_stable_ids(
		string $pageKey,
		string $actionKey,
		string $expectedPage,
		string $expectedAction,
		string $expectedBase
	) :void {
		$contract = ( new BlockRecoveryRenderContractsTestDouble() )
			->actionContract( $pageKey, $actionKey, '<p>ready</p>' );
		$expectedIds = [
			'launcher' => $expectedBase.'-launcher',
			'dialog'   => $expectedBase.'-dialog',
			'title'    => $expectedBase.'-title',
			'body'     => $expectedBase.'-body',
			'status'   => $expectedBase.'-status',
			'confirm'  => $expectedBase.'-confirm',
			'submit'   => $expectedBase.'-submit',
			'helper'   => $expectedBase.'-helper',
		];

		$this->assertSame( $expectedPage, $contract[ 'page' ] );
		$this->assertSame( $expectedAction, $contract[ 'action' ] );
		$this->assertSame( $expectedIds, $contract[ 'ids' ] );
		$this->assertSame( '#'.$expectedIds[ 'dialog' ], $contract[ 'launcher' ][ 'target_selector' ] );
		$this->assertArrayNotHasKey( 'target', $contract[ 'launcher' ] );
		$this->assertSame( $expectedIds[ 'title' ], $contract[ 'modal' ][ 'title_id' ] );
	}

	/**
	 * @return array<string,array{0:string,1:string,2:string,3:string,4:string}>
	 */
	public function provideStableActionContracts() :array {
		return [
			'ip shield email unblock'       => [
				'IP_Shield',
				'email_unblock',
				'ip-shield',
				'email-unblock',
				'shield-block-ip-shield-email-unblock',
			],
			'ip shield auto recover'        => [
				'ip-shield',
				'auto-recover',
				'ip-shield',
				'auto-recover',
				'shield-block-ip-shield-auto-recover',
			],
			'crowdsec auto recover'         => [
				'ip-crowdsec',
				'auto-recover',
				'ip-crowdsec',
				'auto-recover',
				'shield-block-ip-crowdsec-auto-recover',
			],
			'traffic rate limit auto recover' => [
				'traffic-rate-limit',
				'auto-recover',
				'traffic-rate-limit',
				'auto-recover',
				'shield-block-traffic-rate-limit-auto-recover',
			],
		];
	}

	public function test_recovery_contract_prefers_first_available_candidate() :void {
		$builder = new BlockRecoveryRenderContractsTestDouble();
		$autoRecovery = $builder->actionContract( 'ip-shield', 'auto-recover' );
		$contract = $builder->pageContract( 'ip-shield', [
			$builder->candidate(
				$builder->actionContract( 'ip-shield', 'email-unblock' ),
				' '
			),
			$builder->candidate(
				$autoRecovery,
				'<form>ready</form>'
			),
		] );

		$this->assertTrue( $contract[ 'is_available' ] );
		$this->assertSame( 'auto-recover', $contract[ 'action' ] );
		$this->assertSame( '<form>ready</form>', $contract[ 'content' ] );
		$this->assertSame( $autoRecovery[ 'ids' ], $contract[ 'ids' ] );
	}

	public function test_unavailable_recovery_contract_has_no_content() :void {
		$builder = new BlockRecoveryRenderContractsTestDouble();
		$contract = $builder->pageContract( 'ip-shield', [
			$builder->candidate(
				$builder->actionContract( 'ip-shield', 'email-unblock' ),
				''
			),
		] );

		$this->assertFalse( $contract[ 'is_available' ] );
		$this->assertSame( 'none', $contract[ 'action' ] );
		$this->assertSame( '', $contract[ 'content' ] );
	}

	public function test_ip_shield_render_data_prefers_email_recovery() :void {
		$page = new BlockIpAddressShieldRenderDataTestDouble( '<div>email</div>', '<form>auto</form>' );
		$renderData = $page->renderDataForTest();
		$recovery = $renderData[ 'vars' ][ 'recovery' ];

		$this->assertSame( 'ip-shield', $recovery[ 'page' ] );
		$this->assertSame( 'email-unblock', $recovery[ 'action' ] );
		$this->assertSame( '<div>email</div>', $recovery[ 'content' ] );
		$this->assertNotSame( [], $renderData[ 'vars' ][ 'inline_js' ] );
	}

	public function test_ip_shield_auto_recovery_omits_magic_link_bootstrap() :void {
		$page = new BlockIpAddressShieldRenderDataTestDouble( '', '<form>auto</form>' );
		$renderData = $page->renderDataForTest();
		$recovery = $renderData[ 'vars' ][ 'recovery' ];

		$this->assertSame( 'ip-shield', $recovery[ 'page' ] );
		$this->assertSame( 'auto-recover', $recovery[ 'action' ] );
		$this->assertSame( [], $renderData[ 'vars' ][ 'inline_js' ] );
	}

	public function test_crowdsec_render_data_uses_auto_recovery_only() :void {
		$page = new BlockIpAddressCrowdsecRenderDataTestDouble( '<form>auto</form>' );
		$renderData = $page->renderDataForTest();
		$recovery = $renderData[ 'vars' ][ 'recovery' ];

		$this->assertSame( 'ip-crowdsec', $recovery[ 'page' ] );
		$this->assertSame( 'auto-recover', $recovery[ 'action' ] );
		$this->assertSame( '<form>auto</form>', $recovery[ 'content' ] );
		$this->assertSame( [], $renderData[ 'vars' ][ 'inline_js' ] );
	}

	public function test_traffic_rate_limit_render_data_uses_auto_recovery_when_available() :void {
		$page = new BlockTrafficRateLimitExceededRenderDataTestDouble( '<form>auto</form>' );
		$recovery = $page->renderDataForTest()[ 'vars' ][ 'recovery' ];

		$this->assertSame( 'traffic-rate-limit', $recovery[ 'page' ] );
		$this->assertSame( 'auto-recover', $recovery[ 'action' ] );
		$this->assertSame( '<form>auto</form>', $recovery[ 'content' ] );
	}
}

class BlockRecoveryRenderContractsTestDouble {

	use BlockRecoveryRenderContracts;

	public function actionContract( string $pageKey, string $actionKey, string $content = '' ) :array {
		return $this->buildBlockRecoveryActionContract( $pageKey, $actionKey, $content );
	}

	public function pageContract( string $pageKey, array $candidates ) :array {
		return $this->buildBlockRecoveryContract( $pageKey, $candidates );
	}

	public function candidate( array $recovery, string $content ) :array {
		return $this->buildBlockRecoveryCandidate( $recovery, $content );
	}
}

class BlockIpAddressShieldRenderDataTestDouble extends BlockIpAddressShield {

	private string $emailContent;

	private string $autoContent;

	public function __construct( string $emailContent, string $autoContent ) {
		parent::__construct();
		$this->emailContent = $emailContent;
		$this->autoContent = $autoContent;
	}

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}

	protected function renderEmailMagicLinkContent( array $recovery ) :string {
		return $this->emailContent;
	}

	protected function renderAutoUnblock( array $recovery ) :string {
		return $this->autoContent;
	}
}

class BlockIpAddressCrowdsecRenderDataTestDouble extends BlockIpAddressCrowdsec {

	private string $autoContent;

	public function __construct( string $autoContent ) {
		parent::__construct();
		$this->autoContent = $autoContent;
	}

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}

	protected function renderAutoUnblock( array $recovery ) :string {
		return $this->autoContent;
	}
}

class BlockTrafficRateLimitExceededRenderDataTestDouble extends BlockTrafficRateLimitExceeded {

	private string $autoContent;

	public function __construct( string $autoContent ) {
		parent::__construct();
		$this->autoContent = $autoContent;
	}

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}

	protected function renderAutoUnblock( array $recovery ) :string {
		return $this->autoContent;
	}
}
