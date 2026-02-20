<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AjaxRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ActionsMap;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionRoutingDeterminismTest extends BaseUnitTest {

	public function testBuildAjaxRenderRequiresActionClassParameter() :void {
		$method = new \ReflectionMethod( ActionData::class, 'BuildAjaxRender' );
		$this->assertSame( 1, $method->getNumberOfRequiredParameters() );
	}

	public function testBuildAjaxRenderWithoutActionClassThrows() :void {
		$this->expectException( \ArgumentCountError::class );
		ActionData::BuildAjaxRender();
	}

	public function testActionFromEmptySlugReturnsUnresolved() :void {
		$this->assertSame( '', ActionsMap::ActionFromSlug( '' ) );
	}

	public function testActionFromKnownSlugResolvesExpectedClass() :void {
		$this->assertSame( AjaxRender::class, ActionsMap::ActionFromSlug( 'ajax_render' ) );
	}
}
