<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

abstract class PageScansBase extends BasePluginAdminPage {

	protected function getPageContextualHrefs_Help() :array {
		return [
			'text'       => sprintf( '%s: %s', __( 'Help', 'wp-simple-firewall' ), __( 'Scans', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/452-a-complete-guide-to-the-shield-security-scans',
			'new_window' => true,
		];
	}
}