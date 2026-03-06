<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class PageInvestigateByIp extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_investigate_by_ip';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_by_ip.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$lookup = $this->getTextInputFromRequestOrActionData( 'analyse_ip' );
		$shared = ( new InvestigateByIpViewBuilder() )->build( $lookup );

		return [
			'flags'   => $shared[ 'flags' ],
			'hrefs'   => [
				'by_ip' => $shared[ 'hrefs' ][ 'by_ip' ],
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'globe2' ),
			],
			'strings' => \array_merge(
				$shared[ 'strings' ],
				[
				'inner_page_title'    => __( 'Investigate By IP', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Inspect sessions, activity, and request behavior for a specific IP address.', 'wp-simple-firewall' ),
				'change_subject'      => __( 'Change IP address', 'wp-simple-firewall' ),
				]
			),
			'vars'    => $shared[ 'vars' ],
			'content' => $shared[ 'content' ],
		];
	}
}
