<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

trait ModeLandingAssertions {

	private function assertModeShellContract( \DOMXPath $xpath, string $mode, string $labelPrefix ) :void {
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-shell="1" and @data-mode="'.$mode.'" and @data-mode-interactive="0"]',
			$labelPrefix.' mode shell contract marker'
		);
	}

	private function assertModeAccentContract( \DOMXPath $xpath, string $accentStatus, string $labelPrefix ) :void {
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " mode-landing-accent-bar ") and contains(concat(" ", normalize-space(@class), " "), " status-'.$accentStatus.' ")]',
			$labelPrefix.' mode accent marker'
		);
	}

	private function assertModeShellAndAccentContract( \DOMXPath $xpath, string $mode, string $accentStatus, string $labelPrefix ) :void {
		$this->assertModeShellContract( $xpath, $mode, $labelPrefix );
		$this->assertModeAccentContract( $xpath, $accentStatus, $labelPrefix );
	}

	private function assertSharedModePanelMarker( \DOMXPath $xpath, string $labelPrefix ) :void {
		$this->assertXPathExists(
			$xpath,
			'//*[@data-mode-panel="1"]',
			$labelPrefix.' shared mode panel marker'
		);
	}
}
