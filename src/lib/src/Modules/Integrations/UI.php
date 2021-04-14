<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;

class UI extends Modules\BaseShield\UI {

	protected function getSectionWarnings( string $section ) :array {
		$warnings = [];

		/** @var Options $opts */
		$opts = $this->getOptions();
		/** @var Modules\LoginGuard\Options $loginGuardOpts */
		$loginGuardOpts = $this->getCon()->getModule_LoginGuard()->getOptions();

		switch ( $section ) {

			case 'section_user_forms':
				if ( !$loginGuardOpts->isEnabledAntiBot() ) {
					$warnings[] = sprintf( '%s: %s %s', __( 'Important', 'wp-simple-firewall' ),
						__( "Use of the AntiBot Detection Engine for user forms isn't turned on in the Login Guard module.", 'wp-simple-firewall' ),
						sprintf( '<a href="%s" target="_blank">%s</a>',
							$this->getCon()->getModule_LoginGuard()->getUrl_AdminPage(),
							__( 'Click here to review those settings.', 'wp-simple-firewall' ) )
					);
				}
				break;
		}

		return $warnings;
	}
}