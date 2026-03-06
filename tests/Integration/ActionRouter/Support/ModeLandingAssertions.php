<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

trait ModeLandingAssertions {

	private function assertModeShellPayload( array $vars, string $mode, string $accentStatus, bool $isInteractive = false ) :void {
		$modeShell = \is_array( $vars[ 'mode_shell' ] ?? null ) ? $vars[ 'mode_shell' ] : [];
		$this->assertSame( $mode, (string)( $modeShell[ 'mode' ] ?? '' ), 'Mode shell payload mode contract' );
		$this->assertSame( $accentStatus, (string)( $modeShell[ 'accent_status' ] ?? '' ), 'Mode shell payload accent contract' );
		$this->assertTrue( (bool)( $modeShell[ 'is_mode_landing' ] ?? false ), 'Mode shell payload landing contract' );
		$this->assertSame( $isInteractive, (bool)( $modeShell[ 'is_interactive' ] ?? !$isInteractive ), 'Mode shell payload interactive contract' );
	}

	private function assertModePanelPayload( array $vars, string $activeTarget, bool $isOpen ) :void {
		$modePanel = \is_array( $vars[ 'mode_panel' ] ?? null ) ? $vars[ 'mode_panel' ] : [];
		$this->assertSame( $activeTarget, (string)( $modePanel[ 'active_target' ] ?? '' ), 'Mode panel payload active-target contract' );
		$this->assertSame( $isOpen, (bool)( $modePanel[ 'is_open' ] ?? !$isOpen ), 'Mode panel payload open-state contract' );
	}

	private function assertModeShellContract( \DOMXPath $xpath, string $mode, string $labelPrefix, bool $isInteractive = false ) :void {
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-shell="1" and @data-mode="'.$mode.'" and @data-mode-interactive="'.( $isInteractive ? '1' : '0' ).'"]',
			$labelPrefix.' mode shell contract marker'
		);
	}

	private function assertModeAccentContract( \DOMXPath $xpath, string $accentStatus, string $labelPrefix ) :void {
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-accent="1" and @data-mode-accent-status="'.$accentStatus.'"]',
			$labelPrefix.' mode accent marker'
		);
	}

	private function assertModeShellAndAccentContract( \DOMXPath $xpath, string $mode, string $accentStatus, string $labelPrefix, bool $isInteractive = false ) :void {
		$this->assertModeShellContract( $xpath, $mode, $labelPrefix, $isInteractive );
		$this->assertModeAccentContract( $xpath, $accentStatus, $labelPrefix );
	}

	private function assertSharedModePanelMarker( \DOMXPath $xpath, string $labelPrefix ) :void {
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-panel="1"]',
			$labelPrefix.' shared mode panel marker'
		);
	}

	private function assertSharedModePanelMarkerCount( \DOMXPath $xpath, int $expectedCount, string $labelPrefix ) :void {
		$this->assertXPathCount(
			$xpath,
			'//*[@data-mode-panel="1"]',
			$expectedCount,
			$labelPrefix.' shared mode panel marker count'
		);
	}

	private function assertModePanelHasDataAttribute( \DOMXPath $xpath, string $dataAttribute, string $labelPrefix ) :void {
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-panel="1" and @data-'.$dataAttribute.']',
			$labelPrefix.' mode panel data attribute '.$dataAttribute
		);
	}
}
