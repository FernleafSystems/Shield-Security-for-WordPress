<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Services\Services;

class PageInvestigateLanding extends PageModeLandingBase {

	use InvestigateAssetOptionsBuilder;

	public const SLUG = 'plugin_admin_page_investigate_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_landing.twig';
	private const ACTIVE_SUBJECT_INPUT_PRECEDENCE = [ 'plugin_slug', 'theme_slug', 'analyse_ip', 'user_lookup' ];

	private ?array $inputValuesCache = null;

	private ?array $optionsCache = null;

	private ?array $subjectBySubnavHintMapCache = null;

	private ?array $subjectByInputKeyMapCache = null;

	private ?array $subjectDefinitionsCache = null;

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
			'by_user'      => $con->plugin_urls->investigateByUser(),
			'by_ip'        => $con->plugin_urls->investigateByIp(),
			'by_plugin'    => $con->plugin_urls->investigateByPlugin(),
			'by_theme'     => $con->plugin_urls->investigateByTheme(),
			'by_core'      => $con->plugin_urls->investigateByCore(),
		];
	}

	protected function getLandingStrings() :array {
		return [
			'selector_title'         => __( 'Choose A Subject To Investigate', 'wp-simple-firewall' ),
			'selector_intro'         => __( 'Select a subject type to load the matching investigation actions and lookup controls.', 'wp-simple-firewall' ),
			'selector_section_label' => __( 'What Do You Want To Investigate?', 'wp-simple-firewall' ),
			'lookup_section_label'   => __( 'Investigation Workflow', 'wp-simple-firewall' ),
			'panel_intro'            => __( 'Use the selected subject to run the matching investigation workflow below.', 'wp-simple-firewall' ),
			'label_pro'              => __( 'PRO', 'wp-simple-firewall' ),
			'ip_invalid_text'        => __( 'Enter a valid IPv4 or IPv6 address to investigate this IP.', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @throws \LogicException
	 */
	protected function getLandingVars() :array {
		$input = $this->getInputValues();
		$hrefs = $this->getLandingHrefs();
		$activeSubject = $this->resolveActiveSubject();

		return [
			'active_subject' => $activeSubject,
			'input'          => $input,
			'plugin_options' => $this->getOptionsByKey( 'plugin_options' ),
			'theme_options'  => $this->getOptionsByKey( 'theme_options' ),
			'subjects'       => $this->buildSubjectsPayload( $hrefs, $input, $activeSubject ),
		];
	}

	/**
	 * @throws \LogicException
	 */
	private function buildSubjectsPayload( array $hrefs, array $input, string $activeSubject ) :array {
		$subjects = [];
		foreach ( $this->getSubjectDefinitions() as $subject ) {
			$this->assertSubjectDefinitionContract( $subject, $hrefs );

			$subjectKey = (string)$subject[ 'key' ];
			$isEnabled = (bool)$subject[ 'is_enabled' ];
			$panelType = (string)$subject[ 'panel_type' ];
			$inputKey = $this->normalizeOptionalString( $subject[ 'input_key' ] ?? null );
			$optionsKey = $this->normalizeOptionalString( $subject[ 'options_key' ] ?? null );
			$isLookupPanel = $isEnabled && \in_array( $panelType, [ 'lookup_text', 'lookup_select' ], true );
			$hrefKey = (string)( $subject[ 'href_key' ] ?? '' );

			$subjects[] = [
				'key'                 => $subjectKey,
				'tab_id'              => \sprintf( 'investigate-subject-%s', $subjectKey ),
				'is_enabled'          => $isEnabled,
				'is_pro'              => (bool)$subject[ 'is_pro' ],
				'is_active'           => $isEnabled && $activeSubject === $subjectKey,
				'panel_type'          => $panelType,
				'href'                => $isEnabled && !empty( $hrefKey ) ? (string)( $hrefs[ $hrefKey ] ?? '' ) : '',
				'icon_class'          => (string)$subject[ 'icon_class' ],
				'subject_label'       => (string)$subject[ 'label' ],
				'subject_description' => (string)$subject[ 'description' ],
				'panel_title'         => (string)$subject[ 'panel_title' ],
				'lookup_placeholder'  => $this->normalizeOptionalString( $subject[ 'lookup_placeholder' ] ?? null ),
				'go_label'            => (string)$subject[ 'go_label' ],
				'input_key'           => $inputKey,
				'input_value'         => $inputKey !== null ? ( $input[ $inputKey ] ?? '' ) : null,
				'options_key'         => $optionsKey,
				'options'             => $optionsKey !== null ? $this->getOptionsByKey( $optionsKey ) : [],
				'lookup_route'        => $isLookupPanel
					? $this->buildLookupRouteContract( (string)$subject[ 'subnav_hint' ] )
					: [],
			];
		}

		return $subjects;
	}

	private function normalizeSelectableSubjectKey( string $key ) :string {
		$key = \strtolower( \trim( $key ) );
		$subject = $this->getSubjectDefinitions()[ $key ] ?? null;
		return \is_array( $subject ) && !empty( $subject[ 'is_enabled' ] ) ? $key : '';
	}

	private function resolveActiveSubject() :string {
		$explicit = $this->normalizeSelectableSubjectKey(
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

	/**
	 * @throws \LogicException
	 */
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
		if ( $this->subjectDefinitionsCache === null ) {
			$this->subjectDefinitionsCache = [];
			foreach ( PluginNavs::investigateLandingSubjectDefinitions() as $subjectKey => $subject ) {
				if ( !\is_array( $subject ) ) {
					continue;
				}
				$subject[ 'key' ] = (string)$subjectKey;
				$this->subjectDefinitionsCache[ (string)$subjectKey ] = $subject;
			}
		}
		return $this->subjectDefinitionsCache;
	}

	private function getEnabledSubjectDefinitions() :array {
		return \array_filter(
			$this->getSubjectDefinitions(),
			static fn( array $subject ) :bool => !empty( $subject[ 'is_enabled' ] )
		);
	}

	private function getSubjectBySubnavHintMap() :array {
		if ( $this->subjectBySubnavHintMapCache === null ) {
			$this->subjectBySubnavHintMapCache = [];
			foreach ( $this->getEnabledSubjectDefinitions() as $subjectKey => $subject ) {
				$subnavHint = $this->normalizeOptionalString( $subject[ 'subnav_hint' ] ?? null );
				if ( $subnavHint !== null ) {
					$this->subjectBySubnavHintMapCache[ $subnavHint ] = $subjectKey;
				}
			}
		}
		return $this->subjectBySubnavHintMapCache;
	}

	private function getSubjectByInputKeyMap() :array {
		if ( $this->subjectByInputKeyMapCache === null ) {
			$this->subjectByInputKeyMapCache = [];
			foreach ( $this->getEnabledSubjectDefinitions() as $subjectKey => $subject ) {
				$inputKey = $this->normalizeOptionalString( $subject[ 'input_key' ] ?? null );
				if ( $inputKey !== null && \in_array( $inputKey, self::ACTIVE_SUBJECT_INPUT_PRECEDENCE, true ) ) {
					$this->subjectByInputKeyMapCache[ $inputKey ] = $subjectKey;
				}
			}
		}
		return $this->subjectByInputKeyMapCache;
	}

	/**
	 * @throws \LogicException
	 */
	private function assertSubjectDefinitionContract( array $subject, array $hrefs ) :void {
		$subjectKey = (string)( $subject[ 'key' ] ?? '[undefined]' );
		foreach ( [ 'label', 'description', 'icon_class' ] as $requiredKey ) {
			$value = (string)( $subject[ $requiredKey ] ?? '' );
			if ( \trim( $value ) === '' ) {
				throw new \LogicException(
					\sprintf( 'Investigate subject "%s" requires %s.', $subjectKey, $requiredKey )
				);
			}
		}

		$panelType = (string)( $subject[ 'panel_type' ] ?? '' );
		if ( !\in_array( $panelType, [ 'lookup_text', 'lookup_select', 'direct_link', 'disabled' ], true ) ) {
			throw new \LogicException(
				\sprintf( 'Investigate subject "%s" has invalid panel_type "%s".', $subjectKey, $panelType )
			);
		}

		$isEnabled = !empty( $subject[ 'is_enabled' ] );
		$hrefKey = $this->normalizeOptionalString( $subject[ 'href_key' ] ?? null );
		if ( $isEnabled ) {
			if ( $panelType === 'disabled' ) {
				throw new \LogicException(
					\sprintf( 'Investigate subject "%s" cannot be enabled with panel_type disabled.', $subjectKey )
				);
			}
			if ( $hrefKey === null || !\array_key_exists( $hrefKey, $hrefs ) ) {
				throw new \LogicException(
					\sprintf( 'Investigate subject "%s" requires href key "%s".', $subjectKey, (string)$hrefKey )
				);
			}
		}
		elseif ( $panelType !== 'disabled' ) {
			throw new \LogicException(
				\sprintf( 'Investigate subject "%s" disabled entries must use panel_type disabled.', $subjectKey )
			);
		}

		$inputKey = $this->normalizeOptionalString( $subject[ 'input_key' ] ?? null );
		$optionsKey = $this->normalizeOptionalString( $subject[ 'options_key' ] ?? null );
		$subnavHint = $this->normalizeOptionalString( $subject[ 'subnav_hint' ] ?? null );

		if ( $panelType === 'lookup_select' && $isEnabled ) {
			if ( $optionsKey === null ) {
				throw new \LogicException(
					\sprintf( 'Investigate subject "%s" lookup_select requires options_key.', $subjectKey )
				);
			}
		}
		elseif ( $optionsKey !== null ) {
			throw new \LogicException(
				\sprintf( 'Investigate subject "%s" non-select panel requires null options_key.', $subjectKey )
			);
		}

		if ( \in_array( $panelType, [ 'lookup_text', 'lookup_select' ], true ) && $isEnabled ) {
			if ( $inputKey === null ) {
				throw new \LogicException(
					\sprintf( 'Investigate subject "%s" lookup panel requires input_key.', $subjectKey )
				);
			}
			if ( $subnavHint === null ) {
				throw new \LogicException(
					\sprintf( 'Investigate subject "%s" lookup panel requires subnav_hint.', $subjectKey )
				);
			}
		}
		elseif ( $panelType === 'direct_link' && $inputKey !== null ) {
			throw new \LogicException(
				\sprintf( 'Investigate subject "%s" direct_link requires null input_key.', $subjectKey )
			);
		}

		if ( $isEnabled ) {
			foreach ( [ 'panel_title', 'go_label' ] as $requiredTextKey ) {
				if ( \trim( (string)( $subject[ $requiredTextKey ] ?? '' ) ) === '' ) {
					throw new \LogicException(
						\sprintf( 'Investigate subject "%s" requires %s.', $subjectKey, $requiredTextKey )
					);
				}
			}

			if ( \in_array( $panelType, [ 'lookup_text', 'lookup_select' ], true )
				 && \trim( (string)( $subject[ 'lookup_placeholder' ] ?? '' ) ) === '' ) {
				throw new \LogicException(
					\sprintf( 'Investigate subject "%s" lookup panels require lookup_placeholder.', $subjectKey )
				);
			}
		}
	}

	private function normalizeOptionalString( $value ) :?string {
		if ( !\is_string( $value ) ) {
			return null;
		}
		$value = \trim( $value );
		return $value === '' ? null : $value;
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
