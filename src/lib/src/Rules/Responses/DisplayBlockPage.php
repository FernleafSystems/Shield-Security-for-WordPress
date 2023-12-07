<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class DisplayBlockPage extends Base {

	public const SLUG = 'display_block_page';

	public function execResponse() :bool {
		$hook = $this->params[ 'hook' ];
		if ( empty( $hook ) ) {
			$this->displayPage();
		}
		else {
			add_action( $hook, function () {
				$this->displayPage();
			}, $this->params[ 'hook_priority' ], 0 );
		}
		return true;
	}

	private function displayPage() {
		self::con()->action_router->action( Actions\FullPageDisplay\DisplayBlockPage::class, [
			'render_slug' => $this->params[ 'block_page_slug' ],
		] );
	}

	public function getParamsDef() :array {
		return [
			'block_page_slug' => [
				'type'  => 'string',
				'label' => __( 'Block page slug', 'wp-simple-firewall' ),
			],
			'hook'            => [
				'type'    => 'string',
				'label'   => __( 'Hook to attach to', 'wp-simple-firewall' ),
			],
			'hook_priority'   => [
				'type'    => 'int',
				'default' => 10,
				'label'   => __( 'Hook priority', 'wp-simple-firewall' ),
			],
		];
	}
}