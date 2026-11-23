<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ActionsQueueRefresh;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AjaxRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MfaLoginVerifyStep;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansAttemptRecovery;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	Malware,
	Wordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueAssetFileStatusDetail,
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

	public function testRoutingFieldThatIsNotAStringReadsAsEmpty() :void {
		$transport = [
			'string' => 'ajax_render',
			'list'   => [ 'ajax_render' ],
			'map'    => [ 'a' => [ 'b' ] ],
			'number' => 7,
			'null'   => null,
		];
		$this->assertSame( 'ajax_render', ActionData::TransportField( $transport, 'string' ) );
		foreach ( [ 'list', 'map', 'number', 'null', 'missing' ] as $field ) {
			$this->assertSame( '', ActionData::TransportField( $transport, $field ), $field );
		}
	}

	public function testClassNameThatIsNotAnActionIsUnresolved() :void {
		$this->assertSame( '', ActionsMap::ActionFromSlug( \ArrayObject::class ) );
		$this->assertSame( '', ActionsMap::ActionFromSlug( ActionsMap::class ) );
	}

	public function testActionFromKnownSlugResolvesExpectedClass() :void {
		$this->assertSame( AjaxRender::class, ActionsMap::ActionFromSlug( 'ajax_render' ) );
	}

	public function testActionFromScanRecoverySlugResolvesExpectedClass() :void {
		$this->assertSame( 'scans_attempt_recovery', ScansAttemptRecovery::SLUG );
		$this->assertTrue( ActionData::isValidActionSlug( ScansAttemptRecovery::SLUG ) );
		$this->assertSame( ScansAttemptRecovery::class, ActionsMap::ActionFromSlug( ScansAttemptRecovery::SLUG ) );
	}

	public function testActionFromDrillDownSlugsResolveExpectedClasses() :void {
		$this->assertSame( ActionsQueueAssetFileStatusDetail::class, ActionsMap::ActionFromSlug( ActionsQueueAssetFileStatusDetail::SLUG ) );
		$this->assertSame( Wordpress::class, ActionsMap::ActionFromSlug( Wordpress::SLUG ) );
		$this->assertSame( Malware::class, ActionsMap::ActionFromSlug( Malware::SLUG ) );
		$this->assertSame( ActionsQueueRefresh::class, ActionsMap::ActionFromSlug( ActionsQueueRefresh::SLUG ) );
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
