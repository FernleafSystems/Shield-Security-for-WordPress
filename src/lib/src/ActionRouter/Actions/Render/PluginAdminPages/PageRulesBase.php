<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class PageRulesBase extends BasePluginAdminPage {

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'flags'   => [
				'can_custom_rules' => $con->caps->canCreateCustomRules(),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'node-plus-fill' ),
			],
			'strings' => [
				'inner_page_title'    => $this->getInnerPageTitle(),
				'inner_page_subtitle' => $this->getInnerPageSubTitle(),

				'cant_custom_rules' => __( 'Please upgrade your Shield Security subscription to access the Custom Security Rules Builder.', 'wp-simple-firewall' ),
			],
		];
	}
}