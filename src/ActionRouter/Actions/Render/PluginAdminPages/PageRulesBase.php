<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

abstract class PageRulesBase extends PageModeLandingBase {

	protected function getRenderData() :array {
		$con = self::con();
		return \array_replace_recursive( parent::getRenderData(), [
			'flags'   => [
				'can_custom_rules' => $con->caps->canCustomSecurityRules(),
			],
			'hrefs'   => [
				'rules_builder' => $con->plugin_urls->rulesBuild(),
			],
			'vars'    => [
				'upgrade_feature' => [
					'title'      => __( 'Custom Security Rules', 'wp-simple-firewall' ),
					'icon_class' => 'bi bi-'.$this->getLandingIcon(),
					'summary'    => __( 'Create and manage custom rules to meet your security needs.', 'wp-simple-firewall' ),
				],
			],
			'strings' => [
				'what_is_custom_security_rules_feature' => __( 'What is the Custom Security Rules feature?', 'wp-simple-firewall' ),
			],
		] );
	}

	protected function getLandingIcon() :string {
		return 'node-plus-fill';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_CONFIGURE;
	}

	protected function hasOperatorModeParent() :bool {
		return true;
	}

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', CommonDisplayStrings::get( 'help_label' ), __( 'Custom Rules', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/category/777-custom-rules-manager',
			'new_window' => true,
		];
	}
}
