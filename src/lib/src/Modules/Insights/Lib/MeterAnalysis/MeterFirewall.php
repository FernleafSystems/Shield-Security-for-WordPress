<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis;

class MeterFirewall extends MeterBase {

	const SLUG = 'firewall';

	protected function title() :string {
		return __( 'Powerful WordPress Firewall', 'wp-simple-firewall' );
	}

	protected function subtitle() :string {
		return __( 'How malicious requests to your site are handled', 'wp-simple-firewall' );
	}

	protected function description() :array {
		return [
			__( "The firewall inspects all data sent in every request to your site.", 'wp-simple-firewall' )
			.' '.__( "If malicious data is detected, the request will be quickly terminated before it can be misused.", 'wp-simple-firewall' ),
			__( "The more rules you employ, the better, but you should always monitor your Activity Audit Trail for false positives.", 'wp-simple-firewall' ),
		];
	}

	protected function getMeterRenderData() :array {
		return [];
	}

	protected function getComponentSlugs() :array {
		return array_map(
			function ( string $key ) {
				return 'fwb_'.$key;
			},
			[
				'dir_traversal',
				'wordpress_terms',
				'field_truncation',
				'php_code',
				'exe_file_uploads',
				'leading_schema',
				'aggressive'
			]
		);
	}
}