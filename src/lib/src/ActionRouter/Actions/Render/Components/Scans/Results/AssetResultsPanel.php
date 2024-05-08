<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;

class AssetResultsPanel extends PluginThemesBase {

	public const SLUG = 'scan_asset_results_panel';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/section/assets/%s_panel.twig';

	protected function getRenderData() :array {
		$type = $this->action_data[ 'asset_type' ];
		$uniqID = $this->action_data[ 'unique_id' ];

		if ( $type === 'plugin' ) {
			$data = $this->buildPluginData( Services::WpPlugins()->getPluginAsVo( $uniqID ), true );
		}
		else {
			$data = $this->buildThemeData( Services::WpThemes()->getThemeAsVo( $uniqID ) );
		}

		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'no_files'      => __( "Scans didn't detect any modified or unrecognised files.", 'wp-simple-firewall' ),
				'files_found'   => __( "Scans detected 1 or more modified or unrecognised files.", 'wp-simple-firewall' ),
				'not_active'    => __( "This isn't active and should be uninstalled.", 'wp-simple-firewall' ),
				'wporg_ok'      => __( "This is installed from WordPress.org so actions such as file repair and file diff are available.", 'wp-simple-firewall' ),
				'not_wporg'     => __( "This isn't installed from WordPress.org so actions such as file repair and file diff aren't available.", 'wp-simple-firewall' ),
				'no_tags'       => __( "The developer chose not to use SVN tags for this version, so actions such as file repair and file diff aren't available.", 'wp-simple-firewall' ),
				'go_to_plugins' => sprintf( __( 'Go To %s', 'wp-simple-firewall' ), __( 'Plugins' ) ),
			],
			'hrefs'   => [
				'page_plugins' => Services::WpGeneral()->getAdminUrl_Plugins()
			],
			'vars'    => [
				'asset_data' => $data,
			]
		] );
	}

	protected function getRenderTemplate() :string {
		$type = $this->action_data[ 'asset_type' ];
		if ( !\in_array( $type, [ 'plugin', 'theme' ] ) ) {
			throw new ActionException( 'Invalid type.' );
		}
		return sprintf( self::TEMPLATE, $this->action_data[ 'asset_type' ] );
	}
}