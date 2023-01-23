<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
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
		$con = $this->getCon();
		try {
			( new IPRecords() )
				->setMod( $con->getModule_Data() )
				->loadIP( $ip->toString(), false );
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
							'icon'       => $con->svgs->raw( 'bootstrap/diagram-2-fill.svg' ),
						],
					],
				]
			];
		}
		catch ( \Exception $e ) {
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

		$optionGroups = array_merge(
			$this->getToolsSearch(),
			$this->getIntegrationsSearch(),
			$this->getExternalSearch(),
			$this->getConfigSearch()
		);

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

	private function getExternalSearch() :array {
		$con = $this->getCon();
		return [
			[
				'text'     => __( 'External Links', 'wp-simple-firewall' ),
				'children' => [
					[
						'id'         => 'external_helpdesk',
						'text'       => __( 'Helpdesk and Knowledge Base', 'wp-simple-firewall' ),
						'href'       => $con->labels->url_helpdesk,
						'new_window' => true,
						'tokens'     => 'help docs helpdesk support knowledge base doc',
						'icon'       => $con->svgs->raw( 'bootstrap/life-preserver.svg' ),
					],
					[
						'id'         => 'external_getshieldhome',
						'text'       => __( 'Shield Security Home Page', 'wp-simple-firewall' ),
						'href'       => 'https://getshieldsecurity.com',
						'new_window' => true,
						'tokens'     => 'shield security homepage home website site',
						'icon'       => $con->svgs->raw( 'bootstrap/house-fill.svg' ),
					],
					[
						'id'         => 'external_gopro',
						'text'       => __( 'Get ShieldPRO!', 'wp-simple-firewall' ),
						'href'       => 'https://getshieldsecurity.com/pricing/',
						'new_window' => true,
						'tokens'     => 'security pro premium security upgrade',
						'icon'       => $con->svgs->raw( 'bootstrap/box-arrow-up-right.svg' ),
					],
					[
						'id'         => 'external_trial',
						'text'       => __( 'ShieldPRO Free Trial', 'wp-simple-firewall' ),
						'href'       => 'https://getshieldsecurity.com/free-trial/',
						'new_window' => true,
						'tokens'     => 'security pro premium free trial',
						'icon'       => $con->svgs->raw( 'bootstrap/box-arrow-up-right.svg' ),
					],
					[
						'id'         => 'external_review',
						'text'       => __( 'Leave A Review', 'wp-simple-firewall' ),
						'href'       => 'https://shsec.io/l1',
						'new_window' => true,
						'tokens'     => 'review reviews stars',
						'icon'       => $con->svgs->raw( 'bootstrap/pencil-square.svg' ),
					],
					[
						'id'         => 'external_testimonials',
						'text'       => __( 'Read Customer Testimonials', 'wp-simple-firewall' ),
						'href'       => 'https://shsec.io/l2',
						'new_window' => true,
						'tokens'     => 'review reviews testimonial testimonials',
						'icon'       => $con->svgs->raw( 'bootstrap/book-half.svg' ),
					],
					[
						'id'         => 'external_crowdsec',
						'text'       => __( 'CrowdSec Home', 'wp-simple-firewall' ),
						'href'       => 'https://crowdsec.net/',
						'new_window' => true,
						'tokens'     => 'crowdsec',
						'icon'       => $con->svgs->raw( 'bootstrap/box-arrow-up-right.svg' ),
					],
				],
			]
		];
	}

	private function getToolsSearch() :array {
		$con = $this->getCon();
		$pageURLs = $con->plugin_urls;
		return [
			[
				'text'     => __( 'Security Tools', 'wp-simple-firewall' ),
				'children' => [
					[
						'id'     => 'tool_ip_manager',
						'text'   => __( 'Manage IP Rules', 'wp-simple-firewall' ),
						'href'   => $pageURLs->adminTop( PluginURLs::NAV_IP_RULES ),
						'tokens' => 'tool ips ip address analyse analysis rules rule manager block black white list lists bypass crowdsec table',
						'icon'   => $con->svgs->raw( 'bootstrap/diagram-3-fill.svg' ),
					],
					[
						'id'     => 'tool_scan_run',
						'text'   => __( 'Run A File Scan', 'wp-simple-firewall' ),
						'href'   => $pageURLs->adminTop( PluginURLs::NAV_SCANS_RUN ),
						'tokens' => 'tool scan scans run file files modified hacked missing core wordpress plugins themes malware',
						'icon'   => $con->svgs->raw( 'bootstrap/shield-shaded.svg' ),
					],
					[
						'id'     => 'tool_scan_results',
						'text'   => __( 'View Scan Results', 'wp-simple-firewall' ),
						'href'   => $pageURLs->adminTop( PluginURLs::NAV_SCANS_RESULTS ),
						'tokens' => 'tool filelocker locker wp-config scans scan results files file modified hacked missing core wordpress plugins themes malware guard repair ignore',
						'icon'   => $con->svgs->raw( 'bootstrap/shield-fill.svg' ),
					],
					[
						'id'     => 'tool_activity_log',
						'text'   => __( 'View User Activity Log', 'wp-simple-firewall' ),
						'href'   => $pageURLs->adminTop( PluginURLs::NAV_ACTIVITY_LOG ),
						'tokens' => 'tool audit trail activity log table traffic request requests bots review',
						'icon'   => $con->svgs->raw( 'bootstrap/person-lines-fill.svg' ),
					],
					[
						'id'     => 'tool_traffic_log',
						'text'   => __( 'View Traffic and Request Log', 'wp-simple-firewall' ),
						'href'   => $pageURLs->adminTop( PluginURLs::NAV_TRAFFIC_VIEWER ),
						'tokens' => 'tool activity log table traffic request requests bots review',
						'icon'   => $con->svgs->raw( 'bootstrap/stoplights.svg' ),
					],
					[
						'id'     => 'tool_sessions',
						'text'   => __( 'View User Sessions', 'wp-simple-firewall' ),
						'href'   => $pageURLs->adminTop( PluginURLs::NAV_USER_SESSIONS ),
						'tokens' => 'tool user users session sessions expire discard logout',
						'icon'   => $con->svgs->raw( 'bootstrap/person-badge.svg' ),
					],
					[
						'id'     => 'tool_license',
						'text'   => __( 'Activate ShieldPRO License', 'wp-simple-firewall' ),
						'href'   => $pageURLs->adminTop( PluginURLs::NAV_LICENSE ),
						'tokens' => 'tool pro license shieldpro upgrade buy purchase pricing',
						'icon'   => $con->svgs->raw( 'bootstrap/award.svg' ),
					],
					[
						'id'     => 'tool_notes',
						'text'   => __( 'Review Admin Notes', 'wp-simple-firewall' ),
						'href'   => $pageURLs->adminTop( PluginURLs::NAV_NOTES ),
						'tokens' => 'tool admin notes note',
						'icon'   => $con->svgs->raw( 'bootstrap/pencil-square.svg' ),
					],
					[
						'id'     => 'tool_importexport',
						'text'   => __( 'Import / Export Settings', 'wp-simple-firewall' ),
						'href'   => $pageURLs->adminTop( PluginURLs::NAV_IMPORT_EXPORT ),
						'tokens' => 'tool sync import export transfer download settings configuration options slave master network',
						'icon'   => $con->svgs->raw( 'bootstrap/arrows-expand.svg' ),
					],
					[
						'id'     => 'tool_overview',
						'text'   => __( 'My Security Overview', 'wp-simple-firewall' ),
						'href'   => $pageURLs->adminTop( PluginURLs::NAV_OVERVIEW ),
						'tokens' => 'tool overview grade grading charts performance dashboard summary',
						'icon'   => $con->svgs->raw( 'bootstrap/speedometer.svg' ),
					],
					[
						'id'     => 'tool_guidedsetup',
						'text'   => __( 'Run Guided Setup Wizard', 'wp-simple-firewall' ),
						'href'   => $pageURLs->adminTop( PluginURLs::NAV_WIZARD ),
						'tokens' => 'tool setup guide guided wizard',
						'icon'   => $con->svgs->raw( 'bootstrap/magic.svg' ),
					],
					[
						'id'     => 'tool_debug',
						'text'   => __( 'View Debug Info', 'wp-simple-firewall' ),
						'href'   => $pageURLs->adminTop( PluginURLs::NAV_DEBUG ),
						'tokens' => 'tool debug info help',
						'icon'   => $con->svgs->raw( 'bootstrap/tools.svg' ),
					],
				],
			]
		];
	}

	private function getIntegrationsSearch() :array {
		$con = $this->getCon();
		$modIntegrations = $con->getModule_Integrations();

		$integrations = [
			[
				'id'     => 'integration_mainwp',
				'text'   => 'Integration with MainWP',
				'href'   => $con->plugin_urls->modCfgOption( 'enable_mainwp' ),
				'tokens' => 'integration main mainwp',
				'icon'   => $con->svgs->raw( 'bootstrap/sliders.svg' ),
			]
		];

		foreach (
			$modIntegrations->getOptions()->getOptDefinition( 'user_form_providers' )[ 'value_options' ] as $item
		) {
			$integrations[] = [
				'id'     => 'integration_'.$item[ 'value_key' ],
				'text'   => sprintf( 'Integration with %s', $item[ 'text' ] ),
				'href'   => $con->plugin_urls->modCfgOption( 'user_form_providers' ),
				'tokens' => 'integration login form '.$item[ 'text' ],
				'icon'   => $con->svgs->raw( 'bootstrap/sliders.svg' ),
			];
		}

		foreach (
			$modIntegrations->getOptions()->getOptDefinition( 'form_spam_providers' )[ 'value_options' ] as $item
		) {
			$integrations[] = [
				'id'     => 'integration_'.$item[ 'value_key' ],
				'text'   => sprintf( 'Integration with %s', $item[ 'text' ] ),
				'href'   => $con->plugin_urls->modCfgOption( 'form_spam_providers' ),
				'tokens' => 'contact integration form forms '.$item[ 'text' ],
				'icon'   => $con->svgs->raw( 'bootstrap/sliders.svg' ),
			];
		}

		return [
			[
				'text'     => __( '3rd Party Integrations', 'wp-simple-firewall' ),
				'children' => $integrations
			]
		];
	}

	private function getConfigSearch() :array {
		$con = $this->getCon();

		$search = [];
		foreach ( $con->modules as $module ) {
			if ( $module->cfg->properties[ 'show_module_options' ] ) {
				$config = [];
				foreach ( $module->getOptions()->getVisibleOptionsKeys() as $optKey ) {
					try {
						$config[] = [
							'id'     => 'config_'.$optKey,
							'text'   => $module->getStrings()->getOptionStrings( $optKey )[ 'name' ],
							'href'   => $con->plugin_urls->modCfgOption( $optKey ),
							'icon'   => $con->svgs->raw( 'bootstrap/sliders.svg' ),
							'tokens' => $this->getSearchableTextForModuleOption( $module, $optKey ),
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

	/**
	 * @param ModCon|mixed $mod
	 * @throws \Exception
	 */
	private function getSearchableTextForModuleOption( $mod, string $optKey ) :string {
		$modOpts = $mod->getOptions();
		$modStrings = $mod->getStrings();

		$strSection = $modStrings->getSectionStrings( $modOpts->getOptDefinition( $optKey )[ 'section' ] );
		$strOpts = $modStrings->getOptionStrings( $optKey );
		return implode( ' ',
			array_unique( array_filter(
				array_map( 'trim', explode( ' ', preg_replace( '#\(\):-#', ' ', strip_tags( implode( ' ', array_merge(
					[
						$strOpts[ 'name' ],
						$strOpts[ 'summary' ],
						( is_array( $strOpts[ 'description' ] ) ? implode( ' ', $strOpts[ 'description' ] ) : $strOpts[ 'description' ] ),
						$strSection[ 'title' ],
						$strSection[ 'title_short' ],
					],
					$strSection[ 'summary' ]
				) ) ) ) ) ),
				function ( $word ) {
					return strlen( $word ) > 2;
				}
			) )
		);
	}
}