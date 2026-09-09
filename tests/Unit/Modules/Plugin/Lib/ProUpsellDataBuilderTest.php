<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Urls;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ProUpsellDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class ProUpsellDataBuilderTest extends BaseUnitTest {

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_reuses_asset_url_resolver_and_canonical_upgrade_destinations() :void {
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$urls = $this->createMock( Urls::class );
		$urls->expects( $this->once() )
			 ->method( 'forImage' )
			 ->with( 'plugin_logo_prem_dark.svg' )
			 ->willReturn( '/resolved-assets/pro-logo.svg' );
		UnitTestControllerFactory::install( null, null, (object)[ 'urls' => $urls ] );

		$upsell = ( new ProUpsellDataBuilder() )->build();

		$this->assertSame( '/resolved-assets/pro-logo.svg', $upsell[ 'logo_url' ] );
		$this->assertSame( BaseRender::GO_PRO_URL, $upsell[ 'hrefs' ][ 'go_pro' ] );
		$this->assertSame( BaseRender::COMPARE_FEATURES_URL, $upsell[ 'hrefs' ][ 'compare_features' ] );
	}
}
