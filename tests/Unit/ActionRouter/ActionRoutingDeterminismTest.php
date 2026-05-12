<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AjaxRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaLoginVerifyStep;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	Malware,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueAssetFileStatusDetail,
	ActionsQueueDrillDownGroups,
	ConfigureDrillDownDiagnosis
};
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

	public function testActionFromDrillDownSlugsResolveExpectedClasses() :void {
		$this->assertSame( ActionsQueueAssetFileStatusDetail::class, ActionsMap::ActionFromSlug( ActionsQueueAssetFileStatusDetail::SLUG ) );
		$this->assertSame( Wordpress::class, ActionsMap::ActionFromSlug( Wordpress::SLUG ) );
		$this->assertSame( Malware::class, ActionsMap::ActionFromSlug( Malware::SLUG ) );
		$this->assertSame( ActionsQueueDrillDownGroups::class, ActionsMap::ActionFromSlug( ActionsQueueDrillDownGroups::SLUG ) );
		$this->assertSame( ConfigureDrillDownDiagnosis::class, ActionsMap::ActionFromSlug( ConfigureDrillDownDiagnosis::SLUG ) );
	}

	public function testActionSlugPatternAcceptsLiveSlugWithDigits() :void {
		$this->assertTrue( ActionData::isValidActionSlug( MfaLoginVerifyStep::SLUG ) );
		$this->assertSame( MfaLoginVerifyStep::SLUG, ActionData::extractActionSlug( MfaLoginVerifyStep::SLUG ) );
	}

	public function testActionSlugPatternRejectsMalformedSlug() :void {
		$this->assertFalse( ActionData::isValidActionSlug( 'bad slug!' ) );
		$this->assertSame( '', ActionData::extractActionSlug( 'bad slug!' ) );
	}
}
