<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScansResultsViewBuilder;

class Vulnerabilities extends BaseRender {

	public const SLUG = 'scanresults_vulnerabilities';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/scan_results_rail_pane.twig';

	protected function getRenderData() :array {
		$section = $this->normalizeSection( $this->getTextInputFromRequestOrActionData( 'section' ) );
		$pane = ( new ScansResultsViewBuilder() )->buildRailPaneData(
			$section === 'abandoned' ? 'abandoned' : 'vulnerabilities',
			[],
			$section
		);

		return [
			'strings' => [
				'no_issues' => $this->noIssuesTextForSection( $section ),
			],
			'vars'    => [
				'count_items' => $pane[ 'count_items' ],
			],
			'tab'     => $pane,
			'content' => [],
		];
	}

	private function normalizeSection( string $section ) :?string {
		return \in_array( $section, [ 'vulnerable', 'abandoned' ], true ) ? $section : null;
	}

	private function noIssuesTextForSection( ?string $section ) :string {
		switch ( $section ) {
			case 'vulnerable':
				return __( "Previous scans didn't detect any vulnerable assets.", 'wp-simple-firewall' );
			case 'abandoned':
				return __( "Previous scans didn't detect any abandoned assets.", 'wp-simple-firewall' );
			default:
				return __( "Previous scans didn't detect any vulnerable assets.", 'wp-simple-firewall' );
		}
	}
}
