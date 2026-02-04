<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageRulesBase extends BasePluginAdminPage {

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'flags'   => [
				'can_custom_rules' => $con->caps->canCustomSecurityRules(),
			],
			'hrefs'   => [
				'rules_builder' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_RULES, PluginNavs::SUBNAV_RULES_BUILD ),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'node-plus-fill' ),
			],
			'strings' => [
				'inner_page_title'                      => $this->getInnerPageTitle(),
				'inner_page_subtitle'                   => $this->getInnerPageSubTitle(),
				'cant_custom_rules'                     => sprintf( __( 'Please upgrade your %s subscription to access the Custom Security Rules Builder.', 'wp-simple-firewall' ), self::con()->labels->Name ),
				'what_is_custom_security_rules_feature' => __( 'What is the Custom Security Rules feature?', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', CommonDisplayStrings::get( 'help_label' ), __( 'Custom Rules', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/category/777-custom-rules-manager',
			'new_window' => true,
		];
	}
}
