<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

trait ModeLandingAssertions {

	private function assertModeShellPayload( array $vars, string $mode, string $accentStatus, bool $isInteractive = false ) :void {
		$modeShell = \is_array( $vars[ 'mode_shell' ] ?? null ) ? $vars[ 'mode_shell' ] : [];
		$this->assertSame( $mode, (string)( $modeShell[ 'mode' ] ?? '' ), 'Mode shell payload mode contract' );
		$this->assertSame( $accentStatus, (string)( $modeShell[ 'accent_status' ] ?? '' ), 'Mode shell payload accent contract' );
		$this->assertTrue( (bool)( $modeShell[ 'is_mode_landing' ] ?? false ), 'Mode shell payload landing contract' );
		$this->assertSame( $isInteractive, (bool)( $modeShell[ 'is_interactive' ] ?? !$isInteractive ), 'Mode shell payload interactive contract' );
		$this->assertTrue( (bool)( $modeShell[ 'use_operator_chrome' ] ?? false ), 'Mode shell payload operator chrome contract' );
		$this->assertNotSame( '', (string)( $modeShell[ 'root_step' ][ 'title' ] ?? '' ), 'Mode shell payload root step title contract' );
		$this->assertNotSame( '', (string)( $modeShell[ 'root_step_json' ] ?? '' ), 'Mode shell payload root step JSON contract' );
	}

	private function assertModePanelPayload( array $vars, string $activeTarget, bool $isOpen ) :void {
		$modePanel = \is_array( $vars[ 'mode_panel' ] ?? null ) ? $vars[ 'mode_panel' ] : [];
		$this->assertSame( $activeTarget, (string)( $modePanel[ 'active_target' ] ?? '' ), 'Mode panel payload active-target contract' );
		$this->assertSame( $isOpen, (bool)( $modePanel[ 'is_open' ] ?? !$isOpen ), 'Mode panel payload open-state contract' );
	}
}
