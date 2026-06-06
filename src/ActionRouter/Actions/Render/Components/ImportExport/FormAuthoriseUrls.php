<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;

class FormAuthoriseUrls extends BaseRender {

	public const SLUG = 'form_import_export_sites_authorise_urls';
	public const TEMPLATE = '/components/forms/import_export_sites_authorise_urls.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'urls'             => __( 'Site URLs', 'wp-simple-firewall' ),
				'urls_help'        => __( 'Enter one public HTTP or HTTPS site URL per line.', 'wp-simple-firewall' ),
				'urls_placeholder' => "https://example.com\nhttps://example.org/",
				'confirm'          => __( 'I understand these sites will be authorised to export settings from this site and sent one network invite request.', 'wp-simple-firewall' ),
				'submit'           => __( 'Authorise New URLs', 'wp-simple-firewall' ),
			],
		];
	}
}
