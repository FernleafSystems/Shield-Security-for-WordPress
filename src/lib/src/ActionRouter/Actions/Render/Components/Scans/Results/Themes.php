<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Services\Services;

class Themes extends PluginThemesBase {

	public const SLUG = 'scanresults_themes';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/section/assets/themes_index.twig';

	protected function getRenderData() :array {
		$items = $this->buildThemesData();
		\ksort( $items );

		$hashes = [];
		$abandoned = [];
		$vulnerable = [];
		$problems = [];
		$inactive = [];
		$warning = [];
		foreach ( $items as $key => $item ) {
			if ( $item[ 'flags' ][ 'has_guard_files' ] ) {
				unset( $items[ $key ] );
				$hashes[] = $item;
			}
			elseif ( $item[ 'flags' ][ 'is_vulnerable' ] ) {
				unset( $items[ $key ] );
				$vulnerable[] = $item;
			}
			elseif ( $item[ 'flags' ][ 'is_abandoned' ] ) {
				unset( $items[ $key ] );
				$abandoned[] = $item;
			}
			elseif ( $item[ 'flags' ][ 'has_issue' ] ) {
				unset( $items[ $key ] );
				$problems[] = $item;
			}
			elseif ( $item[ 'flags' ][ 'has_warning' ] ) {
				unset( $items[ $key ] );
				$warning[] = $item;
			}
		}

		$items = \array_merge( $vulnerable, $hashes, $abandoned, $problems, $warning, $inactive, $items );

		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'no_files'     => __( "Scans didn't detect any modified or unrecognised files in the Theme directories.", 'wp-simple-firewall' ),
				'files_found'  => __( "Scans detected 1 or more modified or unrecognised files in the theme directory.", 'wp-simple-firewall' ),
				'not_active'   => __( "This theme isn't active and should be uninstalled.", 'wp-simple-firewall' ),
				'go_to_themes' => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Themes' ) ),
			],
			'hrefs'   => [
				'page_themes' => Services::WpGeneral()->getAdminUrl_Themes()
			],
			'vars'    => [
				'count_items' => \count( $vulnerable ) + \count( $hashes )
								 + \count( $abandoned ) + \count( $problems ),
				'themes'      => \array_values( $items ),
			]
		] );
	}

	private function buildThemesData() :array {
		return \array_map(
			function ( $item ) {
				return $this->buildThemeData( $item );
			},
			Services::WpThemes()->getThemesAsVo()
		);
	}
}