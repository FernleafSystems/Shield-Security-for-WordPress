<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
abstract class PageScansBase extends BasePluginAdminPage {

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', CommonDisplayStrings::get( 'help_label' ), __( 'Scans', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/452-a-complete-guide-to-the-shield-security-scans',
			'new_window' => true,
		];
	}

	protected function getRenderData() :array {
		return [
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'node-plus-fill' ),
			],
			'strings' => [
				'inner_page_title'    => $this->getInnerPageTitle(),
				'inner_page_subtitle' => $this->getInnerPageSubTitle(),
			],
		];
	}
}
