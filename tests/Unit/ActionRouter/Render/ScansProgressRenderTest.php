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
				[
					'id'                   => 11,
					'scan'                 => 'afs',
					'name'                 => 'File Guard',
					'scope_type'           => 'full',
					'scope_key'            => '',
					'raw_status'           => 'running',
					'display_status'       => 'running',
					'is_current'           => true,
					'is_stale'             => false,
					'can_attempt_recovery' => false,
					'progress'             => 135,
					'total_items'          => 4,
					'unfinished'           => 0,
				],
				[
					'id'                   => 12,
					'scan'                 => 'wpv',
					'name'                 => 'Vulnerability Scan',
					'scope_type'           => 'plugin',
					'scope_key'            => 'shield-security',
					'raw_status'           => 'built',
					'display_status'       => 'waiting',
					'is_current'           => false,
					'is_stale'             => false,
					'can_attempt_recovery' => false,
					'progress'             => 40,
					'total_items'          => 5,
					'unfinished'           => 3,
				],
			],
		] ) )->renderDataForTest();

		$rows = $renderData[ 'vars' ][ 'scan_rows' ] ?? [];

		$this->assertTrue( $renderData[ 'vars' ][ 'has_scan_rows' ] ?? false );
		$this->assertCount( 2, $rows );
		$this->assertSame( 'File Guard', $rows[ 0 ][ 'name' ] );
		$this->assertSame( '', $rows[ 0 ][ 'scope_label' ] );
		$this->assertSame( 'running', $rows[ 0 ][ 'display_status' ] );
		$this->assertFalse( $rows[ 0 ][ 'can_attempt_recovery' ] );
		$this->assertNotSame( '', $rows[ 0 ][ 'status_label' ] );
		$this->assertSame( 100, $rows[ 0 ][ 'progress' ] );
		$this->assertNotSame( '', $rows[ 0 ][ 'aria_label' ] );
		$this->assertSame( 'Vulnerability Scan', $rows[ 1 ][ 'name' ] );
		$this->assertStringContainsString( 'shield-security', $rows[ 1 ][ 'scope_label' ] );
		$this->assertSame( 'waiting', $rows[ 1 ][ 'display_status' ] );
		$this->assertFalse( $rows[ 1 ][ 'can_attempt_recovery' ] );
		$this->assertNotSame( '', $rows[ 1 ][ 'status_label' ] );
		$this->assertSame( 40, $rows[ 1 ][ 'progress' ] );
	}

	public function test_render_data_exposes_recovery_for_current_stalled_row() :void {
		$renderData = ( new ScansProgressRenderTestDouble( [
			'modal_state'     => ScansBase::SCAN_MODAL_STATE_RUNNING,
			'current_scan'    => 'Asset Scan',
			'remaining_scans' => '1 scan remaining.',
			'progress'        => 10,
			'scan_rows'       => [
				[
					'id'                   => 13,
					'scan'                 => 'apc',
					'name'                 => 'Asset Scan',
					'scope_type'           => 'core',
					'scope_key'            => '',
					'raw_status'           => 'running',
					'display_status'       => 'stalled',
					'is_current'           => true,
					'is_stale'             => true,
					'can_attempt_recovery' => true,
					'progress'             => 10,
					'total_items'          => 10,
					'unfinished'           => 9,
				],
			],
		] ) )->renderDataForTest();

		$rows = $renderData[ 'vars' ][ 'scan_rows' ] ?? [];

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Asset Scan', $rows[ 0 ][ 'name' ] );
		$this->assertSame( 'stalled', $rows[ 0 ][ 'display_status' ] );
		$this->assertTrue( $rows[ 0 ][ 'can_attempt_recovery' ] );
		$this->assertNotSame( '', $rows[ 0 ][ 'scope_label' ] );
		$this->assertNotSame( '', $rows[ 0 ][ 'status_label' ] );
		$this->assertSame( 10, $rows[ 0 ][ 'progress' ] );
		$this->assertNotSame( '', $rows[ 0 ][ 'aria_label' ] );
	}

	public function test_render_data_preserves_single_progress_fallback_when_no_rows_are_present() :void {
		$renderData = ( new ScansProgressRenderTestDouble( [
			'modal_state'     => ScansBase::SCAN_MODAL_STATE_RUNNING,
			'current_scan'    => 'File Guard',
			'remaining_scans' => '1 scan remaining.',
			'progress'        => 42,
			'scan_rows'       => [],
		] ) )->renderDataForTest();

		$this->assertFalse( $renderData[ 'vars' ][ 'has_scan_rows' ] ?? true );
		$this->assertSame( [], $renderData[ 'vars' ][ 'scan_rows' ] ?? null );
		$this->assertSame( 42, $renderData[ 'vars' ][ 'progress' ] ?? null );
	}
}

class ScansProgressRenderTestDouble extends ScansProgress {

	public function renderDataForTest() :array {
		return $this->getRenderData();
	}
}
