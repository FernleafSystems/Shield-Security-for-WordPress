<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Services\Services;

class FirewallBlock extends Base {

	public const SLUG = 'firewall_block';

	public function execResponse() :void {
		$this->runBlock();
	}

	/**
	 * @throws \Exception
	 */
	private function runBlock() {
		$this->preBlock();

		remove_filter( 'wp_robots', 'wp_robots_noindex_search' );
		remove_filter( 'wp_robots', 'wp_robots_noindex_embeds' );
		Services::WpGeneral()->turnOffCache();
		nocache_headers();

		self::con()->action_router->action( Actions\FullPageDisplay\DisplayBlockPage::class, [
			'render_slug' => Actions\Render\FullPage\Block\BlockFirewall::SLUG,
			'render_data' => [
				'block_meta_data' => self::con()->rules->getConditionMeta()->getRawData(),
			],
		] );
		die();
	}

	private function preBlock() {
		$con = self::con();
		if ( $con->opts->optIs( 'block_send_email', 'Y' ) ) {
			do_action( 'shield/firewall_pre_block' );
			$con->fireEvent(
				$this->sendBlockEmail() ? 'fw_email_success' : 'fw_email_fail',
				[ 'audit_params' => [ 'to' => $con->comps->opts_lookup->getReportEmail() ] ]
			);
		}
	}

	private function sendBlockEmail() :bool {
		$con = self::con();

		$blockMeta = $con->rules->getConditionMeta()->getRawData();
		$blockMeta[ 'firewall_rule_name' ] = $blockMeta[ 'match_name' ] ?? 'Unknown';

		return $con->email_con->sendVO(
			EmailVO::Factory(
				$con->comps->opts_lookup->getReportEmail(),
				__( 'Firewall Block Alert', 'wp-simple-firewall' ),
				$con->action_router->render( Actions\Render\Components\Email\FirewallBlockAlert::SLUG, [
					'ip'         => $this->req->ip,
					'block_meta' => $blockMeta
				] )
			)
		);
	}
}