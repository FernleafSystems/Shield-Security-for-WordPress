<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	ScansBase,
	Render\Components\Scans\ScansProgress
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ScansProgressRenderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->returnArg( 1 );
	}

	public function test_render_data_exposes_per_scan_rows_for_twig() :void {
		$renderData = ( new ScansProgressRenderTestDouble( [
			'modal_state'     => ScansBase::SCAN_MODAL_STATE_RUNNING,
			'current_scan'    => 'File Guard',
			'remaining_scans' => '2 scans remaining.',
			'progress'        => 50,
			'scan_rows'       => [
				$this->progressRow( [
					'id'                   => 11,
					'scan'                 => 'afs',
					'name'                 => 'File Guard',
					'raw_status'           => 'running',
					'display_status'       => 'running',
					'progress'             => 100,
					'total_items'          => 4,
					'unfinished'           => 0,
				] ),
				$this->progressRow( [
					'id'                   => 12,
					'scan'                 => 'wpv',
					'name'                 => 'Vulnerability Scan',
					'scope_type'           => 'plugin',
					'scope_key'            => 'shield-security',
					'raw_status'           => 'built',
					'display_status'       => 'waiting',
					'is_current'           => false,
					'progress'             => 40,
					'total_items'          => 5,
					'unfinished'           => 3,
				] ),
			],
		] ) )->renderDataForTest();

		$vars = $renderData[ 'vars' ];
		$rows = $vars[ 'scan_rows' ];

		$this->assertCount( 2, $rows );
		$this->assertRenderRowShape( $rows[ 0 ] );
		$this->assertRenderRowShape( $rows[ 1 ] );
		$this->assertSame( 'File Guard', $rows[ 0 ][ 'name' ] );
		$this->assertSame( '', $rows[ 0 ][ 'scope_label' ] );
		$this->assertFalse( $rows[ 0 ][ 'can_attempt_recovery' ] );
		$this->assertNotSame( '', $rows[ 0 ][ 'status_label' ] );
		$this->assertStatusPresentationContract( $rows[ 0 ] );
		$this->assertStringContainsString( 'progress-bar-animated', $rows[ 0 ][ 'progress_bar_class' ] );
		$this->assertSame( 100, $rows[ 0 ][ 'progress' ] );
		$this->assertNotSame( '', $rows[ 0 ][ 'aria_label' ] );
		$this->assertSame( 'Vulnerability Scan', $rows[ 1 ][ 'name' ] );
		$this->assertStringContainsString( 'shield-security', $rows[ 1 ][ 'scope_label' ] );
		$this->assertFalse( $rows[ 1 ][ 'can_attempt_recovery' ] );
		$this->assertNotSame( '', $rows[ 1 ][ 'status_label' ] );
		$this->assertStatusPresentationContract( $rows[ 1 ] );
		$this->assertStringNotContainsString( 'progress-bar-animated', $rows[ 1 ][ 'progress_bar_class' ] );
		$this->assertNotSame( $rows[ 0 ][ 'status_icon_class' ], $rows[ 1 ][ 'status_icon_class' ] );
		$this->assertNotSame( $rows[ 0 ][ 'status_class' ], $rows[ 1 ][ 'status_class' ] );
		$this->assertSame( 40, $rows[ 1 ][ 'progress' ] );
	}

	public function test_render_data_exposes_recovery_for_current_stalled_row() :void {
		$renderData = ( new ScansProgressRenderTestDouble( [
			'modal_state'     => ScansBase::SCAN_MODAL_STATE_RUNNING,
			'current_scan'    => 'Asset Scan',
			'remaining_scans' => '1 scan remaining.',
			'progress'        => 10,
			'scan_rows'       => [
				$this->progressRow( [
					'id'                   => 13,
					'scan'                 => 'apc',
					'name'                 => 'Asset Scan',
					'scope_type'           => 'core',
					'display_status'       => 'stalled',
					'is_stale'             => true,
					'can_attempt_recovery' => true,
					'progress'             => 10,
					'total_items'          => 10,
					'unfinished'           => 9,
				] ),
			],
		] ) )->renderDataForTest();

		$rows = $renderData[ 'vars' ][ 'scan_rows' ];

		$this->assertCount( 1, $rows );
		$this->assertRenderRowShape( $rows[ 0 ] );
		$this->assertSame( 'Asset Scan', $rows[ 0 ][ 'name' ] );
		$this->assertTrue( $rows[ 0 ][ 'can_attempt_recovery' ] );
		$this->assertNotSame( '', $rows[ 0 ][ 'scope_label' ] );
		$this->assertNotSame( '', $rows[ 0 ][ 'status_label' ] );
		$this->assertStatusPresentationContract( $rows[ 0 ] );
		$this->assertStringNotContainsString( 'progress-bar-animated', $rows[ 0 ][ 'progress_bar_class' ] );
		$this->assertSame( 10, $rows[ 0 ][ 'progress' ] );
		$this->assertNotSame( '', $rows[ 0 ][ 'aria_label' ] );
	}

	public function test_completed_render_data_exposes_terminal_progress_when_no_rows_are_present() :void {
		$renderData = ( new ScansProgressRenderTestDouble( [
			'modal_state'     => ScansBase::SCAN_MODAL_STATE_COMPLETED,
			'current_scan'    => 'No scan running.',
			'remaining_scans' => 'No scans remaining.',
			'progress'        => 100,
			'scan_rows'       => [],
		] ) )->renderDataForTest();

		$this->assertSame( [], $renderData[ 'vars' ][ 'scan_rows' ] );
		$this->assertSame( 100, $renderData[ 'vars' ][ 'progress' ] );
	}

	private function progressRow( array $overrides = [] ) :array {
		return \array_merge( [
			'id'                   => 1,
			'scan'                 => 'afs',
			'name'                 => 'File Guard',
			'scope_type'           => 'full',
			'scope_key'            => '',
			'raw_status'           => 'running',
			'display_status'       => 'running',
			'is_current'           => true,
			'is_stale'             => false,
			'can_attempt_recovery' => false,
			'progress'             => 0,
			'total_items'          => 0,
			'unfinished'           => 0,
		], $overrides );
	}

	private function assertRenderRowShape( array $row ) :void {
		$expected = [
			'aria_label',
			'can_attempt_recovery',
			'id',
			'name',
			'progress',
			'progress_bar_class',
			'scope_label',
			'status_class',
			'status_icon_class',
			'status_label',
		];
		$actual = \array_keys( $row );
		\sort( $actual );
		$this->assertSame( $expected, $actual );
	}

	private function assertStatusPresentationContract( array $row ) :void {
		foreach ( [ 'status_label', 'status_icon_class', 'status_class', 'progress_bar_class' ] as $key ) {
			$this->assertIsString( $row[ $key ] );
			$this->assertNotSame( '', $row[ $key ] );
		}
	}
}

class ScansProgressRenderTestDouble extends ScansProgress {

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}
}
