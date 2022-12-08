<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;
use FernleafSystems\Wordpress\Services\Services;

class FirewallBlock extends Base {

	public const SLUG = 'firewall_block';

	protected function execResponse() :bool {
		$this->runBlock();
		return true;
	}

	/**
	 * @throws \Exception
	 */
	private function runBlock() {
		$mod = $this->getCon()->getModule_Firewall();

		$this->preBlock();

		remove_filter( 'wp_robots', 'wp_robots_noindex_search' );
		remove_filter( 'wp_robots', 'wp_robots_noindex_embeds' );
		Services::WpGeneral()->turnOffCache();
		nocache_headers();

		switch ( $mod->getBlockResponse() ) {
			case 'redirect_die':
				Services::WpGeneral()->wpDie( 'Firewall Triggered' );
				break;
			case 'redirect_die_message':
				$this->getCon()
					 ->getModule_Insights()
					 ->getActionRouter()
					 ->action( Actions\FullPageDisplay\DisplayBlockPage::SLUG, [
						 'render_slug'     => Actions\Render\FullPage\Block\BlockFirewall::SLUG,
						 'render_data'     => [
							 'block_meta_data' => $this->getConsolidatedConditionMeta()
						 ],
					 ] );
				break;
			case 'redirect_home':
				Services::Response()->redirectToHome();
				break;
			case 'redirect_404':
				Services::Response()->sendApache404();
				break;
			default:
				break;
		}
		die();
	}

	private function preBlock() {
		$mod = $this->getCon()->getModule_Firewall();
		/** @var Options $opts */
		$opts = $mod->getOptions();
		if ( $opts->isSendBlockEmail() ) {
			$this->getCon()->fireEvent(
				$this->sendBlockEmail() ? 'fw_email_success' : 'fw_email_fail',
				[ 'audit_params' => [ 'to' => $mod->getPluginReportEmail() ] ]
			);
		}
	}

	private function sendBlockEmail() :bool {
		$con = $this->getCon();

		$blockMeta = $this->getConsolidatedConditionMeta();
		$fwCategory = $blockMeta[ 'match_category' ] ?? '';
		try {
			$ruleName = $con->getModule_Firewall()
							->getStrings()
							->getOptionStrings( 'block_'.$fwCategory )[ 'name' ] ?? 'Unknown';
		}
		catch ( \Exception $e ) {
			$ruleName = 'Unknown';
		}
		$blockMeta[ 'firewall_rule_name' ] = $ruleName;

		$mod = $con->getModule_Insights();
		return $mod->getEmailProcessor()
				   ->send(
					   $mod->getPluginReportEmail(),
					   __( 'Firewall Block Alert', 'wp-simple-firewall' ),
					   $mod->getActionRouter()
						   ->render( Actions\Render\Components\Email\FirewallBlockAlert::SLUG, [
							   'ip'         => $con->this_req->ip,
							   'block_meta' => $blockMeta
						   ] )
				   );
	}
}