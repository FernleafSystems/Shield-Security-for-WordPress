<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers\Helpers\NinjaForms_ShieldSpamAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers\NinjaForms;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class NinjaFormsShieldSpamActionRegressionTest extends ShieldIntegrationTestCase {

	public function testConstructorAndSetHandlerDoNotFatal() :void {
		$this->ensureNinjaFormsActionBaseStub();

		$handler = $this->buildNinjaFormsHandlerMock( false );
		$action = new NinjaForms_ShieldSpamAction();

		$this->assertInstanceOf( NinjaForms_ShieldSpamAction::class, $action );
		$this->assertSame( $action, $action->setHandler( $handler ) );
	}

	public function testProcessAddsSpamErrorWhenBotBlockRequired() :void {
		$this->ensureNinjaFormsActionBaseStub();

		$action = ( new NinjaForms_ShieldSpamAction() )->setHandler(
			$this->buildNinjaFormsHandlerMock( true )
		);

		$data = [
			'errors' => [],
		];
		$processed = $action->process( [], 10, $data );

		$this->assertArrayHasKey( 'errors', $processed );
		$this->assertArrayHasKey( 'form', $processed[ 'errors' ] );
		$this->assertArrayHasKey( 'spam', $processed[ 'errors' ][ 'form' ] );
		$this->assertIsString( $processed[ 'errors' ][ 'form' ][ 'spam' ] );
		$this->assertNotSame( '', \trim( $processed[ 'errors' ][ 'form' ][ 'spam' ] ) );
	}

	public function testProcessLeavesPayloadUntouchedWhenBotBlockNotRequired() :void {
		$this->ensureNinjaFormsActionBaseStub();

		$data = [
			'field'  => 'value',
			'errors' => [
				'form' => [
					'existing' => 'keep',
				]
			]
		];

		$action = ( new NinjaForms_ShieldSpamAction() )->setHandler(
			$this->buildNinjaFormsHandlerMock( false )
		);

		$this->assertSame( $data, $action->process( [], 11, $data ) );
	}

	private function buildNinjaFormsHandlerMock( bool $isBotBlockRequired ) :NinjaForms {
		$handler = $this->getMockBuilder( NinjaForms::class )
						->disableOriginalConstructor()
						->onlyMethods( [ 'isBotBlockRequired' ] )
						->getMock();

		$handler->method( 'isBotBlockRequired' )->willReturn( $isBotBlockRequired );
		return $handler;
	}

	private function ensureNinjaFormsActionBaseStub() :void {
		if ( !\class_exists( 'NF_Abstracts_Action', false ) ) {
			eval( <<<'PHP'
class NF_Abstracts_Action {
	protected $_nicename = '';
	public function __construct() {}
}
PHP
			);
		}
	}
}
