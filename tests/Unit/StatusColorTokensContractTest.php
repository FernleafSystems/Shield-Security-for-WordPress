<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class StatusColorTokensContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	/**
	 * @return array<string, string>
	 */
	private function expectedTokenValues() :array {
		return [
			'status-bg-info-light' => '#e7f7fb',
			'badge-good-bg'        => '#e6f5e6',
			'badge-good-color'     => '#006400',
			'badge-warning-bg'     => '#fef6e6',
			'badge-warning-color'  => '#b97a00',
			'badge-critical-bg'    => '#fdeaec',
			'badge-critical-color' => '#c62f3e',
			'badge-info-bg'        => '#e7f7fb',
			'badge-info-color'     => '#0ea8c7',
		];
	}

	public function testScssStatusTokenValuesMatchContract() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'assets/css source stylesheets are excluded from packaged artifacts' );
		}

		$content = $this->getPluginFileContents(
			'assets/css/shield/_status-colors.scss',
			'shared status color tokens stylesheet'
		);

		foreach ( $this->expectedTokenValues() as $token => $value ) {
			$this->assertMatchesRegularExpression(
				$this->buildValueAssignmentPattern( '$'.$token, $value ),
				$content,
				\sprintf( 'Expected SCSS token assignment not found: $%s: %s;', $token, $value )
			);
		}
	}

	public function testSecurityReportCssVariableValuesMatchContract() :void {
		$content = $this->getPluginFileContents(
			'templates/twig/pages/report/security.twig',
			'security report template'
		);

		foreach ( $this->expectedTokenValues() as $token => $value ) {
			$this->assertMatchesRegularExpression(
				$this->buildValueAssignmentPattern( '--'.$token, $value ),
				$content,
				\sprintf( 'Expected CSS variable assignment not found: --%s: %s;', $token, $value )
			);
		}
	}

	private function buildValueAssignmentPattern( string $propertyName, string $value ) :string {
		return '/^\s*'.\preg_quote( $propertyName, '/' ).'\s*:\s*'.\preg_quote( $value, '/' ).'\s*;/m';
	}
}
