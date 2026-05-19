<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	Render,
	Render\Components\Email\Footer,
	Render\Components\RenderPluginBadge,
	Render\FullPage\Block\BlockPageSiteBlockdown
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\NavMenuBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class WhitelabelContractsIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( $this->whitelabelOptionKeys() );
		$this->resetWhitelabelRuntime();
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
			$this->resetWhitelabelRuntime();
		}
		parent::tear_down();
	}

	public function test_premium_whitelabel_options_relabel_controller_and_render_payload_contracts() :void {
		$this->enablePremiumCapabilities( [ 'whitelabel' ] );
		$this->configureWhitelabelOptions();
		$this->resetWhitelabelRuntime();

		$con = $this->requireController();
		$labels = $con->labels;

		$this->assertTrue( $labels->is_whitelabelled );
		$this->assertSame( 'Agency Guard', $labels->Name );
		$this->assertSame( 'Agency Guard', $labels->Title );
		$this->assertSame( 'Agency', $labels->MenuTitle );
		$this->assertSame( 'Agency Co', $labels->Author );
		$this->assertSame( 'Agency Co', $labels->AuthorName );
		$this->assertSame( 'Agency managed protection', $labels->Description );
		$this->assertSame( 'https://agency.example/security', $labels->PluginURI );
		$this->assertSame( 'https://agency.example/security', $labels->AuthorURI );
		$this->assertSame( 'https://agency.example/security', $labels->url_helpdesk );
		$this->assertSame( 'https://agency.example/logo.png', $labels->icon_url_128x128 );
		$this->assertSame( 'https://agency.example/banner.png', $labels->url_img_pagebanner );
		$this->assertSame( 'https://agency.example/banner.png', $labels->url_img_logo_small );
		$this->assertSame( 'https://agency.example/security', $labels->url_secadmin_forgotten_key );

		$badge = $this->renderDataFor( RenderPluginBadge::class, [ 'is_floating' => false ] );
		$this->assertTrue( (bool)( $badge[ 'flags' ][ 'is_whitelabelled' ] ?? false ) );
		$this->assertSame( 'https://agency.example/security', $badge[ 'hrefs' ][ 'badge' ] ?? '' );
		$this->assertSame( 'https://agency.example/logo.png', $badge[ 'hrefs' ][ 'logo' ] ?? '' );
		$this->assertSame( 'Agency Guard', $badge[ 'strings' ][ 'name' ] ?? '' );

		$footer = $this->renderDataFor( Footer::class );
		$this->assertTrue( (bool)( $footer[ 'flags' ][ 'is_pro' ] ?? false ) );
		$this->assertTrue( (bool)( $footer[ 'flags' ][ 'is_whitelabelled' ] ?? false ) );

		$blockPage = $this->renderDataFor( BlockPageSiteBlockdown::class );
		$this->assertTrue( (bool)( $blockPage[ 'flags' ][ 'is_whitelabelled' ] ?? false ) );
		$this->assertSame( 'https://agency.example/banner.png', $blockPage[ 'imgs' ][ 'logo_banner' ] ?? '' );
		$this->assertSame( 'https://agency.example/banner.png', $blockPage[ 'imgs' ][ 'logo_small' ] ?? '' );

		$sidebar = ( new NavMenuBuilder() )->build();
		$this->assertSame( '', $sidebar[ 'home_connect_title' ] );
		$this->assertSame( [], $sidebar[ 'home_connect_items' ] );
	}

	public function test_premium_without_whitelabel_keeps_pro_suffix_and_default_render_flags() :void {
		$this->enablePremiumCapabilities( [] );
		$this->requireController()->opts
			->optSet( 'whitelabel_enable', 'N' )
			->store();
		$this->resetWhitelabelRuntime();

		$labels = $this->requireController()->labels;

		$this->assertFalse( $labels->is_whitelabelled );
		$this->assertStringEndsWith( ' PRO', $labels->Name );
		$this->assertStringEndsWith( ' PRO', $labels->Title );
		$this->assertStringEndsWith( ' PRO', $labels->MenuTitle );

		$footer = $this->renderDataFor( Footer::class );
		$this->assertTrue( (bool)( $footer[ 'flags' ][ 'is_pro' ] ?? false ) );
		$this->assertFalse( (bool)( $footer[ 'flags' ][ 'is_whitelabelled' ] ?? true ) );
	}

	public function test_non_premium_default_state_does_not_claim_relabelled_labels() :void {
		$this->disablePremiumCapabilities();
		$this->requireController()->opts
			->optSet( 'whitelabel_enable', 'N' )
			->store();
		$this->resetWhitelabelRuntime();

		$labels = $this->requireController()->labels;

		$this->assertFalse( $labels->is_whitelabelled );
		$this->assertStringEndsNotWith( ' PRO', $labels->Name );
	}

	private function configureWhitelabelOptions() :void {
		$this->requireController()->opts
			->optSet( 'whitelabel_enable', 'Y' )
			->optSet( 'wl_pluginnamemain', 'Agency Guard' )
			->optSet( 'wl_namemenu', 'Agency' )
			->optSet( 'wl_companyname', 'Agency Co' )
			->optSet( 'wl_description', 'Agency managed protection' )
			->optSet( 'wl_homeurl', 'https://agency.example/security' )
			->optSet( 'wl_menuiconurl', 'https://agency.example/icon.png' )
			->optSet( 'wl_dashboardlogourl', 'https://agency.example/logo.png' )
			->optSet( 'wl_login2fa_logourl', 'https://agency.example/banner.png' )
			->store();
	}

	/**
	 * @param class-string $renderClass
	 */
	private function renderDataFor( string $renderClass, array $data = [] ) :array {
		$payload = $this->requireController()->action_router->action( Render::class, [
			'render_action_slug' => $renderClass,
			'render_action_data' => $data,
		] )->payload();

		$this->assertFalse( (bool)( $payload[ 'render_error' ] ?? true ) );
		$this->assertIsArray( $payload[ 'render_data' ] ?? null );
		return $payload[ 'render_data' ];
	}

	private function resetWhitelabelRuntime() :void {
		$con = $this->requireController();
		\remove_filter( $con->prefix( 'labels' ), [ $con->comps->whitelabel, 'applyWhiteLabels' ], 200 );
		\remove_filter( $con->prefix( 'is_relabelled' ), '__return_true' );
		\remove_filter( 'plugin_row_meta', [ $con->comps->whitelabel, 'removePluginMetaLinks' ], 200 );
		$con->comps->whitelabel->resetExecution();
		$con->labels = null;
	}

	private function whitelabelOptionKeys() :array {
		return [
			'whitelabel_enable',
			'wl_pluginnamemain',
			'wl_namemenu',
			'wl_companyname',
			'wl_description',
			'wl_homeurl',
			'wl_menuiconurl',
			'wl_dashboardlogourl',
			'wl_login2fa_logourl',
		];
	}
}
