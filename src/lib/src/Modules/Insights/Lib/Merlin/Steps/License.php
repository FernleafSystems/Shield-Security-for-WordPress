<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class License extends Base {

	const SLUG = 'license';

	public function processStepFormSubmit( array $form ) :Shield\Utilities\Response {
		$resp = parent::processStepFormSubmit( $form );
		$resp->success = $this->getCon()
							  ->getModule_License()
							  ->getLicenseHandler()
							  ->verify( true )
							  ->hasValidWorkingLicense();
		if ( $resp->success ) {
			$resp->msg = 'License found and installed successfully';
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
		return $this->getCon()->isPremiumActive();
	}
}