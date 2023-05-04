<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Meter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class MeterSummary extends MeterBase {

	public const SLUG = 'summary';

	protected function getWorkingMods() :array {
		return array_filter(
			$this->con()->modules,
			function ( $mod ) {
				return ( $mod->cfg->properties[ 'show_module_options' ] ?? false ) && $mod->cfg->slug !== 'plugin';
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
			__( "This section takes a high-level perspective on assessing your overall security, so you can see at a glance how you're progressing.", 'wp-simple-firewall' ),
			__( "There are many aspects that affect the security of a WordPress website.", 'wp-simple-firewall' )
			.' '.__( "Your overall grade in this section incorporates all other security scores as well as some components not directly related to the plugin.", 'wp-simple-firewall' ),
			__( "All sections use a simple grading system from A - F, where A is best, and F is worst.", 'wp-simple-firewall' ),
			__( "Use the 'Analysis' button within each section to review how the score is reached and to quickly jump to the relevant plugin option.", 'wp-simple-firewall' ),
		];
	}

	protected function getComponents() :array {
		return [
			Component\AllComponents::class,
			Component\SystemSslCertificate::class,
			Component\SystemLibOpenssl::class,
			Component\WpDbPassword::class,
			Component\SecurityAdmin::class,
			Component\ActivityLogEnabled::class,
			Component\TrafficLogEnabled::class,
			Component\ShieldPro::class,
			Component\PluginReportEmail::class,
		];
	}
}