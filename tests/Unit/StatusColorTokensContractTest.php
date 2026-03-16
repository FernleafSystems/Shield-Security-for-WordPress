<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class StatusColorTokensContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	/**
	 * @return list<string>
	 */
	private function securityReportTokenNames() :array {
		return [
			'status-bg-info-light',
			'badge-good-bg',
			'badge-good-color',
			'badge-warning-bg',
			'badge-warning-color',
			'badge-critical-bg',
			'badge-critical-color',
			'badge-info-bg',
			'badge-info-color',
		];
	}

	public function testSecurityReportCssVariablesMirrorSharedScssTokens() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'assets/css source stylesheets are excluded from packaged artifacts' );
		}

		$scssAssignments = $this->parseAssignments( $this->getPluginFileContents(
			'assets/css/shield/_status-colors.scss',
			'shared status color tokens stylesheet'
		), '$' );
		$templateAssignments = $this->parseAssignments( $this->getPluginFileContents(
			'templates/twig/pages/report/security.twig',
			'security report template'
		), '--' );

		$expectedTokens = $this->securityReportTokenNames();
		$reportAssignments = $this->selectAssignments( $templateAssignments, $expectedTokens, 'Security report CSS variable' );
		$this->assertExpectedAssignments(
			$this->selectAssignments( $scssAssignments, $expectedTokens, 'SCSS status token' ),
			$reportAssignments,
			'Security report CSS variable'
		);
	}

	/**
	 * @return array<string,string>
	 */
	private function parseAssignments( string $content, string $prefix ) :array {
		$matched = \preg_match_all(
			'/^\s*'.\preg_quote( $prefix, '/' ).'(?P<token>[a-z0-9-]+)\s*:\s*(?P<value>[^;]+);/mi',
			$content,
			$matches,
			\PREG_SET_ORDER
		);
		$this->assertNotFalse( $matched, \sprintf( 'Failed to parse assignments for prefix "%s"', $prefix ) );

		$assignments = [];
		foreach ( $matches as $match ) {
			$token = \strtolower( \trim( (string)( $match[ 'token' ] ?? '' ) ) );
			if ( $token === '' ) {
				continue;
			}
			$assignments[ $token ] = \trim( (string)( $match[ 'value' ] ?? '' ) );
		}
		return $assignments;
	}

	/**
	 * @param array<string,string> $expected
	 * @param array<string,string> $actual
	 */
	private function assertExpectedAssignments( array $expected, array $actual, string $label ) :void {
		$missing = [];
		$mismatches = [];

		foreach ( $expected as $token => $expectedValue ) {
			if ( !\array_key_exists( $token, $actual ) ) {
				$missing[] = $token;
				continue;
			}

			$actualValue = (string)$actual[ $token ];
			if ( $this->normalizeValue( $actualValue ) !== $this->normalizeValue( $expectedValue ) ) {
				$mismatches[] = \sprintf( '%s expected "%s" got "%s"', $token, $expectedValue, $actualValue );
			}
		}

		if ( !empty( $missing ) || !empty( $mismatches ) ) {
			$messages = [];
			if ( !empty( $missing ) ) {
				$messages[] = 'missing ['.\implode( ', ', $missing ).']';
			}
			if ( !empty( $mismatches ) ) {
				$messages[] = 'mismatch ['.\implode( ' | ', $mismatches ).']';
			}
			$this->fail( $label.' contract mismatch: '.\implode( '; ', $messages ) );
		}
	}

	private function normalizeValue( string $value ) :string {
		$normalized = \preg_replace( '/\s+/', '', \trim( $value ) );
		return \strtolower( (string)$normalized );
	}

	/**
	 * @param array<string,string> $assignments
	 * @param list<string> $tokens
	 * @return array<string,string>
	 */
	private function selectAssignments( array $assignments, array $tokens, string $label ) :array {
		$selected = [];
		foreach ( $tokens as $token ) {
			$this->assertArrayHasKey( $token, $assignments, $label.' missing: '.$token );
			$selected[ $token ] = (string)$assignments[ $token ];
		}
		return $selected;
	}
}
