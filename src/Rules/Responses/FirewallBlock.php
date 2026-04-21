<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers\AlertHandlerFirewallBlock;
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
		if ( $con->opts->optIs( 'instant_alert_firewall_block', 'email' ) ) {
			do_action( 'shield/firewall_pre_block' );
			$con->comps->events->fireEvent(
				$this->sendBlockEmail() ? 'fw_email_success' : 'fw_email_fail',
				[ 'audit_params' => [ 'to' => $con->comps->opts_lookup->getReportEmail() ] ]
			);
		}
	}

	private function sendBlockEmail() :bool {
		$con = self::con();
		return $con->comps->instant_alerts->updateAlertDataFor(
			new AlertHandlerFirewallBlock(),
			[
				'firewall_block' => $this->buildAlertPayload(),
			]
		);
	}

	/**
	 * @return array{
	 *   ip:string,
	 *   request_path:string,
	 *   firewall_rule_name:string,
	 *   match_pattern:string,
	 *   match_request_param:string,
	 *   match_request_value:string
	 * }
	 */
	private function buildAlertPayload() :array {
		$blockMeta = self::con()->rules->getConditionMeta()->getRawData();
		$fallback = static fn( string $value, string $default = 'Unavailable' ) :string => $value !== '' ? $value : $default;

		return [
			'ip'                  => $fallback( (string)$this->req->ip ),
			'request_path'        => $fallback( (string)Services::Request()->getPath() ),
			'firewall_rule_name'  => $fallback( (string)( $blockMeta[ 'match_name' ] ?? '' ), 'Unknown' ),
			'match_pattern'       => $fallback( (string)( $blockMeta[ 'match_pattern' ] ?? '' ) ),
			'match_request_param' => $fallback( (string)( $blockMeta[ 'match_request_param' ] ?? '' ) ),
			'match_request_value' => $fallback( (string)( $blockMeta[ 'match_request_value' ] ?? '' ) ),
		];
	}
}
