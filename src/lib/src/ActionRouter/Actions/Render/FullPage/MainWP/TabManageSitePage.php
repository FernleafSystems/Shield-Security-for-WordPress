<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\MainWP;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP\ServerActions\MainwpServerClientActionHandler;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsForm;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

class TabManageSitePage extends BaseMainwpPage {

	public const SLUG = 'render_page_mainwp_tab_manage_site';

	protected function getRenderData() :array {
		$con = $this->con();
		return [
			'strings' => [
				'page_title' => sprintf( __( '%s Login Verification', 'wp-simple-firewall' ), $con->getHumanName() ),
			],
			'hrefs'   => [
				'what_is_this' => 'https://help.getshieldsecurity.com/article/322-what-is-the-login-authentication-portal',
			],
			'imgs'    => [
				'logo_banner' => $con->labels->url_img_pagebanner,
				'favicon'     => $con->labels->icon_url_32x32,
			],
			'flags'   => [
			],
			'content' => [
				'main' => $this->renderMainBodyContent(),
			]
		];
	}

	protected function renderMainBodyContent() :string {
		try {
			return $this->con()->action_router->action( MainwpServerClientActionHandler::class, [
				'site_id'            => $this->action_data[ 'site_id' ],
				'site_action_slug'   => OptionsForm::SLUG,
				'site_action_params' => [
					'mod_slug' => 'plugin'
				],
			] )->action_response_data[ 'render_output' ];
		}
		catch ( ActionException $e ) {
			return 'error rendering main body content: '.$e->getMessage();
		}
	}

	protected function getRequiredDataKeys() :array {
		return [
			'site_id',
		];
	}
}