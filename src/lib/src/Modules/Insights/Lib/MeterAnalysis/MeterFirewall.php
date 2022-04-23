<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall;

class MeterFirewall extends MeterBase {

	const SLUG = 'firewall';

	protected function title() :string {
		return __( 'Firewall', 'wp-simple-firewall' );
	}

	protected function getMeterRenderData() :array{
		return [];
	}

	protected function buildComponents() :array {
		$mod = $this->getCon()->getModule_Firewall();
		/** @var Firewall\Options $opts */
		$opts = $mod->getOptions();

		$components = [];
		foreach (
			[
				'dir_traversal',
				'wordpress_terms',
				'field_truncation',
				'php_code',
				'exe_file_uploads',
				'leading_schema',
				'aggressive'
			] as $opt
		) {
			$opt = 'block_'.$opt;
			$components[ $opt ] = [
				'protected' => $opts->isOpt( $opt, 'Y' ),
				'weight'    => 20,
			];
		}
		return $components;
	}
}