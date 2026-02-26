<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

class PageInvestigateLanding extends PageModeLandingBase {

	use InvestigateAssetOptionsBuilder;

	public const SLUG = 'plugin_admin_page_investigate_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_landing.twig';

	private const ACTIVE_SUBJECT_INPUT_PRECEDENCE = [ 'plugin_slug', 'theme_slug', 'analyse_ip', 'user_lookup' ];

	private const SUBJECT_DEFINITIONS = [
		'users'     => [
			'key'         => 'users',
			'subnav_hint' => PluginNavs::SUBNAV_ACTIVITY_BY_USER,
			'input_key'   => 'user_lookup',
			'options_key' => null,
			'panel_type'  => 'lookup_text',
			'href_key'    => 'by_user',
			'string_keys' => [
				'subject' => 'subject_users',
				'panel'   => 'panel_users',
				'lookup'  => 'lookup_user',
				'go'      => 'go_user',
			],
		],
		'ips'       => [
			'key'         => 'ips',
			'subnav_hint' => PluginNavs::SUBNAV_ACTIVITY_BY_IP,
			'input_key'   => 'analyse_ip',
			'options_key' => null,
			'panel_type'  => 'lookup_text',
			'href_key'    => 'by_ip',
			'string_keys' => [
				'subject' => 'subject_ips',
				'panel'   => 'panel_ips',
				'lookup'  => 'lookup_ip',
				'go'      => 'go_ip',
			],
		],
		'plugins'   => [
			'key'         => 'plugins',
			'subnav_hint' => PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN,
			'input_key'   => 'plugin_slug',
			'options_key' => 'plugin_options',
			'panel_type'  => 'lookup_select',
			'href_key'    => 'by_plugin',
			'string_keys' => [
				'subject' => 'subject_plugins',
				'panel'   => 'panel_plugins',
				'lookup'  => 'lookup_plugin',
				'go'      => 'go_plugin',
			],
		],
		'themes'    => [
			'key'         => 'themes',
			'subnav_hint' => PluginNavs::SUBNAV_ACTIVITY_BY_THEME,
			'input_key'   => 'theme_slug',
			'options_key' => 'theme_options',
			'panel_type'  => 'lookup_select',
			'href_key'    => 'by_theme',
			'string_keys' => [
				'subject' => 'subject_themes',
				'panel'   => 'panel_themes',
				'lookup'  => 'lookup_theme',
				'go'      => 'go_theme',
			],
		],
		'wordpress' => [
			'key'         => 'wordpress',
			'subnav_hint' => PluginNavs::SUBNAV_ACTIVITY_BY_CORE,
			'input_key'   => null,
			'options_key' => null,
			'panel_type'  => 'direct_link',
			'href_key'    => 'by_core',
			'string_keys' => [
				'subject' => 'subject_wordpress',
				'panel'   => 'panel_wordpress',
				'go'      => 'go_wordpress',
			],
		],
		'requests'  => [
			'key'         => 'requests',
			'subnav_hint' => null,
			'input_key'   => null,
			'options_key' => null,
			'panel_type'  => 'direct_link',
			'href_key'    => 'traffic_log',
			'string_keys' => [
				'subject' => 'subject_requests',
				'panel'   => 'panel_requests',
				'go'      => 'go_requests',
			],
		],
		'activity'  => [
			'key'         => 'activity',
			'subnav_hint' => null,
			'input_key'   => null,
			'options_key' => null,
			'panel_type'  => 'direct_link',
			'href_key'    => 'activity_log',
			'string_keys' => [
				'subject' => 'subject_activity',
				'panel'   => 'panel_activity',
				'go'      => 'go_activity',
			],
		],
	];

	private ?array $inputValuesCache = null;
	private ?array $optionsCache = null;
	private ?array $subjectBySubnavHintMapCache = null;
	private ?array $subjectByInputKeyMapCache = null;

	protected function getLandingTitle() :string {
		return __( 'Investigate', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Investigate user activity, request logs, and IP behavior.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'search';
	}

	protected function getLandingFlags() :array {
		$lookup = $this->getByIpLookup();
		return [
			'has_ip_lookup' => $lookup[ 'has_lookup' ],
			'ip_is_valid'   => $lookup[ 'is_valid' ],
		];
	}

	protected function getLandingHrefs() :array {
		$con = self::con();
		return [
			'activity_log' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_ACTIVITY, PluginNavs::SUBNAV_LOGS ),
			'traffic_log'  => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LOGS ),
			'live_traffic' => $con->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE ),
			'ip_rules'     => $con->plugin_urls->adminTopNav( PluginNavs::NAV_IPS, PluginNavs::SUBNAV_IPS_RULES ),
			'by_user'      => $con->plugin_urls->investigateByUser(),
			'by_ip'        => $con->plugin_urls->investigateByIp(),
			'by_plugin'    => $con->plugin_urls->investigateByPlugin(),
			'by_theme'     => $con->plugin_urls->investigateByTheme(),
			'by_core'      => $con->plugin_urls->investigateByCore(),
		];
	}

	protected function getLandingStrings() :array {
		return [
			'selector_title'      => __( 'What Do You Want To Investigate?', 'wp-simple-firewall' ),
			'quick_tools_title'   => __( 'Quick Access', 'wp-simple-firewall' ),
			'subject_users'       => __( 'Users', 'wp-simple-firewall' ),
			'subject_ips'         => __( 'IP Addresses', 'wp-simple-firewall' ),
			'subject_plugins'     => __( 'Plugins', 'wp-simple-firewall' ),
			'subject_themes'      => __( 'Themes', 'wp-simple-firewall' ),
			'subject_wordpress'   => __( 'WordPress Core', 'wp-simple-firewall' ),
			'subject_requests'    => __( 'HTTP Requests', 'wp-simple-firewall' ),
			'subject_activity'    => __( 'Activity Log', 'wp-simple-firewall' ),
			'tool_activity'       => __( 'WP Activity Log', 'wp-simple-firewall' ),
			'tool_traffic'        => __( 'HTTP Request Log', 'wp-simple-firewall' ),
			'tool_live'           => __( 'Live HTTP Log', 'wp-simple-firewall' ),
			'tool_ip_rules'       => __( 'IP Rules', 'wp-simple-firewall' ),
			'panel_users'         => __( 'Investigate A User', 'wp-simple-firewall' ),
			'panel_ips'           => __( 'Investigate An IP Address', 'wp-simple-firewall' ),
			'panel_plugins'       => __( 'Investigate A Plugin', 'wp-simple-firewall' ),
			'panel_themes'        => __( 'Investigate A Theme', 'wp-simple-firewall' ),
			'panel_wordpress'     => __( 'WordPress Core Integrity', 'wp-simple-firewall' ),
			'panel_requests'      => __( 'HTTP Request Log', 'wp-simple-firewall' ),
			'panel_activity'      => __( 'Activity Log', 'wp-simple-firewall' ),
			'lookup_user'         => __( 'User ID, username, or email', 'wp-simple-firewall' ),
			'lookup_ip'           => __( 'IP address', 'wp-simple-firewall' ),
			'lookup_plugin'       => __( 'Select a plugin', 'wp-simple-firewall' ),
			'lookup_theme'        => __( 'Select a theme', 'wp-simple-firewall' ),
			'go_user'             => __( 'Investigate User', 'wp-simple-firewall' ),
			'go_ip'               => __( 'Investigate IP', 'wp-simple-firewall' ),
			'go_plugin'           => __( 'Investigate Plugin', 'wp-simple-firewall' ),
			'go_theme'            => __( 'Investigate Theme', 'wp-simple-firewall' ),
			'go_wordpress'        => __( 'View Core Status', 'wp-simple-firewall' ),
			'go_requests'         => __( 'Open Request Log', 'wp-simple-firewall' ),
			'go_activity'         => __( 'Open Activity Log', 'wp-simple-firewall' ),
			'ip_invalid_text'     => __( 'Enter a valid IPv4 or IPv6 address to investigate this IP.', 'wp-simple-firewall' ),
		];
	}

	protected function getLandingVars() :array {
		$input = $this->getInputValues();
		$strings = $this->getLandingStrings();
		$hrefs = $this->getLandingHrefs();
		$activeSubject = $this->resolveActiveSubject();
		$pluginOptions = $this->getOptionsByKey( 'plugin_options' );
		$themeOptions = $this->getOptionsByKey( 'theme_options' );
		$subjects = [];
		foreach ( $this->getSubjectDefinitions() as $subject ) {
			$this->assertSubjectDefinitionContract( $subject, $hrefs, $strings );
			$stringKeys = $subject[ 'string_keys' ];
			$inputKey = $subject[ 'input_key' ];
			$optionsKey = $subject[ 'options_key' ];
			$isLookupPanel = \in_array( $subject[ 'panel_type' ], [ 'lookup_text', 'lookup_select' ], true );

			$subjects[] = [
				'key'                => $subject[ 'key' ],
				'tab_id'             => \sprintf( 'investigate-subject-%s', $subject[ 'key' ] ),
				'is_active'          => $activeSubject === $subject[ 'key' ],
				'panel_type'         => $subject[ 'panel_type' ],
				'href'               => $hrefs[ $subject[ 'href_key' ] ],
				'input_key'          => $inputKey,
				'input_value'        => $inputKey !== null ? ( $input[ $inputKey ] ?? '' ) : null,
				'options_key'        => $optionsKey,
				'options'            => $optionsKey !== null ? $this->getOptionsByKey( $optionsKey ) : [],
				'subject_label'      => $strings[ $stringKeys[ 'subject' ] ],
				'panel_title'        => $strings[ $stringKeys[ 'panel' ] ],
				'lookup_placeholder' => isset( $stringKeys[ 'lookup' ] ) ? $strings[ $stringKeys[ 'lookup' ] ] : null,
				'go_label'           => $strings[ $stringKeys[ 'go' ] ],
				'lookup_route'       => $isLookupPanel
					? $this->buildLookupRouteContract( (string)$subject[ 'subnav_hint' ] )
					: [],
			];
		}

		return [
			'active_subject' => $activeSubject,
			'input'          => $input,
			'plugin_options' => $pluginOptions,
			'theme_options'  => $themeOptions,
			'subjects'       => $subjects,
		];
	}

	private function normalizeSubjectKey( string $key ) :string {
		$key = \strtolower( \trim( $key ) );
		return isset( $this->getSubjectDefinitions()[ $key ] ) ? $key : '';
	}

	private function resolveActiveSubject() :string {
		$explicit = $this->normalizeSubjectKey(
			$this->getTextInputFromRequestOrActionData( 'subject' )
		);
		if ( $explicit !== '' ) {
			return $explicit;
		}

		$subNav = PluginNavs::GetSubNav();
		$subjectBySubnavHintMap = $this->getSubjectBySubnavHintMap();
		if ( isset( $subjectBySubnavHintMap[ $subNav ] ) ) {
			return $subjectBySubnavHintMap[ $subNav ];
		}

		$input = $this->getInputValues();
		$subjectByInputKeyMap = $this->getSubjectByInputKeyMap();
		foreach ( self::ACTIVE_SUBJECT_INPUT_PRECEDENCE as $inputKey ) {
			if ( !empty( $input[ $inputKey ] ) && isset( $subjectByInputKeyMap[ $inputKey ] ) ) {
				return $subjectByInputKeyMap[ $inputKey ];
			}
		}

		return 'users';
	}

	private function getInputValues() :array {
		if ( $this->inputValuesCache === null ) {
			$this->inputValuesCache = [
				'user_lookup' => $this->getTextInputFromRequestOrActionData( 'user_lookup' ),
				'analyse_ip'  => $this->getTextInputFromRequestOrActionData( 'analyse_ip' ),
				'plugin_slug' => $this->getTextInputFromRequestOrActionData( 'plugin_slug' ),
				'theme_slug'  => $this->getTextInputFromRequestOrActionData( 'theme_slug' ),
			];
		}
		return $this->inputValuesCache;
	}

	private function buildPluginOptions() :array {
		return $this->buildAssetOptions(
			Services::WpPlugins()->getPluginsAsVo(),
			'file'
		);
	}

	private function buildThemeOptions() :array {
		return $this->buildAssetOptions(
			Services::WpThemes()->getThemesAsVo(),
			'stylesheet'
		);
	}

	private function getOptionsByKey( string $optionsKey ) :array {
		if ( $this->optionsCache === null ) {
			$this->optionsCache = [];
		}
		if ( !isset( $this->optionsCache[ $optionsKey ] ) ) {
			switch ( $optionsKey ) {
				case 'plugin_options':
					$this->optionsCache[ $optionsKey ] = $this->buildPluginOptions();
					break;
				case 'theme_options':
					$this->optionsCache[ $optionsKey ] = $this->buildThemeOptions();
					break;
				default:
					throw new \LogicException(
						\sprintf( 'Unknown investigate subject options key "%s".', $optionsKey )
					);
			}
		}
		return $this->optionsCache[ $optionsKey ];
	}

	protected function getSubjectDefinitions() :array {
		return self::SUBJECT_DEFINITIONS;
	}

	private function getSubjectBySubnavHintMap() :array {
		if ( $this->subjectBySubnavHintMapCache === null ) {
			$this->subjectBySubnavHintMapCache = [];
			foreach ( $this->getSubjectDefinitions() as $subjectKey => $subject ) {
				$subnavHint = $subject[ 'subnav_hint' ];
				if ( !empty( $subnavHint ) ) {
					$this->subjectBySubnavHintMapCache[ $subnavHint ] = $subjectKey;
				}
			}
		}
		return $this->subjectBySubnavHintMapCache;
	}

	private function getSubjectByInputKeyMap() :array {
		if ( $this->subjectByInputKeyMapCache === null ) {
			$this->subjectByInputKeyMapCache = [];
			foreach ( $this->getSubjectDefinitions() as $subjectKey => $subject ) {
				$inputKey = $subject[ 'input_key' ];
				if ( \is_string( $inputKey ) && \in_array( $inputKey, self::ACTIVE_SUBJECT_INPUT_PRECEDENCE, true ) ) {
					$this->subjectByInputKeyMapCache[ $inputKey ] = $subjectKey;
				}
			}
		}
		return $this->subjectByInputKeyMapCache;
	}

	private function assertSubjectDefinitionContract( array $subject, array $hrefs, array $strings ) :void {
		$subjectKey = (string)( $subject[ 'key' ] ?? '[undefined]' );
		$hrefKey = $subject[ 'href_key' ] ?? '';
		if ( !\is_string( $hrefKey ) || !\array_key_exists( $hrefKey, $hrefs ) ) {
			throw new \LogicException(
				\sprintf( 'Investigate subject "%s" requires href key "%s".', $subjectKey, (string)$hrefKey )
			);
		}

		$stringKeys = $subject[ 'string_keys' ] ?? null;
		if ( !\is_array( $stringKeys ) ) {
			throw new \LogicException(
				\sprintf( 'Investigate subject "%s" requires string key map.', $subjectKey )
			);
		}

		foreach ( [ 'subject', 'panel', 'go' ] as $requiredStringKey ) {
			$stringKey = $stringKeys[ $requiredStringKey ] ?? '';
			if ( !\is_string( $stringKey ) || !\array_key_exists( $stringKey, $strings ) ) {
				throw new \LogicException(
					\sprintf(
						'Investigate subject "%s" requires string key "%s" for "%s".',
						$subjectKey,
						(string)$stringKey,
						$requiredStringKey
					)
				);
			}
		}

		if ( isset( $stringKeys[ 'lookup' ] ) ) {
			$lookupStringKey = $stringKeys[ 'lookup' ];
			if ( !\is_string( $lookupStringKey ) || !\array_key_exists( $lookupStringKey, $strings ) ) {
				throw new \LogicException(
					\sprintf(
						'Investigate subject "%s" requires lookup string key "%s".',
						$subjectKey,
						(string)$lookupStringKey
					)
				);
			}
		}

		$panelType = $subject[ 'panel_type' ] ?? '';
		$optionsKey = $subject[ 'options_key' ] ?? null;
		$inputKey = $subject[ 'input_key' ] ?? null;
		$isLookupPanel = \in_array( $panelType, [ 'lookup_text', 'lookup_select' ], true );

		if ( $panelType === 'lookup_select' ) {
			if ( !\is_string( $optionsKey ) || $optionsKey === '' ) {
				throw new \LogicException(
					\sprintf( 'Investigate subject "%s" lookup_select requires options_key.', $subjectKey )
				);
			}
		}
		else {
			if ( $optionsKey !== null ) {
				throw new \LogicException(
					\sprintf( 'Investigate subject "%s" non-select panel requires null options_key.', $subjectKey )
				);
			}
		}

		if ( $isLookupPanel ) {
			if ( !\is_string( $inputKey ) || $inputKey === '' ) {
				throw new \LogicException(
					\sprintf( 'Investigate subject "%s" lookup panel requires input_key.', $subjectKey )
				);
			}
			$subnavHint = $subject[ 'subnav_hint' ] ?? '';
			if ( !\is_string( $subnavHint ) || $subnavHint === '' ) {
				throw new \LogicException(
					\sprintf( 'Investigate subject "%s" lookup panel requires subnav_hint.', $subjectKey )
				);
			}
		}
		elseif ( $panelType === 'direct_link' ) {
			if ( $inputKey !== null ) {
				throw new \LogicException(
					\sprintf( 'Investigate subject "%s" direct_link requires null input_key.', $subjectKey )
				);
			}
		}
	}

	private function getByIpLookup() :array {
		$input = $this->getInputValues();
		$ip = $input[ 'analyse_ip' ];
		$hasLookup = !empty( $ip );
		return [
			'ip'         => $ip,
			'has_lookup' => $hasLookup,
			'is_valid'   => $hasLookup && Services::IP()->isValidIp( $ip ),
		];
	}

	private function buildLookupRouteContract( string $subNav ) :array {
		return [
			'page'    => self::con()->plugin_urls->rootAdminPageSlug(),
			'nav'     => PluginNavs::NAV_ACTIVITY,
			'nav_sub' => $subNav,
		];
	}
}
