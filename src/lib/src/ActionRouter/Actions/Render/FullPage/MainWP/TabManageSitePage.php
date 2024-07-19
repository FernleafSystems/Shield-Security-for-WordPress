<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\MainWP;

class TabManageSitePage extends BaseMainwpPage {

	public const SLUG = 'render_page_mainwp_tab_manage_site';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'page_title' => '',
			],
			'hrefs'   => [
			],
			'imgs'    => [
			],
			'flags'   => [
			],
			'content' => [
				'main' => $this->renderMainBodyContent(),
			]
		];
	}

	protected function renderMainBodyContent() :string {
		return 'no content yet';
	}

	protected function getRequiredDataKeys() :array {
		return [
			'site_id',
		];
	}
}