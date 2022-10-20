<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\PluginImportFromFileUpload;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\PluginImportFromSite;

class PageImportExport extends BasePluginAdminPage {

	const SLUG = 'admin_plugin_page_importexport';
	const PRIMARY_MOD = 'plugin';
	const TEMPLATE = '/wpadmin_pages/insights/importexport/index.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		$mod = $this->primary_mod;
		return [
			'vars'    => [
				'file_upload_nonce' => $con->getShieldActionNonceData( PluginImportFromFileUpload::SLUG, [
					'notification_type' => 'wp_admin_notice'
				] ),
			],
			'ajax'    => [
				'import_from_site' => ActionData::BuildJson( PluginImportFromSite::SLUG ),
			],
			'flags'   => [
				'can_importexport' => $con->isPremiumActive(),
			],
			'hrefs'   => [
				'export_file_download' => ActionData::FileDownloadHref( 'plugin_export' ),
			],
			'strings' => [
				'tab_by_file'          => __( 'Import From File', 'wp-simple-firewall' ),
				'tab_by_site'          => __( 'Import From Another Site', 'wp-simple-firewall' ),
				'title_import_file'    => __( 'Import From File', 'wp-simple-firewall' ),
				'subtitle_import_file' => __( 'Upload an exported options file you downloaded from another site', 'wp-simple-firewall' ),
				'select_import_file'   => __( 'Select file to import options from', 'wp-simple-firewall' ),
				'i_understand'         => __( 'I Understand Existing Options Will Be Overwritten', 'wp-simple-firewall' ),
				'be_sure'              => __( 'Please be sure that this is what you want.', 'wp-simple-firewall' ),
				'not_undone'           => __( "This action can't be undone.", 'wp-simple-firewall' ),
				'title_import_site'    => __( "Import From Site", 'wp-simple-firewall' ),

				'title_download_file'    => __( 'Download Options Export File', 'wp-simple-firewall' ),
				'subtitle_download_file' => __( 'Use this file to copy options from this site into another site', 'wp-simple-firewall' ),

				'subtitle_import_site' => __( 'Import options directly from another site', 'wp-simple-firewall' ),
				'master_site_url'      => __( 'Master Site URL', 'wp-simple-firewall' ),
				'remember_include'     => sprintf(
					__( 'Remember to include %s or %s', 'wp-simple-firewall' ),
					'<code>https://</code>',
					'<code>http://</code>'
				),
				'secret_key'           => __( 'Secret Key', 'wp-simple-firewall' ),
				'master_site_key'      => __( 'Master Site Secret Key', 'wp-simple-firewall' ),
				'create_network'       => __( 'Create Shield Network', 'wp-simple-firewall' ),
				'key_found_under'      => sprintf( __( 'The secret key is found in: %s', 'wp-simple-firewall' ),
					ucwords( sprintf( '%s > %s > %s ', __( 'General Settings', 'wp-simple-firewall' ), __( 'Import/Export', 'wp-simple-firewall' ), __( 'Secret Key', 'wp-simple-firewall' ) ) )
				),
				'turn_on'              => __( 'Turn On', 'wp-simple-firewall' ),
				'turn_off'             => __( 'Turn Off', 'wp-simple-firewall' ),
				'no_change'            => __( 'No Change', 'wp-simple-firewall' ),
				'network_explain'      => [
					__( 'Checking this option on will link this site to Master site.', 'wp-simple-firewall' ),
					__( 'Options will be automatically imported from the Master site each night', 'wp-simple-firewall' ),
					__( 'When you adjust options on the Master site, they will be reflected in this site after the automatic import', 'wp-simple-firewall' ),
				],
				'import_options'       => __( 'Import Options', 'wp-simple-firewall' ),
			]
		];
	}
}