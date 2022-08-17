<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class SelectSearchData {

	use PluginControllerConsumer;

	/**
	 * Note use of array_values() throughout. This is required by Select2 when it receives the data.
	 * All arrays must have simple numeric keys starting from 0.
	 */
	public function build( string $search ) :array {
		// Terms must all be at least 3 characters.
		$terms = array_filter( array_unique( array_map(
			function ( $term ) {
				$term = trim( $term );
				return strlen( $term ) > 2 ? $term : '';
			},
			explode( ' ', $search )
		) ) );

		$optionGroups = array_merge(
			$this->getToolsSearch(),
			$this->getConfigSearch()
		);

		foreach ( $optionGroups as $optGroupKey => $optionGroup ) {
			foreach ( $optionGroup[ 'children' ] as $optKey => $option ) {

				$found = false;
				foreach ( $terms as $term ) {
					if ( stripos( $option[ 'tokens' ].' '.$optionGroup[ 'text' ], $term ) !== false ) {
						$found = true;
						break;
					}
				}

				if ( $found ) {
					// Remove unnecessary 'tokens' from data sent back to select2
					unset( $optionGroups[ $optGroupKey ][ 'children' ][ $optKey ][ 'tokens' ] );
				}
				else {
					unset( $optionGroups[ $optGroupKey ][ 'children' ][ $optKey ] );
				}
			}

			// Don't include empty OptGroups
			if ( empty( $optionGroups[ $optGroupKey ][ 'children' ] ) ) {
				unset( $optionGroups[ $optGroupKey ] );
			}
			else {
				$optionGroups[ $optGroupKey ][ 'children' ] = array_values( $optionGroups[ $optGroupKey ][ 'children' ] );
			}
		}

		return array_values( $optionGroups );
	}

	private function getToolsSearch() :array {
		$modInsights = $this->getCon()->getModule_Insights();
		return [
			[
				'text'     => __( 'Tools', 'wp-simple-firewall' ),
				'children' => [
					[
						'id'     => 'tool_ip_manager',
						'text'   => __( 'IP Rules Manager', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_IPs(),
						'tokens' => 'ips manager block black white list bypass crowdsec table'
					],
					[
						'id'     => 'tool_scan_results',
						'text'   => __( 'Scan Results', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_ScansResults(),
						'tokens' => 'filelocker wp-config scans results files modified hacked missing core wordpress plugins themes malware guard repair ignore'
					],
					[
						'id'     => 'tool_scan_run',
						'text'   => __( 'Scan Results', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_ScansResults(),
						'tokens' => 'scans run files modified hacked missing core wordpress plugins themes malware'
					],
					[
						'id'     => 'tool_activity_log',
						'text'   => __( 'Activity Log', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_ActivityLog(),
						'tokens' => 'audit trail activity log table traffic requests bots review'
					],
					[
						'id'     => 'tool_traffic_log',
						'text'   => __( 'Traffic Log', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_ActivityLog(),
						'tokens' => 'activity log table traffic requests bots review'
					],
					[
						'id'     => 'tool_sessions',
						'text'   => __( 'User Sessions', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_Sessions(),
						'tokens' => 'users sessions expire discard logout'
					],
					[
						'id'     => 'tool_license',
						'text'   => __( 'ShieldPRO', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_Sessions(),
						'tokens' => 'pro license shieldpro upgrade buy purchase pricing'
					],
					[
						'id'     => 'tool_notes',
						'text'   => __( 'Admin Notes', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_SubInsightsPage( 'notes' ),
						'tokens' => 'admin notes'
					],
					[
						'id'     => 'tool_importexport',
						'text'   => __( 'Import / Export', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_SubInsightsPage( 'importexport' ),
						'tokens' => 'import export transfer download settings configuration options slave master network'
					],
					[
						'id'     => 'tool_overview',
						'text'   => __( 'Overview', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_SubInsightsPage( 'overview' ),
						'tokens' => 'overview grade grading charts performance dashboard summary'
					],
					[
						'id'     => 'tool_debug',
						'text'   => __( 'Debug Info', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_SubInsightsPage( 'debug' ),
						'tokens' => 'debug info help'
					],
				],
			]
		];
	}

	private function getConfigSearch() :array {
		$search = [];
		foreach ( $this->getCon()->modules as $module ) {
			$cfg = $module->cfg;
			if ( $cfg->properties[ 'show_module_options' ] ) {
				$config = [];
				foreach ( $module->getOptions()->getVisibleOptionsKeys() as $optKey ) {
					try {
						$st = $module->getStrings()->getOptionStrings( $optKey );
						$description = strip_tags( is_array( $st[ 'description' ] ) ? implode( ' ', $st[ 'description' ] ) : $st[ 'description' ] );
						$config[] = [
							'id'     => 'config_'.$optKey,
							'text'   => $st[ 'name' ],
							'href'   => $module->getUrl_DirectLinkToOption( $optKey ),
							'tokens' => implode( ' ',
								array_filter(
									array_map( 'trim', explode( ' ', $description.' '.$st[ 'summary' ].' '.$st[ 'name' ] ) ),
									function ( $word ) {
										return strlen( $word ) > 3;
									}
								)
							),
						];
					}
					catch ( \Exception $e ) {
					}
				}

				if ( !empty( $config ) ) {
					$search[] = [
						'text'     => sprintf( '%s: %s', __( 'Config', 'wp-simple-firewall' ), $module->getMainFeatureName() ),
						'children' => $config
					];
				}
			}
		}
		return $search;
	}
}