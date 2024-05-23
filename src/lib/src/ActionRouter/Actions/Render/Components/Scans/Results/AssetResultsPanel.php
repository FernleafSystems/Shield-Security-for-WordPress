<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;

class AssetResultsPanel extends PluginThemesBase {

	public const SLUG = 'scan_asset_results_panel';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/section/assets/%s_panel.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$type = $this->action_data[ 'asset_type' ];
		$uniqID = $this->action_data[ 'unique_id' ];

		$data = $type === 'plugin' ?
			$this->buildPluginData( Services::WpPlugins()->getPluginAsVo( $uniqID ), true )
			: $this->buildThemeData( Services::WpThemes()->getThemeAsVo( $uniqID ), true );

		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'no_files'         => __( "Scans didn't detect any modified or unrecognised files", 'wp-simple-firewall' ),
				'files_found'      => __( "Scans detected modified/unrecognised files", 'wp-simple-firewall' ),
				'not_active'       => __( 'Inactive' ),
				'should_uninstall' => __( "Should be uninstalled", 'wp-simple-firewall' ),
				'wporg_ok'         => __( "Auto File Repair and File Diff are available", 'wp-simple-firewall' ),
				'not_wporg'        => __( "Auto File Repair and File Diff aren't available.", 'wp-simple-firewall' ),
				'no_tags'          => __( "The developer chose not to use SVN tags for this version, so actions such as file repair and file diff aren't available.", 'wp-simple-firewall' ),
				'manage'           => __( 'Manage', 'wp-simple-firewall' ),
				'ignore'           => __( 'Ignore', 'wp-simple-firewall' ),
				'updates'          => __( 'Updates', 'wp-simple-firewall' ),
			],
			'hrefs'   => [
				'page_manage' => $type === 'plugin' ?
					Services::WpGeneral()->getAdminUrl_Plugins() : Services::WpGeneral()->getAdminUrl_Themes(),
			],
			'imgs'    => [
				'svgs' => [
					'shield_x'           => $con->svgs->raw( 'shield-fill-x.svg' ),
					'shield_check'       => $con->svgs->raw( 'shield-fill-check.svg' ),
					'shield_exclamation' => $con->svgs->raw( 'shield-fill-exclamation.svg' ),
				],
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