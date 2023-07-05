<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportFromFileUpload;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportFromSite;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;

class PageImportExport extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_importexport';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/import.twig';

	protected function getPageContextualHrefs() :array {
		return [
			[
				'text' => __( 'Configure Auto Import', 'wp-simple-firewall' ),
				'href' => $this->con()->plugin_urls->offCanvasConfigRender( 'section_importexport' ),
			],
		];
	}

	protected function getRenderData() :array {
		$con = $this->con();
		/** @var Options $opts */
		$opts = $con->getModule_Plugin()->getOptions();
		return [
			'ajax'    => [
				'import_from_site' => ActionData::BuildJson( PluginImportFromSite::class ),
			],
			'flags'   => [
				'can_importexport'      => $con->caps->canImportExportFile() && $con->caps->canImportExportSync(),
				'can_importexport_file' => $con->caps->canImportExportFile(),
				'can_importexport_sync' => $con->caps->canImportExportSync(),
				'has_master_url'        => $opts->hasImportExportMasterImportUrl(),
			],
			'hrefs'   => [
				'export_file_download' => $con->plugin_urls->fileDownload( 'plugin_export' ),
			],
			'vars'    => [
				'file_upload_nonce'  => ActionData::Build( PluginImportFromFileUpload::class, true, [
					'notification_type' => 'wp_admin_notice'
				] ),
				'current_master_url' => $opts->getImportExportMasterImportUrl(),
			],
			'strings' => [
				'inner_page_title'    => __( 'Import Configuration', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Quickly setup your site by importing from another site or a backup.', 'wp-simple-firewall' ),

				'tab_by_file'        => __( 'Import From File', 'wp-simple-firewall' ),
				'tab_by_site'        => __( 'Import From Another Site', 'wp-simple-firewall' ),
				'tab_to_file'        => __( 'Export To File', 'wp-simple-firewall' ),
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