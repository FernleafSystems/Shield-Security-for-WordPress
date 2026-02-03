<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\{
	Block,
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class DisplayBlockPage extends Base {

	use Traits\IsTerminating;

	public const SLUG = 'display_block_page';

	public function execResponse() :void {
		$hook = $this->p->hook;
		if ( empty( $hook ) ) {
			$this->displayPage();
		}
		else {
			add_action( $hook, function () {
				$this->displayPage();
			}, $this->p->priority, 0 );
		}
	}

	private function displayPage() {
		self::con()->action_router->action( FullPageDisplay\DisplayBlockPage::class, [
			'render_slug' => $this->p->block_page_slug,
		] );
	}

	public function getParamsDef() :array {
		$blockPages = [
			Block\BlockIpAddressShield::SLUG          => 'IP Block Page (Shield)',
			Block\BlockIpAddressCrowdsec::SLUG        => 'IP Block Page (CrowdSec)',
			Block\BlockFirewall::SLUG                 => 'Firewall Block Page',
			Block\BlockAuthorFishing::SLUG            => 'Author Fishing Block Page',
			Block\BlockPageSiteBlockdown::SLUG        => 'Site Locked Down Block Page',
			Block\BlockTrafficRateLimitExceeded::SLUG => 'Traffic Rate Limit Exceeded Block Page',
		];
		return [
			'block_page_slug' => [
				'type'        => EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $blockPages ),
				'enum_labels' => $blockPages,
				'label'       => __( 'Block Page', 'wp-simple-firewall' ),
			],
			'hook'            => [
				'type'    => EnumParameters::TYPE_STRING,
				'label'   => __( 'Hook to attach to', 'wp-simple-firewall' ),
				'default' => ''
			],
			'priority'        => [
				'type'    => EnumParameters::TYPE_INT,
				'default' => 10,
				'label'   => __( 'Hook priority', 'wp-simple-firewall' ),
			],
		];
	}
}