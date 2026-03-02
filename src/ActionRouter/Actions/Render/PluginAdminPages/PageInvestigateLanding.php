<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageInvestigateLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_investigate_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_landing.twig';

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
		return [];
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
			'selector_intro'         => __( 'Choose a subject tile to navigate directly to the relevant investigation page.', 'wp-simple-firewall' ),
			'selector_section_label' => __( 'What Do You Want To Investigate?', 'wp-simple-firewall' ),
			'label_pro'              => __( 'PRO', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @throws \LogicException
	 */
	protected function getLandingVars() :array {
		return [
			'subjects' => $this->buildSubjectsPayload( $this->getLandingHrefs() ),
		];
	}

	/**
	 * @throws \LogicException
	 */
	private function buildSubjectsPayload( array $hrefs ) :array {
		$subjects = [];
		foreach ( $this->getSubjectDefinitions() as $subject ) {
			$this->assertSubjectDefinitionContract( $subject, $hrefs );

			$hrefKey = $this->normalizeOptionalString( $subject[ 'href_key' ] ?? null );
			$isEnabled = !empty( $subject[ 'is_enabled' ] );
			$subjects[] = [
				'key'                 => $subject[ 'key' ],
				'is_enabled'          => $isEnabled,
				'is_pro'              => !empty( $subject[ 'is_pro' ] ),
				'href'                => $isEnabled && $hrefKey !== null ? $hrefs[ $hrefKey ] : '',
				'icon_class'          => $subject[ 'icon_class' ],
				'subject_label'       => $subject[ 'label' ],
				'subject_description' => $subject[ 'description' ],
			];
		}
		return $subjects;
	}

	protected function getSubjectDefinitions() :array {
		if ( $this->subjectDefinitionsCache === null ) {
			$this->subjectDefinitionsCache = [];
			foreach ( PluginNavs::investigateLandingSubjectDefinitions() as $subjectKey => $subject ) {
				if ( !\is_array( $subject ) ) {
					continue;
				}
				$subject[ 'key' ] = $subjectKey;
				$this->subjectDefinitionsCache[ $subjectKey ] = $subject;
			}
		}
		return $this->subjectDefinitionsCache;
	}

	/**
	 * @throws \LogicException
	 */
	private function assertSubjectDefinitionContract( array $subject, array $hrefs ) :void {
		$subjectKey = $this->requireSubjectString( $subject, 'key', '[undefined]' );
		foreach ( [ 'label', 'description', 'icon_class' ] as $requiredKey ) {
			$this->requireSubjectString( $subject, $requiredKey, $subjectKey );
		}

		$panelType = $this->requireSubjectString( $subject, 'panel_type', $subjectKey );
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
					\sprintf( 'Investigate subject "%s" requires href key "%s".', $subjectKey, $hrefKey ?? '' )
				);
			}
		}
		elseif ( $panelType !== 'disabled' ) {
			throw new \LogicException(
				\sprintf( 'Investigate subject "%s" disabled entries must use panel_type disabled.', $subjectKey )
			);
		}
	}

	/**
	 * @throws \LogicException
	 */
	private function requireSubjectString( array $subject, string $key, string $subjectKey ) :string {
		$value = $subject[ $key ] ?? null;
		if ( !\is_string( $value ) || \trim( $value ) === '' ) {
			throw new \LogicException(
				\sprintf( 'Investigate subject "%s" requires %s.', $subjectKey, $key )
			);
		}
		return $value;
	}

	private function normalizeOptionalString( $value ) :?string {
		if ( !\is_string( $value ) ) {
			return null;
		}
		$value = \trim( $value );
		return $value === '' ? null : $value;
	}
}
