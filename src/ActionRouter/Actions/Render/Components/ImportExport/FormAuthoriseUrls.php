<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;

class FormAuthoriseUrls extends BaseRender {

	public const SLUG = 'form_import_export_sites_authorise_urls';
	public const TEMPLATE = '/components/forms/import_export_sites_authorise_urls.twig';

	protected function getRenderData() :array {
		return [
			'vars'    => [
				'client_secret_key' => ( new ImportExportController() )->getImportExportSecretKey(),
			],
			'strings' => [
				'client_secret_key' => __( 'Client site secret key', 'wp-simple-firewall' ),
				'client_secret_help' => __( 'Use this key only when a client site is not already trusted by URL.', 'wp-simple-firewall' ),
				'urls'             => __( 'Client site URLs', 'wp-simple-firewall' ),
				'urls_help'        => __( 'Enter one HTTP or HTTPS site URL per line. Localhost and private IP addresses are not allowed.', 'wp-simple-firewall' ),
				'urls_placeholder' => "https://example.com\nhttps://example.org/",
				'confirm'          => __( 'I understand these client sites will be authorised to export settings from this site and sent one network invite request.', 'wp-simple-firewall' ),
				'submit'           => __( 'Add Client Sites', 'wp-simple-firewall' ),
			],
		];
	}
}
