<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};

class BaseRenderContractTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private BaseRenderContractRenderer $renderer;

	protected function setUp() :void {
		parent::setUp();
		$this->renderer = new BaseRenderContractRenderer();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_render_failure_exposes_machine_error_contract_and_preserves_html_alias() :void {
		$this->renderer->throwOnRender = true;
		$action = new BaseRenderContractAction();

		$this->invokeNonPublicMethod( $action, 'render' );
		$payload = $action->response()->payload();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertSame( BaseRenderContractAction::TEMPLATE, (string)( $payload[ 'render_template' ] ?? '' ) );
		$this->assertSame( [ 'value' ], $payload[ 'render_data' ][ 'vars' ][ 'contract' ] ?? [] );
		$this->assertTrue( (bool)( $payload[ 'render_error' ] ?? false ) );
		$this->assertSame( BaseRender::RENDER_ERROR_EXCEPTION, (string)( $payload[ 'render_error_code' ] ?? '' ) );
		$this->assertSame( $payload[ 'render_output' ] ?? null, $payload[ 'html' ] ?? null );
	}

	public function test_successful_render_clears_previous_error_state() :void {
		$action = new BaseRenderContractAction();

		$this->renderer->throwOnRender = true;
		$this->invokeNonPublicMethod( $action, 'render' );
		$this->assertTrue( (bool)( $action->response()->payload()[ 'render_error' ] ?? false ) );

		$this->renderer->throwOnRender = false;
		$this->invokeNonPublicMethod( $action, 'render' );
		$payload = $action->response()->payload();

		$this->assertFalse( (bool)( $payload[ 'render_error' ] ?? true ) );
		$this->assertSame( '', (string)( $payload[ 'render_error_code' ] ?? 'unexpected' ) );
		$this->assertSame( BaseRenderContractRenderer::OUTPUT, (string)( $payload[ 'render_output' ] ?? '' ) );
		$this->assertSame( BaseRenderContractRenderer::OUTPUT, (string)( $payload[ 'html' ] ?? '' ) );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = (object)[
			'render' => $this->renderer,
		];

		PluginControllerInstaller::install( $controller );
	}
}

class BaseRenderContractAction extends BaseRender {

	public const SLUG = 'unit_base_render_contract';
	public const TEMPLATE = '/unit/base-render-contract.twig';

	protected function buildRenderData() :array {
		return [
			'vars' => [
				'contract' => [ 'value' ],
			],
		];
	}
}

class BaseRenderContractRenderer {

	public const OUTPUT = 'unit-render-output';

	public bool $throwOnRender = false;

	public function setTemplate( string $template ) :self {
		unset( $template );
		return $this;
	}

	public function setData( array $data ) :self {
		unset( $data );
		return $this;
	}

	public function setEnvironmentVars( array $vars ) :self {
		unset( $vars );
		return $this;
	}

	public function render() :string {
		if ( $this->throwOnRender ) {
			throw new \RuntimeException( 'unit renderer failure' );
		}
		return self::OUTPUT;
	}
}
