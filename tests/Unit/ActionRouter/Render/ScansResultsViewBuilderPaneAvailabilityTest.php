<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

class ScansResultsViewBuilderPaneAvailabilityTest extends ScansResultsViewBuilderTestCase {

	public function test_plugin_pane_data_returns_disabled_state_when_scan_is_unavailable() :void {
		$message = 'plugins-unavailable-sentinel';
		$builder = $this->createBuilder( [
			'pluginsEnabled' => false,
			'tabAvailability' => [
				'plugins' => [
					'is_available'          => false,
					'show_in_actions_queue' => true,
					'show_in_fix_now'       => true,
					'disabled_reason'       => 'not_enabled',
					'disabled_message'      => $message,
					'disabled_status'       => 'neutral',
					'disabled_actions'      => [
						[
							'type'         => 'navigate',
							'label'      => 'Turn On Scanning',
							'href'       => '',
							'is_action'  => true,
							'class_name' => 'zone_component_action',
							'target'     => '',
							'rel'        => '',
							'attributes' => [
								'data-zone_component_action' => 'offcanvas_zone_component_config',
							],
						],
					],
				],
			],
		] );

		$pane = $builder->buildRailPaneData( 'plugins' );
		$this->assertTrue( (bool)( $pane[ 'is_disabled' ] ?? false ) );
		$this->assertSame( 'neutral', $pane[ 'status' ] ?? '' );
		$this->assertSame( 0, $pane[ 'count_items' ] ?? -1 );
		$this->assertSame( [], $pane[ 'items' ] ?? [ 'unexpected' ] );
		$this->assertSame( $message, $pane[ 'disabled_message' ] ?? '' );
		$this->assertCount( 1, $pane[ 'disabled_actions' ] ?? [] );
		$this->assertSame( 'navigate', $pane[ 'disabled_actions' ][ 0 ][ 'type' ] ?? '' );
		$this->assertSame( '', $pane[ 'disabled_actions' ][ 0 ][ 'href' ] ?? 'unexpected' );
		$this->assertTrue( $pane[ 'disabled_actions' ][ 0 ][ 'is_action' ] ?? false );
		$this->assertSame( '', $pane[ 'disabled_actions' ][ 0 ][ 'target' ] ?? 'unexpected' );
		$this->assertSame( '', $pane[ 'disabled_actions' ][ 0 ][ 'rel' ] ?? 'unexpected' );
		$this->assertSame( 'zone_component_action', $pane[ 'disabled_actions' ][ 0 ][ 'class_name' ] ?? '' );
	}

	public function test_theme_pane_data_returns_disabled_state_when_scan_is_unavailable() :void {
		$message = 'themes-unavailable-sentinel';
		$builder = $this->createBuilder( [
			'themesEnabled' => false,
			'tabAvailability' => [
				'themes' => [
					'is_available'          => false,
					'show_in_actions_queue' => true,
					'disabled_message'      => $message,
					'disabled_status'       => 'neutral',
				],
			],
		] );

		$pane = $builder->buildRailPaneData( 'themes' );
		$this->assertTrue( (bool)( $pane[ 'is_disabled' ] ?? false ) );
		$this->assertSame( $message, $pane[ 'disabled_message' ] ?? '' );
		$this->assertSame( [], $pane[ 'items' ] ?? [ 'unexpected' ] );
	}

	public function test_vulnerability_pane_data_returns_disabled_state_when_scans_are_unavailable() :void {
		$message = 'vulnerabilities-unavailable-sentinel';
		$builder = $this->createBuilder( [
			'vulnerabilitiesEnabled' => false,
			'tabAvailability' => [
				'vulnerabilities' => [
					'is_available'          => false,
					'show_in_actions_queue' => true,
					'disabled_message'      => $message,
					'disabled_status'       => 'neutral',
				],
			],
		] );

		$pane = $builder->buildRailPaneData( 'vulnerabilities' );
		$this->assertTrue( (bool)( $pane[ 'is_disabled' ] ?? false ) );
		$this->assertSame( 'neutral', $pane[ 'status' ] ?? '' );
		$this->assertSame( $message, $pane[ 'disabled_message' ] ?? '' );
		$this->assertSame( [], $pane[ 'items' ] ?? [ 'unexpected' ] );
	}

	public function test_malware_pane_data_returns_disabled_state_when_scan_is_unavailable() :void {
		$message = 'malware-unavailable-sentinel';
		$builder = $this->createBuilder( [
			'tabAvailability' => [
				'malware' => [
					'is_available'          => false,
					'show_in_actions_queue' => true,
					'disabled_message'      => $message,
					'disabled_status'       => 'neutral',
				],
			],
		] );

		$pane = $builder->buildRailPaneData( 'malware' );
		$this->assertTrue( (bool)( $pane[ 'is_disabled' ] ?? false ) );
		$this->assertSame( 'neutral', $pane[ 'status' ] ?? '' );
		$this->assertSame( 0, $pane[ 'count_items' ] ?? -1 );
		$this->assertSame( [], $pane[ 'items' ] ?? [ 'unexpected' ] );
		$this->assertSame( $message, $pane[ 'disabled_message' ] ?? '' );
		$this->assertSame( [], $pane[ 'disabled_actions' ] ?? [ 'unexpected' ] );
	}

	public function test_vulnerability_pane_does_not_use_abandoned_state_to_bypass_unavailable_wpv() :void {
		$builder = $this->createBuilder( [
			'vulnerabilitiesEnabled' => true,
			'vulnerabilities'        => [
				'count'    => 1,
				'status'   => 'critical',
				'sections' => [
					'abandoned' => [
						'label'  => 'Abandoned Assets',
						'count'  => 1,
						'status' => 'critical',
						'items'  => [
							[
								'label'       => 'Abandoned Theme',
								'description' => 'This asset appears to be abandoned and should be reviewed.',
								'severity'    => 'critical',
								'count'       => 1,
								'actions'     => [],
							],
						],
					],
				],
			],
			'tabAvailability'        => [
				'vulnerabilities' => [
					'is_available'          => false,
					'show_in_actions_queue' => true,
					'disabled_message'      => 'vulnerabilities-unavailable-sentinel',
					'disabled_status'       => 'neutral',
				],
			],
		] );

		$pane = $builder->buildRailPaneData( 'vulnerabilities' );
		$this->assertTrue( (bool)( $pane[ 'is_disabled' ] ?? false ) );
		$this->assertSame( 'vulnerabilities-unavailable-sentinel', $pane[ 'disabled_message' ] ?? '' );
		$this->assertSame( 'neutral', $pane[ 'status' ] ?? '' );
		$this->assertSame( 0, $pane[ 'count_items' ] ?? -1 );
		$this->assertSame( [], $pane[ 'items' ] ?? [ 'unexpected' ] );
	}

	public function test_plugin_rail_items_pass_through_to_tab() :void {
		$builder = $this->createBuilder( [
			'pluginsEnabled'  => true,
			'pluginRailItems' => [
				$this->makeDetailRow( 'Bad Plugin', 'warning', 5 ),
			],
		] );

		$pluginsTab = $builder->buildRailPaneData( 'plugins' );
		$this->assertCount( 1, $pluginsTab[ 'items' ] ?? [] );
		$this->assertSame( 'warning', $pluginsTab[ 'status' ] ?? '' );
	}

	public function test_theme_rail_items_pass_through_to_tab() :void {
		$builder = $this->createBuilder( [
			'themesEnabled'  => true,
			'themeRailItems' => [
				$this->makeDetailRow( 'Good Theme', 'good' ),
			],
		] );

		$themesTab = $builder->buildRailPaneData( 'themes' );
		$this->assertCount( 1, $themesTab[ 'items' ] ?? [] );
		$this->assertSame( 'good', $themesTab[ 'status' ] ?? '' );
		$this->assertSame( 0, $themesTab[ 'count_items' ] ?? -1 );
	}
}
