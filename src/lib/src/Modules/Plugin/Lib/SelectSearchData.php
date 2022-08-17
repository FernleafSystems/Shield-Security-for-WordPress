<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use IPLib\Address\AddressInterface;
use IPLib\Factory;

class SelectSearchData {

	use PluginControllerConsumer;

	public function build( string $terms ) :array {
		$terms = strtolower( trim( $terms ) );

		$ip = Factory::parseAddressString( $terms );
		if ( is_null( $ip ) ) {
			$data = $this->textSearch( $terms );
		}
		else {
			$data = $this->ipSearch( $ip );
		}

		return $data;
	}

	/**
	 * Note use of array_values() throughout. This is required by Select2 when it receives the data.
	 * All arrays must have simple numeric keys starting from 0.
	 */
	protected function ipSearch( AddressInterface $ip ) :array {
		$ipStatus = ( new IpRuleStatus( $ip->toString() ) )->setMod( $this->getCon()->getModule_IPs() );
		if ( $ipStatus->hasRules() ) {
			$data = [
				[
					'text'     => __( 'IP Addresses', 'wp-simple-firewall' ),
					'children' => [
						[
							'id'         => 'ip_'.$ip->toString(),
							'text'       => $ip->toString(),
							'href'       => '',
							'ip'         => $ip->toString(),
							'new_window' => false,
						],
					],
				]
			];
		}
		else {
			$data = [];
		}
		return $data;
	}

	/**
	 * Note use of array_values() throughout. This is required by Select2 when it receives the data.
	 * All arrays must have simple numeric keys starting from 0.
	 */
	protected function textSearch( string $search ) :array {
		// Terms must all be at least 3 characters.
		$terms = array_filter( array_unique( array_map(
			function ( $term ) {
				$term = strtolower( trim( $term ) );
				return strlen( $term ) > 2 ? $term : '';
			},
			explode( ' ', $search )
		) ) );

		$optionGroups = $this->getAllSearchGroups();

		foreach ( $optionGroups as $optGroupKey => $optionGroup ) {
			foreach ( $optionGroup[ 'children' ] as $optKey => $option ) {

				$count = $this->searchString( $option[ 'tokens' ].' '.$optionGroup[ 'text' ], $terms );
				if ( $count > 0 ) {
					$optionGroups[ $optGroupKey ][ 'children' ][ $optKey ][ 'count' ] = $count;
					// Remove unnecessary 'tokens' from data sent back to select2
					unset( $optionGroups[ $optGroupKey ][ 'children' ][ $optKey ][ 'tokens' ] );

					$optionGroups[ $optGroupKey ][ 'children' ][ $optKey ] = array_merge( [
						'new_window' => false,
						'ip'         => false,
					], $optionGroups[ $optGroupKey ][ 'children' ][ $optKey ] );
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

	private function searchString( string $haystack, array $needles ) :int {
		return count( array_intersect( $needles, array_map( 'trim', explode( ' ', strtolower( $haystack ) ) ) ) );
	}

	private function getAllSearchGroups() :array {
		return array_merge(
			$this->getExternalSearch(),
			$this->getToolsSearch(),
			$this->getConfigSearch()
		);
	}

	private function getExternalSearch() :array {
		return [
			[
				'text'     => __( 'External Links', 'wp-simple-firewall' ),
				'children' => [
					[
						'id'         => 'external_helpdesk',
						'text'       => __( 'Helpdesk', 'wp-simple-firewall' ),
						'href'       => $this->getCon()->labels->url_helpdesk,
						'new_window' => true,
						'tokens'     => 'help docs helpdesk support'
					],
					[
						'id'         => 'external_getshieldhome',
						'text'       => __( 'Shield Security Home Page', 'wp-simple-firewall' ),
						'href'       => 'https://getshieldsecurity.com',
						'new_window' => true,
						'tokens'     => 'shield security homepage home website site'
					],
					[
						'id'         => 'external_gopro',
						'text'       => __( 'Get ShieldPRO!', 'wp-simple-firewall' ),
						'href'       => 'https://getshieldsecurity.com/pricing/',
						'new_window' => true,
						'tokens'     => 'security pro premium security upgrade'
					],
					[
						'id'         => 'external_trial',
						'text'       => __( 'ShieldPRO Free Trial', 'wp-simple-firewall' ),
						'href'       => 'https://getshieldsecurity.com/free-trial/',
						'new_window' => true,
						'tokens'     => 'security pro premium free trial'
					],
				],
			]
		];
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
						'tokens' => 'tool ips manager block black white list lists bypass crowdsec table'
					],
					[
						'id'     => 'tool_scan_results',
						'text'   => __( 'Scan Results', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_ScansResults(),
						'tokens' => 'tool filelocker locker wp-config scans scan results files file modified hacked missing core wordpress plugins themes malware guard repair ignore'
					],
					[
						'id'     => 'tool_scan_run',
						'text'   => __( 'Scan Results', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_ScansResults(),
						'tokens' => 'tool scan scans run file files modified hacked missing core wordpress plugins themes malware'
					],
					[
						'id'     => 'tool_activity_log',
						'text'   => __( 'Activity Log', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_ActivityLog(),
						'tokens' => 'tool audit trail activity log table traffic requests bots review'
					],
					[
						'id'     => 'tool_traffic_log',
						'text'   => __( 'Traffic Log', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_ActivityLog(),
						'tokens' => 'tool activity log table traffic requests bots review'
					],
					[
						'id'     => 'tool_sessions',
						'text'   => __( 'User Sessions', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_Sessions(),
						'tokens' => 'tool user users session sessions expire discard logout'
					],
					[
						'id'     => 'tool_license',
						'text'   => __( 'ShieldPRO', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_Sessions(),
						'tokens' => 'tool pro license shieldpro upgrade buy purchase pricing'
					],
					[
						'id'     => 'tool_notes',
						'text'   => __( 'Admin Notes', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_SubInsightsPage( 'notes' ),
						'tokens' => 'tool admin notes note'
					],
					[
						'id'     => 'tool_importexport',
						'text'   => __( 'Import / Export', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_SubInsightsPage( 'importexport' ),
						'tokens' => 'tool sync import export transfer download settings configuration options slave master network'
					],
					[
						'id'     => 'tool_overview',
						'text'   => __( 'Overview', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_SubInsightsPage( 'overview' ),
						'tokens' => 'tool overview grade grading charts performance dashboard summary'
					],
					[
						'id'     => 'tool_debug',
						'text'   => __( 'Debug Info', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_SubInsightsPage( 'debug' ),
						'tokens' => 'tool debug info help'
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