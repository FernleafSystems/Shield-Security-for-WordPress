<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class PageRulesBase extends BasePluginAdminPage {

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