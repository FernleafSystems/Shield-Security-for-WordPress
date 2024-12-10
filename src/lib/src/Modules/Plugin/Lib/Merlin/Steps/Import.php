<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class Import extends Base {

	public const SLUG = 'import';

	public function processStepFormSubmit( array $form ) :Shield\Utilities\Response {
		throw new \Exception( 'not yet implemented.' );
	}

	public function getName() :string {
		return __( 'Import', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( 'Import Settings From Another Site', 'wp-simple-firewall' ),
			],
		];
	}

	public function skipStep() :bool {
		return !self::con()->isPremiumActive();
	}
}