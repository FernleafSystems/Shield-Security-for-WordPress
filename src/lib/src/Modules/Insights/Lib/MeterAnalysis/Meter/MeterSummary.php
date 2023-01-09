<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Component;

class MeterSummary extends MeterBase {

	public const SLUG = 'summary';

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
		return __( 'High-Level Site Security Summary', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'How WordPress security is looking overall', 'wp-simple-firewall' );
	}

	public function description() :array {
		return [
			__( "There are many aspects that affect the security of a WordPress website.", 'wp-simple-firewall' ),
			__( "This section assesses your security from an high-level perspective so you can see, at a glance, how you're progressing.", 'wp-simple-firewall' )
			.' '.__( "It uses a simple grading system from A - F, where A is best, and F is worst.", 'wp-simple-firewall' ),
			__( "Your overall grade in this section incorporates all other security scores.", 'wp-simple-firewall' )
			.' '.__( "Use the 'Analysis' buttons in each section to review the areas that might need improvement.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponents() :array {
		$components = [
			Component\AllComponents::class,
			Component\SystemSslCertificate::class,
			( function_exists( '\curl_version' ) && in_array( 'openssl', get_loaded_extensions() ) ) ? Component\SystemLibOpenssl::class : '',
			Component\WpDbPassword::class,
			Component\ActivityLogEnabled::class,
			Component\TrafficLogEnabled::class,
			Component\PluginReportEmail::class,
		];
		if ( !$this->getCon()->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() ) {
			$components[] = Component\ShieldPro::class;
		}
		return $components;
	}
}