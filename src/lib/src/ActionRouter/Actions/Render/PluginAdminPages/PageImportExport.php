<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportFromFileUpload;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\GetOptionsForZoneComponents;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\ImportExport;

class PageImportExport extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_importexport';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/import.twig';

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s/%s', __( 'Help', 'wp-simple-firewall' ), __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/129-how-to-create-shield-security-network-with-automatic-import-export-feature',
			'new_window' => true,
		];
	}

	protected function getRenderData() :array {
		$con = self::con();
		$importMasterURL = $con->comps->import_export->getImportExportMasterImportUrl();
		return [
			'content' => [
				'import_export_config' => $con->action_router->render( OptionsFormFor::class, [
					'options' => ( new GetOptionsForZoneComponents() )->run( [ ImportExport::Slug() ] )
				] ),
			],
			'flags'   => [
				'can_importexport'      => $con->caps->canImportExportFile() || $con->caps->canImportExportSync(),
				'can_importexport_file' => $con->caps->canImportExportFile(),
				'can_importexport_sync' => $con->caps->canImportExportSync(),
				'has_master_url'        => !empty( $importMasterURL ),
			],
			'hrefs'   => [
				'export_file_download' => $con->plugin_urls->fileDownload( 'plugin_export' ),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->raw( 'arrow-down-up' ),
			],
			'vars'    => [
				'file_upload_nonce'  => ActionData::Build( PluginImportFromFileUpload::class, true, [
					'notification_type' => 'wp_admin_notice'
				] ),
				'current_master_url' => $importMasterURL,
			],
			'strings' => [
				'inner_page_title'    => __( 'Import/Export', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Quickly setup your site by importing from another site or a backup.', 'wp-simple-firewall' ),

				'tab_by_file'        => __( 'Import From File', 'wp-simple-firewall' ),
				'tab_by_site'        => __( 'Run Import From Another Site', 'wp-simple-firewall' ),
				'tab_to_file'        => __( 'Export To File', 'wp-simple-firewall' ),
				'tab_config'         => __( 'Edit Settings', 'wp-simple-firewall' ),
				'title_import_file'  => __( 'Import From File', 'wp-simple-firewall' ),
				'select_import_file' => __( 'Select file to import options from', 'wp-simple-firewall' ),
				'i_understand'       => __( 'I Understand Existing Options Will Be Overwritten', 'wp-simple-firewall' ),
				'be_sure'            => __( 'Please be sure that this is what you want.', 'wp-simple-firewall' ),
				'not_undone'         => __( "This action can't be undone.", 'wp-simple-firewall' ),
				'title_import_site'  => __( "Import From Site", 'wp-simple-firewall' ),

				'currently_in_network' => __( "This site appears to be part of a Shield Network.", 'wp-simple-firewall' ),
				'master_url_is'        => __( "Master Site URL", 'wp-simple-firewall' ),

				'title_download_file'    => __( 'Download Options Export File', 'wp-simple-firewall' ),
				'subtitle_download_file' => __( 'Use this file to copy options from this site into another site', 'wp-simple-firewall' ),

				'master_site_url'  => __( 'Master Site URL', 'wp-simple-firewall' ),
				'remember_include' => sprintf(
					__( 'Remember to include %s or %s', 'wp-simple-firewall' ),
					'<code>https://</code>',
					'<code>http://</code>'
				),
				'secret_key'       => __( 'Secret Key', 'wp-simple-firewall' ),
				'master_site_key'  => __( 'Master Site Secret Key', 'wp-simple-firewall' ),
				'create_network'   => __( 'Create Shield Network', 'wp-simple-firewall' ),
				'key_found_under'  => sprintf( __( 'The secret key is found in: %s', 'wp-simple-firewall' ),
					ucwords( sprintf( '%s > %s > %s ', __( 'General Settings', 'wp-simple-firewall' ), __( 'Import/Export', 'wp-simple-firewall' ), __( 'Secret Key', 'wp-simple-firewall' ) ) )
				),
				'turn_on'          => __( 'Turn On', 'wp-simple-firewall' ),
				'turn_off'         => __( 'Turn Off', 'wp-simple-firewall' ),
				'no_change'        => __( 'No Change', 'wp-simple-firewall' ),
				'network_explain'  => [
					__( 'Checking this option on will link this site to Master site.', 'wp-simple-firewall' ),
					__( 'Options will be automatically imported from the Master site each night', 'wp-simple-firewall' ),
					__( 'When you adjust options on the Master site, they will be reflected in this site after the automatic import', 'wp-simple-firewall' ),
				],
				'import_options'   => __( 'Import Options', 'wp-simple-firewall' ),
			]
		];
	}
}