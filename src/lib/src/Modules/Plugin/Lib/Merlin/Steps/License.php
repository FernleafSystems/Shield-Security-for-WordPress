<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class License extends Base {

	public const SLUG = 'license';

	public function processStepFormSubmit( array $form ) :Shield\Utilities\Response {
		$resp = parent::processStepFormSubmit( $form );
		if ( $resp->success = self::con()->comps->license->verify( true )->hasValidWorkingLicense() ) {
			$resp->message = 'License found and installed successfully';
		}
		else {
			$resp->error = "There doesn't appear to be a active ShieldPRO license available for this site.";
		}

		$resp->addData( 'page_reload', $resp->success );

		return $resp;
	}

	public function getName() :string {
		return 'ShieldPRO';
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( "Activate Your ShieldPRO License", 'wp-simple-firewall' ),
			],
		];
	}

	public function skipStep() :bool {
		return self::con()->isPremiumActive();
	}
}