<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterIntegrity extends MeterBase {

	public const SLUG = 'integrity';

	protected function getWorkingMods() :array {
		return array_filter(
			$this->getCon()->modules,
			function ( $mod ) {
				return ( $mod->cfg->properties[ 'show_module_options' ] ?? false )
					   && $mod->getSlug() !== 'plugin';
			}
		);
	}

	public function title() :string {
		return __( 'Overall Site Security Integrity', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'How WordPress security protection is looking overall', 'wp-simple-firewall' );
	}

	protected function description() :array {
		return [
			__( "There are many aspects and considerations that affect the security of a WordPress website.", 'wp-simple-firewall' ),
			__( "This section assesses your security from an high-level perspective so you can see, at a glance, how you're progressing.", 'wp-simple-firewall' )
			.' '.__( "It uses a simple grading system from A - F, where A is best, and F is worst.", 'wp-simple-firewall' ),
			__( "Your overall grade in this section incorporates all other security scores.", 'wp-simple-firewall' )
			.' '.__( "Use the 'Analysis' buttons in each section to review the areas that might need improvement.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponentSlugs() :array {
		$components = [
			'all',
			'ssl_certificate',
			'db_password',
			'activity_log_enabled',
			'traffic_log_enabled',
			'report_email',
		];
		if ( !$this->getCon()->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() ) {
			$components[] = 'shieldpro';
		}
		return $components;
	}
}