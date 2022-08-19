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
							'icon'   => $this->getCon()->svgs->raw( 'bootstrap/diagram-2-fill.svg' ),
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
						'text'       => __( 'Helpdesk and Knowledge Base', 'wp-simple-firewall' ),
						'href'       => $this->getCon()->labels->url_helpdesk,
						'new_window' => true,
						'tokens'     => 'help docs helpdesk support knowledge base doc',
						'icon'       => $this->getCon()->svgs->raw( 'bootstrap/life-preserver.svg' ),
					],
					[
						'id'         => 'external_getshieldhome',
						'text'       => __( 'Shield Security Home Page', 'wp-simple-firewall' ),
						'href'       => 'https://getshieldsecurity.com',
						'new_window' => true,
						'tokens'     => 'shield security homepage home website site',
						'icon'       => $this->getCon()->svgs->raw( 'bootstrap/house-fill.svg' ),
					],
					[
						'id'         => 'external_gopro',
						'text'       => __( 'Get ShieldPRO!', 'wp-simple-firewall' ),
						'href'       => 'https://getshieldsecurity.com/pricing/',
						'new_window' => true,
						'tokens'     => 'security pro premium security upgrade',
						'icon'       => $this->getCon()->svgs->raw( 'bootstrap/box-arrow-up-right.svg' ),
					],
					[
						'id'         => 'external_trial',
						'text'       => __( 'ShieldPRO Free Trial', 'wp-simple-firewall' ),
						'href'       => 'https://getshieldsecurity.com/free-trial/',
						'new_window' => true,
						'tokens'     => 'security pro premium free trial',
						'icon'       => $this->getCon()->svgs->raw( 'bootstrap/box-arrow-up-right.svg' ),
					],
				],
			]
		];
	}

	private function getToolsSearch() :array {
		$modInsights = $this->getCon()->getModule_Insights();
		return [
			[
				'text'     => __( 'Security Tools', 'wp-simple-firewall' ),
				'children' => [
					[
						'id'     => 'tool_ip_manager',
						'text'   => __( 'Manage IP Rules', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_IPs(),
						'tokens' => 'tool ips ip address analyse analysis rules rule manager block black white list lists bypass crowdsec table',
						'icon'   => $this->getCon()->svgs->raw( 'bootstrap/diagram-3-fill.svg' ),
					],
					[
						'id'     => 'tool_scan_run',
						'text'   => __( 'Run A File Scan', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_ScansResults(),
						'tokens' => 'tool scan scans run file files modified hacked missing core wordpress plugins themes malware',
						'icon'   => $this->getCon()->svgs->raw( 'bootstrap/shield-shaded.svg' ),
					],
					[
						'id'     => 'tool_scan_results',
						'text'   => __( 'View Scan Results', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_ScansResults(),
						'tokens' => 'tool filelocker locker wp-config scans scan results files file modified hacked missing core wordpress plugins themes malware guard repair ignore',
						'icon'   => $this->getCon()->svgs->raw( 'bootstrap/shield-fill.svg' ),
					],
					[
						'id'     => 'tool_activity_log',
						'text'   => __( 'View User Activity Log', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_ActivityLog(),
						'tokens' => 'tool audit trail activity log table traffic requests bots review',
						'icon'   => $this->getCon()->svgs->raw( 'bootstrap/person-lines-fill.svg' ),
					],
					[
						'id'     => 'tool_traffic_log',
						'text'   => __( 'View Traffic and Request Log', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_ActivityLog(),
						'tokens' => 'tool activity log table traffic requests bots review',
						'icon'   => $this->getCon()->svgs->raw( 'bootstrap/stoplights.svg' ),
					],
					[
						'id'     => 'tool_sessions',
						'text'   => __( 'View User Sessions', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_Sessions(),
						'tokens' => 'tool user users session sessions expire discard logout',
						'icon'   => $this->getCon()->svgs->raw( 'bootstrap/person-badge.svg' ),
					],
					[
						'id'     => 'tool_license',
						'text'   => __( 'Activate ShieldPRO License', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_Sessions(),
						'tokens' => 'tool pro license shieldpro upgrade buy purchase pricing',
						'icon'   => $this->getCon()->svgs->raw( 'bootstrap/award.svg' ),
					],
					[
						'id'     => 'tool_notes',
						'text'   => __( 'Review Admin Notes', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_SubInsightsPage( 'notes' ),
						'tokens' => 'tool admin notes note',
						'icon'   => $this->getCon()->svgs->raw( 'bootstrap/pencil-square.svg' ),
					],
					[
						'id'     => 'tool_importexport',
						'text'   => __( 'Import / Export Settings', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_SubInsightsPage( 'importexport' ),
						'tokens' => 'tool sync import export transfer download settings configuration options slave master network',
						'icon'   => $this->getCon()->svgs->raw( 'bootstrap/arrows-expand.svg' ),
					],
					[
						'id'     => 'tool_overview',
						'text'   => __( 'My Security Overview', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_SubInsightsPage( 'overview' ),
						'tokens' => 'tool overview grade grading charts performance dashboard summary',
						'icon'   => $this->getCon()->svgs->raw( 'bootstrap/speedometer.svg' ),
					],
					[
						'id'     => 'tool_debug',
						'text'   => __( 'View Debug Info', 'wp-simple-firewall' ),
						'href'   => $modInsights->getUrl_SubInsightsPage( 'debug' ),
						'tokens' => 'tool debug info help',
						'icon'   => $this->getCon()->svgs->raw( 'bootstrap/tools.svg' ),
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
							'icon'   => $this->getCon()->svgs->raw( 'bootstrap/sliders.svg' ),
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