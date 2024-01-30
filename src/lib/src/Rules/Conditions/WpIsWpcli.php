<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;

class WpIsWpcli extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'wp_is_wpcli';

	protected function execConditionCheck() :bool {
		return $this->req->wp_is_wpcli;
	}

	public function getName() :string {
		return __( 'Is WP-CLI', 'wp-simple-firewall' );
	}

	public function getDescription() :string {
		return __( 'Is the request triggered by WP-CLI.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsPhpCli::class,
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}