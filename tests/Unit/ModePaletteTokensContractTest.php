<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

class ModePaletteTokensContractTest extends BaseUnitTest {

	use PluginPathsTrait;

	/**
	 * @return array<string,string>
	 */
	private function expectedAssignments() :array {
		return [
			'mode-actions'            => '$danger',
			'mode-actions-light'      => '#fdeaec',
			'mode-actions-border'     => '#f5c6cb',
			'mode-investigate'        => '$info',
			'mode-investigate-light'  => '#e0f4f8',
			'mode-investigate-border' => '#b6e3ed',
			'mode-configure'          => '#6c4bb3',
			'mode-configure-light'    => '#efe8fb',
			'mode-configure-border'   => '#d8cdef',
			'mode-reports'            => '#c84d82',
			'mode-reports-light'      => '#fce8f1',
			'mode-reports-border'     => '#efc1d3',
		];
	}

	public function testModePaletteAssignmentsMatchExpectedTokens() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'assets/css source stylesheets are excluded from packaged artifacts' );
		}

		$assignments = $this->parseAssignments( $this->getPluginFileContents(
			'assets/css/_mode-palette.scss',
			'operator mode palette stylesheet'
		) );

		$this->assertSame( $this->expectedAssignments(), $this->selectAssignments(
			$assignments,
			\array_keys( $this->expectedAssignments() )
		) );
	}

	/**
	 * @return array<string,string>
	 */
	private function parseAssignments( string $content ) :array {
		$matched = \preg_match_all(
			'/^\s*\$(?P<token>[a-z0-9-]+)\s*:\s*(?P<value>[^;]+);/mi',
			$content,
			$matches,
			\PREG_SET_ORDER
		);
		$this->assertNotFalse( $matched, 'Failed to parse mode palette assignments' );

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
	 * @param array<string,string> $assignments
	 * @param list<string> $tokens
	 * @return array<string,string>
	 */
	private function selectAssignments( array $assignments, array $tokens ) :array {
		$selected = [];
		foreach ( $tokens as $token ) {
			$this->assertArrayHasKey( $token, $assignments, 'Mode palette token missing: '.$token );
			$selected[ $token ] = (string)$assignments[ $token ];
		}
		return $selected;
	}
}
