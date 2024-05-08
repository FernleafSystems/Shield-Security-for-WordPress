<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Services\Services;

class Plugins extends PluginThemesBase {

	public const SLUG = 'scanresults_plugins';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/section/assets/plugins_index.twig';

	protected function getRenderData() :array {
		$items = $this->buildPluginsData();
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
				'no_files'      => __( "Scans didn't detect any modified or unrecognised files in the plugin directory.", 'wp-simple-firewall' ),
				'files_found'   => __( "Scans detected modified or unrecognised files in the plugin directory.", 'wp-simple-firewall' ),
				'not_active'    => __( "This plugin isn't active and should be uninstalled.", 'wp-simple-firewall' ),
				'wporg_ok'      => __( "This plugin is installed from WordPress.org so actions such as file repair and file diff are available.", 'wp-simple-firewall' ),
				'not_wporg'     => __( "This plugin isn't installed from WordPress.org so actions such as file repair and file diff aren't available.", 'wp-simple-firewall' ),
				'no_tags'       => __( "The plugin developer chose not to use SVN tags for this version, so actions such as file repair and file diff aren't available.", 'wp-simple-firewall' ),
				'go_to_plugins' => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Plugins' ) ),
			],
			'hrefs'   => [
				'page_plugins' => Services::WpGeneral()->getAdminUrl_Plugins()
			],
			'vars'    => [
				'count_items' => \count( $vulnerable ) + \count( $hashes )
								 + \count( $abandoned ) + \count( $problems ),
				'plugins'     => \array_values( $items ),
			]
		] );
	}

	private function buildPluginsData() :array {
		return \array_map(
			function ( $plugin ) {
				return $this->buildPluginData( $plugin );
			},
			Services::WpPlugins()->getPluginsAsVo()
		);
	}
}