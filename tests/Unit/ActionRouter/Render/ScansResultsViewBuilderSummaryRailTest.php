<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

class ScansResultsViewBuilderSummaryRailTest extends ScansResultsViewBuilderTestCase {

	public function test_build_prefers_summary_rows_and_hides_empty_asset_and_vulnerability_tabs() :void {
		$builder = $this->createBuilder( [
			'summaryRows'            => [
				[ 'key' => 'wp_files', 'label' => 'WordPress Files', 'count' => 2 ],
			],
			'assessmentRows'         => [
				[ 'key' => 'assessment', 'label' => 'Assessment', 'status' => 'good', 'description' => 'Fine' ],
			],
			'wordpressPayload'       => $this->buildSectionPayload( 'rendered-wordpress', 2 ),
			'pluginsPayload'         => $this->buildSectionPayload( 'rendered-plugins', 0 ),
			'themesPayload'          => $this->buildSectionPayload( 'rendered-themes', 3 ),
			'malwarePayload'         => $this->buildSectionPayload( 'rendered-malware', 1 ),
			'fileLockerPayload'      => $this->buildFileLockerPayload( 'rendered-file-locker', false ),
			'vulnerabilities'        => $this->buildEmptyVulnerabilities(),
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => false,
			'malwareEnabled'         => true,
		] );

		$renderData = $builder->build();
		$tabs = $renderData[ 'vars' ][ 'tabs' ] ?? [];
		$rail = $renderData[ 'vars' ][ 'rail' ] ?? [];
		$railTabs = $renderData[ 'vars' ][ 'rail_tabs' ] ?? [];

		$this->assertSame( [], \array_diff( [ 'summary', 'wordpress', 'themes', 'malware', 'file_locker' ], \array_column( $tabs, 'key' ) ) );
		$this->assertSame( [], \array_diff( [ 'summary', 'wordpress', 'plugins', 'themes', 'malware', 'file_locker' ], \array_column( $railTabs, 'key' ) ) );
		$this->assertSame( [], \array_diff( [ 'summary', 'wordpress', 'plugins', 'themes', 'malware', 'file_locker' ], \array_column( $rail[ 'items' ] ?? [], 'key' ) ) );
		$this->assertTrue( (bool)( $tabs[ 0 ][ 'is_active' ] ?? false ) );
		$this->assertNotEmpty( $renderData[ 'vars' ][ 'assessment_rows' ] );
		$this->assertSame( 'rendered-wordpress', $renderData[ 'content' ][ 'section' ][ 'wordpress' ] ?? '' );
		$this->assertSame( 'rendered-themes', $renderData[ 'content' ][ 'section' ][ 'themes' ] ?? '' );
	}

	public function test_build_uses_assessment_rows_when_summary_is_empty_and_shows_vulnerabilities() :void {
		$builder = $this->createBuilder( [
			'assessmentRows'         => [
				[ 'key' => 'wp_files', 'label' => 'WordPress Files', 'status' => 'good', 'description' => 'OK' ],
			],
			'wordpressPayload'       => $this->buildSectionPayload( 'rendered-wordpress', 9 ),
			'pluginsPayload'         => $this->buildSectionPayload( 'rendered-plugins', 4 ),
			'themesPayload'          => $this->buildSectionPayload( 'rendered-themes', 0 ),
			'malwarePayload'         => $this->buildSectionPayload( 'rendered-malware', 0 ),
			'fileLockerPayload'      => $this->buildFileLockerPayload( 'rendered-file-locker', false ),
			'vulnerabilities'        => [
				'count'    => 2,
				'status'   => 'critical',
				'sections' => [
					'vulnerable' => [
						'label' => 'Known Vulnerabilities',
						'items' => [
							[
								'label'       => 'Vulnerable Plugin',
								'description' => '1 known vulnerability needs review.',
								'severity'    => 'critical',
								'count'       => 1,
								'actions'     => [],
							],
						],
					],
					'abandoned' => [
						'label' => 'Abandoned Assets',
						'items' => [
							[
								'label'       => 'Abandoned Theme',
								'description' => 'Abandoned.',
								'severity'    => 'critical',
								'count'       => 1,
								'actions'     => [],
							],
						],
					],
				],
			],
			'wordpressEnabled'       => false,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => false,
		] );

		$renderData = $builder->build();
		$tabs = $renderData[ 'vars' ][ 'tabs' ] ?? [];
		$railTabs = $renderData[ 'vars' ][ 'rail_tabs' ] ?? [];

		$this->assertSame( [], \array_diff( [ 'summary', 'plugins', 'vulnerabilities', 'malware', 'file_locker' ], \array_column( $tabs, 'key' ) ) );
		$this->assertSame( [], \array_diff( [ 'summary', 'plugins', 'themes', 'vulnerabilities', 'file_locker' ], \array_column( $railTabs, 'key' ) ) );
		$this->assertSame( [], $renderData[ 'vars' ][ 'summary_rows' ] ?? null );
		$this->assertNotEmpty( $renderData[ 'vars' ][ 'assessment_rows' ] );
		$this->assertSame( 2, (int)( $tabs[ 2 ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'critical', $this->findTabByKey( $railTabs, 'vulnerabilities' )[ 'status' ] ?? '' );
		$this->assertSame( 'rendered-file-locker', $renderData[ 'content' ][ 'section' ][ 'filelocker' ] ?? '' );
	}

	public function test_rail_tabs_include_icon_class_for_all_tabs() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => true,
		] );

		foreach ( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [] as $tab ) {
			$this->assertArrayHasKey( 'icon_class', $tab );
			$this->assertNotEmpty( $tab[ 'icon_class' ] );
		}
	}

	public function test_rail_items_include_icon_class_for_all_tabs() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => true,
		] );

		foreach ( $builder->build()[ 'vars' ][ 'rail' ][ 'items' ] ?? [] as $item ) {
			$this->assertArrayHasKey( 'icon_class', $item );
			$this->assertNotEmpty( $item[ 'icon_class' ] );
		}
	}

	public function test_rail_tabs_expose_complete_local_contract_fields() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'pluginsEnabled'   => true,
		] );

		$summaryTab = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' );
		$pluginsTab = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'plugins' );

		foreach ( [ 'items', 'is_loaded', 'is_disabled', 'disabled_message', 'disabled_status', 'render_action', 'show_count_placeholder' ] as $key ) {
			$this->assertArrayHasKey( $key, $summaryTab );
			$this->assertArrayHasKey( $key, $pluginsTab );
		}

		$this->assertSame( [], $summaryTab[ 'render_action' ] );
		$this->assertTrue( $summaryTab[ 'is_loaded' ] );
		$this->assertFalse( $pluginsTab[ 'is_disabled' ] );
	}

	public function test_rail_tab_count_excludes_good_status_items() :void {
		$builder = $this->createBuilder( [
			'pluginsEnabled'  => true,
			'pluginRailItems' => [
				$this->makeDetailRow( 'Bad Plugin', 'warning', 3 ),
				$this->makeDetailRow( 'Clean Plugin', 'good' ),
				$this->makeDetailRow( 'Clean Plugin 2', 'good' ),
			],
		] );

		$this->assertSame( 1, $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'plugins' )[ 'count' ] ?? -1 );
	}

	public function test_summary_tab_status_reflects_highest_child_severity() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'malwareEnabled'   => true,
			'afsDisplayItems'  => [
				$this->makeAfsItem( 'is_in_core' ),
			],
		] );

		$this->assertSame( 'critical', $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' )[ 'status' ] ?? '' );
	}

	public function test_summary_tab_status_is_good_when_all_children_are_good() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled' => true,
			'malwareEnabled'   => true,
		] );

		$this->assertSame( 'good', $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' )[ 'status' ] ?? '' );
	}

	public function test_summary_tab_count_uses_sum_of_row_counts_not_number_of_rows() :void {
		$builder = $this->createBuilder( [
			'summaryRows'      => [
				[ 'key' => 'wp_files', 'label' => 'WordPress Files', 'text' => 'Issues found', 'severity' => 'critical', 'count' => 4 ],
				[ 'key' => 'plugin_files', 'label' => 'Plugin Files', 'text' => 'Issues found', 'severity' => 'warning', 'count' => 2 ],
			],
			'wordpressEnabled' => true,
			'pluginsEnabled'   => true,
		] );

		$renderData = $builder->build();
		$this->assertSame( 6, $this->findTabByKey( $renderData[ 'vars' ][ 'tabs' ] ?? [], 'summary' )[ 'count' ] ?? -1 );
		$this->assertSame( 6, $this->findTabByKey( $renderData[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' )[ 'count' ] ?? -1 );
	}

	public function test_summary_items_show_attention_and_all_clear_sections_when_issues_exist() :void {
		$builder = $this->createBuilder( [
			'summaryRows'    => [
				[ 'label' => 'WordPress Files', 'text' => 'Issues found', 'severity' => 'critical', 'count' => 3 ],
			],
			'assessmentRows' => [
				[ 'label' => 'Plugin Files', 'description' => 'All clear', 'status' => 'good', 'status_icon_class' => '', 'status_label' => 'Good' ],
				[ 'label' => 'Malware', 'description' => 'Problem', 'status' => 'warning', 'status_icon_class' => '', 'status_label' => 'Warning' ],
			],
		] );

		$items = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' )[ 'items' ] ?? [];
		$this->assertCount( 2, $items );
		$this->assertSame( 'critical', $items[ 0 ][ 'status' ] ?? '' );
		$this->assertSame( 'good', $items[ 1 ][ 'status' ] ?? '' );
		$this->assertNotSame( '', $items[ 0 ][ 'section_label' ] ?? '' );
		$this->assertNotSame( '', $items[ 1 ][ 'section_label' ] ?? '' );
		$this->assertNotSame( $items[ 0 ][ 'section_label' ] ?? '', $items[ 1 ][ 'section_label' ] ?? '' );
	}

	public function test_detail_rows_expose_complete_local_contract_fields() :void {
		$builder = $this->createBuilder( [
			'summaryRows' => [
				[ 'label' => 'WordPress Files', 'text' => 'Issues found', 'severity' => 'critical', 'count' => 3 ],
			],
		] );

		$row = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' )[ 'items' ][ 0 ] ?? [];

		foreach ( [ 'expand_target', 'expansion', 'section_label', 'actions', 'attributes' ] as $key ) {
			$this->assertArrayHasKey( $key, $row );
		}

		$this->assertSame( '', $row[ 'expand_target' ] );
		$this->assertSame( [], $row[ 'expansion' ] );
		$this->assertNotSame( '', $row[ 'section_label' ] );
	}

	public function test_summary_items_show_only_assessments_when_no_issues() :void {
		$builder = $this->createBuilder( [
			'summaryRows'    => [],
			'assessmentRows' => [
				[ 'label' => 'WordPress Core', 'description' => 'All clear', 'status' => 'good', 'status_icon_class' => '', 'status_label' => 'Good' ],
				[ 'label' => 'Malware', 'description' => 'All clear', 'status' => 'good', 'status_icon_class' => '', 'status_label' => 'Good' ],
			],
		] );

		$items = $this->findTabByKey( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' )[ 'items' ] ?? [];
		$this->assertCount( 2, $items );
		$this->assertSame( [ '', '' ], \array_column( $items, 'section_label' ) );
	}

	public function test_rail_tabs_include_expected_keys_when_all_tabs_are_available() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => true,
		] );

		$this->assertSame(
			[],
			\array_diff(
				[ 'summary', 'wordpress', 'plugins', 'themes', 'vulnerabilities', 'malware', 'file_locker' ],
				\array_column( $builder->build()[ 'vars' ][ 'rail_tabs' ] ?? [], 'key' )
			)
		);
	}

	public function test_legacy_tabs_include_the_same_available_keys_as_rail_tabs() :void {
		$builder = $this->createBuilder( [
			'wordpressEnabled'       => true,
			'pluginsEnabled'         => true,
			'themesEnabled'          => true,
			'vulnerabilitiesEnabled' => true,
			'malwareEnabled'         => true,
			'wordpressPayload'       => $this->buildSectionPayload( 'rendered-wordpress', 1 ),
			'pluginsPayload'         => $this->buildSectionPayload( 'rendered-plugins', 1 ),
			'themesPayload'          => $this->buildSectionPayload( 'rendered-themes', 1 ),
			'malwarePayload'         => $this->buildSectionPayload( 'rendered-malware', 1 ),
			'fileLockerPayload'      => $this->buildFileLockerPayload( 'rendered-file-locker', true, 1 ),
			'vulnerabilities'        => [
				'count'    => 1,
				'status'   => 'warning',
				'sections' => [],
			],
		] );

		$renderData = $builder->build();
		$this->assertEqualsCanonicalizing(
			\array_column( $renderData[ 'vars' ][ 'rail_tabs' ] ?? [], 'key' ),
			\array_column( $renderData[ 'vars' ][ 'tabs' ] ?? [], 'key' )
		);
	}

	public function test_first_rail_tab_is_always_active() :void {
		$railTabs = $this->createBuilder()->build()[ 'vars' ][ 'rail_tabs' ] ?? [];
		$this->assertTrue( (bool)( $railTabs[ 0 ][ 'is_active' ] ?? false ) );
		$this->assertSame(
			0,
			\count( \array_filter( \array_slice( $railTabs, 1 ), static fn( array $t ) :bool => !empty( $t[ 'is_active' ] ) ) )
		);
	}
}
