<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

class ScansResultsViewBuilderPaneAvailabilityTest extends ScansResultsViewBuilderTestCase {

	public function test_wordpress_pane_returns_no_items_when_no_core_issues() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'afsDisplayItems'  => [],
		] );

		$wpTab = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'wordpress' );
		$this->assertSame( [], $wpTab[ 'items' ] ?? [] );
		$this->assertSame( 'good', $wpTab[ 'status' ] ?? '' );
		$this->assertSame( 0, $wpTab[ 'count' ] ?? -1 );
	}

	public function test_wordpress_pane_shows_critical_items_for_core_issues() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'afsDisplayItems'  => [
				$this->makeAfsItem( 'is_in_core', [ 'is_checksumfail' => 1, 'path_fragment' => 'wp-admin/admin.php' ] ),
				$this->makeAfsItem( 'is_in_core', [ 'is_missing' => 1, 'path_fragment' => 'wp-includes/class-wp.php' ] ),
			],
		] );

		$wpTab = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'wordpress' );
		$this->assertCount( 2, $wpTab[ 'items' ] ?? [] );
		foreach ( $wpTab[ 'items' ] ?? [] as $item ) {
			$this->assertSame( 'critical', $item[ 'status' ] ?? '' );
		}
		$this->assertSame( 2, $wpTab[ 'count' ] ?? -1 );
	}

	public function test_malware_pane_returns_no_items_when_no_threats() :void {
		$builder = $this->createBuilder( [
			'malwareEnabled'  => true,
			'afsDisplayItems' => [],
		] );

		$malwareTab = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'malware' );
		$this->assertSame( [], $malwareTab[ 'items' ] ?? [] );
		$this->assertSame( 'good', $malwareTab[ 'status' ] ?? '' );
		$this->assertSame( 0, $malwareTab[ 'count' ] ?? -1 );
	}

	public function test_plugin_pane_data_returns_disabled_state_when_scan_is_unavailable() :void {
		$message = 'plugins-unavailable-sentinel';
		$builder = $this->createBuilder( [
			'pluginsEnabled' => false,
			'tabAvailability' => [
				'plugins' => [
					'is_available'          => false,
					'show_in_actions_queue' => true,
					'disabled_message'      => $message,
					'disabled_status'       => 'neutral',
				],
			],
		] );

		$pane = $builder->buildRailPaneData( 'plugins' );
		$this->assertTrue( (bool)( $pane[ 'is_disabled' ] ?? false ) );
		$this->assertSame( 'neutral', $pane[ 'status' ] ?? '' );
		$this->assertSame( 0, $pane[ 'count_items' ] ?? -1 );
		$this->assertSame( [], $pane[ 'items' ] ?? [ 'unexpected' ] );
		$this->assertSame( $message, $pane[ 'disabled_message' ] ?? '' );
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

	public function test_combined_vulnerability_pane_stays_available_when_only_abandoned_results_are_enabled() :void {
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
		$this->assertFalse( (bool)( $pane[ 'is_disabled' ] ?? true ) );
		$this->assertSame( '', $pane[ 'disabled_message' ] ?? 'unexpected' );
		$this->assertSame( 'critical', $pane[ 'status' ] ?? '' );
		$this->assertSame( 1, $pane[ 'count_items' ] ?? -1 );
		$this->assertSame( 'Abandoned Assets', $pane[ 'items' ][ 0 ][ 'section_label' ] ?? '' );
	}

	public function test_malware_pane_data_returns_disabled_state_when_scan_is_unavailable() :void {
		$message = 'malware-unavailable-sentinel';
		$builder = $this->createBuilder( [
			'malwareEnabled' => false,
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
		$this->assertSame( $message, $pane[ 'disabled_message' ] ?? '' );
		$this->assertSame( [], $pane[ 'items' ] ?? [ 'unexpected' ] );
	}

	public function test_plugin_rail_items_pass_through_to_tab() :void {
		$builder = $this->createBuilder( [
			'pluginsEnabled'  => true,
			'pluginRailItems' => [
				$this->makeDetailRow( 'Bad Plugin', 'warning', 5 ),
			],
		] );

		$pluginsTab = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'plugins' );
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

		$themesTab = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'themes' );
		$this->assertCount( 1, $themesTab[ 'items' ] ?? [] );
		$this->assertSame( 'good', $themesTab[ 'status' ] ?? '' );
		$this->assertSame( 0, $themesTab[ 'count' ] ?? -1 );
	}

	public function test_afs_display_items_are_shared_between_wordpress_and_malware() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'malwareEnabled'   => true,
			'afsDisplayItems'  => [
				$this->makeAfsItem( 'is_in_core', [ 'is_checksumfail' => 1, 'path_fragment' => 'wp-admin/x.php' ] ),
				$this->makeAfsItem( 'is_mal', [ 'path_fragment' => 'evil.php' ] ),
			],
		] );

		$railTabs = $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$wpTab = $this->findTabByKey( $railTabs, 'wordpress' );
		$malwareTab = $this->findTabByKey( $railTabs, 'malware' );

		$this->assertCount( 1, $wpTab[ 'items' ] ?? [] );
		$this->assertCount( 1, $malwareTab[ 'items' ] ?? [] );
		$this->assertSame( 'critical', $wpTab[ 'status' ] ?? '' );
		$this->assertSame( 'critical', $malwareTab[ 'status' ] ?? '' );
	}

	public function test_clean_wordpress_pane_returns_empty_items_with_good_status() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'afsDisplayItems'  => [],
		] );

		$pane = $builder->buildRailPaneData( 'wordpress' );
		$this->assertSame( 'good', $pane[ 'status' ] ?? '' );
		$this->assertSame( 0, $pane[ 'count_items' ] ?? -1 );
		$this->assertSame( [], $pane[ 'items' ] ?? null );
	}

	public function test_clean_malware_pane_returns_empty_items_with_good_status() :void {
		$builder = $this->createBuilder( [
			'malwareEnabled' => true,
			'afsDisplayItems' => [],
		] );

		$pane = $builder->buildRailPaneData( 'malware' );
		$this->assertSame( 'good', $pane[ 'status' ] ?? '' );
		$this->assertSame( 0, $pane[ 'count_items' ] ?? -1 );
		$this->assertSame( [], $pane[ 'items' ] ?? null );
	}
}
