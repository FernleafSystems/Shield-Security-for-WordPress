<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class LeafPageOperatorShellIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
	}

	/** @dataProvider leafPages */
	public function test_distinct_pages_keep_their_content_in_the_shared_shell( string $renderer, string $mode, array $contentPath ) :void {
		$payload = $this->processActionPayloadWithAdminBypass( $renderer::SLUG );
		$this->assertNotSame( '', $this->assertRouteRenderOutputHealthy( $payload, $renderer ) );
		$data = $payload[ 'render_data' ];
		$content = $data;
		foreach ( $contentPath as $key ) {
			$this->assertArrayHasKey( $key, $content );
			$content = $content[ $key ];
		}
		$this->assertArrayHasKey( 'mode_shell', $data[ 'vars' ] ?? [] );
		$shell = $data[ 'vars' ][ 'mode_shell' ];
		$this->assertTrue( $shell[ 'use_operator_chrome' ] );
		$this->assertFalse( $shell[ 'is_interactive' ] );
		$this->assertSame( $mode, $shell[ 'mode' ] );
		$this->assertSame( $data[ 'strings' ][ 'inner_page_title' ], $shell[ 'root_step' ][ 'title' ] );
		$this->assertSame( $data[ 'imgs' ][ 'inner_page_title_icon' ], $shell[ 'root_step' ][ 'icon_class' ] );
		$this->assertSame( $this->requireController()->plugin_urls->adminHome(), $shell[ 'home_href' ] );
		if ( $mode === PluginNavs::NAV_DASHBOARD ) {
			$this->assertSame( '', $shell[ 'parent_href' ] );
		}
		else {
			$entry = PluginNavs::defaultEntryForMode( $mode );
			$this->assertSame(
				$this->requireController()->plugin_urls->adminTopNav( $entry[ 'nav' ], $entry[ 'subnav' ] ),
				$shell[ 'parent_href' ]
			);
		}
	}

	public static function leafPages() :array {
		return [
			'ip rules' => [ PluginAdminPages\PageIpRulesTable::class, PluginNavs::MODE_INVESTIGATE, [ 'vars', 'datatable_iprules' ] ],
			'scan runner' => [ PluginAdminPages\PageScansRun::class, PluginNavs::MODE_ACTIONS, [ 'scans' ] ],
			'rules manager' => [ PluginAdminPages\PageRulesManage::class, PluginNavs::MODE_CONFIGURE, [ 'vars', 'datatables_init' ] ],
			'rules builder' => [ PluginAdminPages\PageRulesBuild::class, PluginNavs::MODE_CONFIGURE, [ 'hrefs', 'rules_builder' ] ],
			'rules summary' => [ PluginAdminPages\PageRulesSummary::class, PluginNavs::MODE_CONFIGURE, [ 'vars', 'rules' ] ],
			'lockdown' => [ PluginAdminPages\PageToolLockdown::class, PluginNavs::MODE_CONFIGURE, [ 'flags', 'blockdown_active' ] ],
			'debug' => [ PluginAdminPages\PageDebug::class, PluginNavs::MODE_CONFIGURE, [ 'vars', 'debug_data' ] ],
			'import export' => [ PluginAdminPages\PageImportExport::class, PluginNavs::MODE_CONFIGURE, [ 'vars', 'network_sync' ] ],
			'license' => [ PluginAdminPages\PageLicense::class, PluginNavs::MODE_CONFIGURE, [ 'vars', 'license_table' ] ],
			'restricted' => [ PluginAdminPages\PageSecurityAdminRestricted::class, PluginNavs::NAV_DASHBOARD, [ 'flags', 'allow_email_override' ] ],
		];
	}
}
