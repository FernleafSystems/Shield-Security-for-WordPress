<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueScanResultScopeResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueScanResultScopeResolverTest extends BaseUnitTest {

	public function test_resolves_direct_wordpress_and_malware_scopes() :void {
		$resolver = new ActionsQueueScanResultScopeResolver();

		$this->assertSame(
			[
				'type' => 'wordpress',
				'file' => 'wordpress',
			],
			$resolver->resolveForGroup( 'wordpress' )
		);
		$this->assertSame(
			[
				'type' => 'malware',
				'file' => 'malware',
			],
			$resolver->resolveForGroup( 'malware' )
		);
	}

	public function test_resolves_plugin_and_theme_scopes_from_group_subject() :void {
		$resolver = new ActionsQueueScanResultScopeResolver();

		$this->assertSame(
			[
				'type' => 'plugin',
				'file' => 'example-plugin/example-plugin.php',
			],
			$resolver->resolveForGroup( 'plugins', [
				'subject_type' => 'theme',
				'subject_id'   => 'example-plugin/example-plugin.php',
			] )
		);
		$this->assertSame(
			[
				'type' => 'theme',
				'file' => 'example-theme',
			],
			$resolver->resolveForGroup( 'themes', [
				'subject_type' => 'plugin',
				'subject_id'   => 'example-theme',
			] )
		);
	}

	public function test_returns_empty_scope_for_unknown_or_unscoped_asset_groups() :void {
		$resolver = new ActionsQueueScanResultScopeResolver();

		$this->assertSame( [], $resolver->resolveForGroup( 'vulnerabilities' ) );
		$this->assertSame( [], $resolver->resolveForGroup( 'plugins', [
			'subject_id' => '',
		] ) );
		$this->assertSame( [], $resolver->resolveForGroup( 'themes' ) );
	}
}
