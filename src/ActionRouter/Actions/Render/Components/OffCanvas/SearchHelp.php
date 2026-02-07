<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\SearchTextParser;

class SearchHelp extends OffCanvasBase {

	public const SLUG = 'offcanvas_search_help';

	protected function buildCanvasTitle() :string {
		return __( 'Search Help', 'wp-simple-firewall' );
	}

	protected function buildCanvasBody() :string {
		$rows = '';
		foreach ( $this->getSearchPrefixes() as $prefix ) {
			$rows .= \sprintf( '<tr><td><code>%s</code></td><td>%s</td><td><code>%s</code></td></tr>',
				esc_html( $prefix[ 'prefix' ] ),
				esc_html( $prefix[ 'description' ] ),
				esc_html( $prefix[ 'example' ] )
			);
		}

		return \sprintf(
			'<p>%s</p><table class="table table-striped table-sm"><thead><tr><th>%s</th><th>%s</th><th>%s</th></tr></thead><tbody>%s</tbody></table>',
			esc_html__( 'Use these prefixes in the search box to filter results.', 'wp-simple-firewall' ),
			esc_html__( 'Prefix', 'wp-simple-firewall' ),
			esc_html__( 'Description', 'wp-simple-firewall' ),
			esc_html__( 'Example', 'wp-simple-firewall' ),
			$rows
		);
	}

	private function getSearchPrefixes() :array {
		return \array_map(
			fn( string $prefix, array $def ) => [
				'prefix'      => $prefix.':',
				'description' => __( $def[ 'description' ], 'wp-simple-firewall' ),
				'example'     => $def[ 'example' ],
			],
			\array_keys( SearchTextParser::GetFilterDefinitions() ),
			\array_values( SearchTextParser::GetFilterDefinitions() )
		);
	}
}
