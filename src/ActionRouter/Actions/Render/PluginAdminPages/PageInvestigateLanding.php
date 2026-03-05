<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxBatchRequests,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageInvestigateLanding extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_investigate_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_landing.twig';
	private const SUBJECT_LIVE_TRAFFIC = 'live_traffic';

	/**
	 * @var array<string,array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   stat_text:string,
	 *   subnav_hint:string|null,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   render_action:string,
	 *   render_nav:string,
	 *   render_subnav:string,
	 *   lookup_key:string|null,
	 *   is_enabled:bool,
	 *   is_pro:bool
	 * }>|null
	 */
	private ?array $subjectDefinitionsCache = null;

	/**
	 * @var list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   is_pro:bool,
	 *   is_loaded:bool,
	 *   is_live:bool,
	 *   title:string,
	 *   icon_class:string,
	 *   status:string,
	 *   stat_text:string,
	 *   lookup_key:string,
	 *   subject_title:string,
	 *   subject_icon:string,
	 *   subject_meta:string,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   panel_body:string,
	 *   render_action:array<string,mixed>
	 * }>|null
	 */
	private ?array $subjectsPayloadCache = null;

	private ?string $activeSubjectCache = null;

	/**
	 * @var array<string,string>|null
	 */
	private ?array $lookupValuesCache = null;

	protected function getLandingTitle() :string {
		return __( 'Investigate', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Investigate users, IPs, assets, and live traffic.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'search';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_INVESTIGATE;
	}

	protected function isLandingInteractive() :bool {
		return true;
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool
	 * }>
	 */
	protected function getLandingTiles() :array {
		return \array_map(
			fn( array $subject ) :array => [
				'key'          => $subject[ 'key' ],
				'panel_target' => $subject[ 'panel_target' ],
				'is_enabled'   => $subject[ 'is_enabled' ],
				'is_disabled'  => $subject[ 'is_disabled' ],
			],
			$this->getSubjectsPayload()
		);
	}

	protected function getLandingPanel() :array {
		return [
			'active_target' => $this->getActiveSubject(),
		];
	}

	protected function getLandingStrings() :array {
		return [
			'label_pro'        => __( 'PRO', 'wp-simple-firewall' ),
			'panel_loading'    => $this->getPanelLoadingMessage(),
			'panel_load_error' => $this->getPanelLoadErrorMessage(),
			'landing_hint'     => __( 'Select a subject above to begin investigating.', 'wp-simple-firewall' ),
		];
	}

	protected function getLandingVars() :array {
		return [
			'subjects'            => $this->getSubjectsPayload(),
			'active_subject'      => $this->getActiveSubject(),
			'batch_render_action' => ActionData::Build( AjaxBatchRequests::class ),
		];
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   is_pro:bool,
	 *   is_loaded:bool,
	 *   is_live:bool,
	 *   title:string,
	 *   icon_class:string,
	 *   status:string,
	 *   stat_text:string,
	 *   lookup_key:string,
	 *   subject_title:string,
	 *   subject_icon:string,
	 *   subject_meta:string,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   panel_body:string,
	 *   render_action:array<string,mixed>
	 * }>
	 */
	private function getSubjectsPayload() :array {
		if ( $this->subjectsPayloadCache === null ) {
			$this->subjectsPayloadCache = $this->buildSubjectsPayload();
		}
		return $this->subjectsPayloadCache;
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   is_pro:bool,
	 *   is_loaded:bool,
	 *   is_live:bool,
	 *   title:string,
	 *   icon_class:string,
	 *   status:string,
	 *   stat_text:string,
	 *   lookup_key:string,
	 *   subject_title:string,
	 *   subject_icon:string,
	 *   subject_meta:string,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   panel_body:string,
	 *   render_action:array<string,mixed>
	 * }>
	 */
	private function buildSubjectsPayload() :array {
		$activeSubject = $this->getActiveSubject();
		$lookupValues = $this->getLookupValues();
		$subjects = [];
		foreach ( $this->getSubjectDefinitions() as $subject ) {
			$isEnabled = $subject[ 'is_enabled' ];
			$isLoaded = $this->shouldPreloadSubjectPanel( $subject, $activeSubject );
			$banner = $this->buildSubjectBannerData( $subject, $isLoaded, $activeSubject, $lookupValues );
			$subjects[] = [
				'key'          => $subject[ 'key' ],
				'panel_target' => $subject[ 'key' ],
				'is_enabled'   => $isEnabled,
				'is_disabled'  => !$isEnabled,
				'is_pro'       => $subject[ 'is_pro' ],
				'is_loaded'    => $isLoaded,
				'is_live'      => $this->isLiveTrafficSubject( $subject ),
				'title'        => $subject[ 'label' ],
				'icon_class'   => $subject[ 'icon_class' ],
				'status'       => $subject[ 'status' ],
				'stat_text'    => $subject[ 'stat_text' ],
				'lookup_key'   => $banner[ 'lookup_key' ],
				'subject_title' => $banner[ 'subject_title' ],
				'subject_icon'  => $banner[ 'subject_icon' ],
				'subject_meta'  => $banner[ 'subject_meta' ],
				'panel_title'  => $subject[ 'panel_title' ],
				'panel_status' => $subject[ 'panel_status' ],
				'panel_body'   => $isLoaded
					? $this->buildSubjectPanelBody( $subject, $activeSubject )
					: $this->buildSubjectUnloadedPanelBody( $subject ),
				'render_action' => $isEnabled ? $this->buildPanelRenderActionData( $subject ) : [],
			];
		}
		return $subjects;
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   stat_text:string,
	 *   subnav_hint:string|null,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   render_action:string,
	 *   render_nav:string,
	 *   render_subnav:string,
	 *   lookup_key:string|null,
	 *   is_enabled:bool,
	 *   is_pro:bool
	 * } $subject
	 * @param array<string,string> $lookupValues
	 * @return array{
	 *   lookup_key:string,
	 *   subject_title:string,
	 *   subject_icon:string,
	 *   subject_meta:string
	 * }
	 */
	private function buildSubjectBannerData( array $subject, bool $isLoaded, string $activeSubject, array $lookupValues ) :array {
		$lookupKey = \is_string( $subject[ 'lookup_key' ] ) ? $subject[ 'lookup_key' ] : '';
		$lookupValue = '';
		if ( $isLoaded && $subject[ 'key' ] === $activeSubject && !empty( $lookupKey ) ) {
			$lookupValue = \trim( (string)( $lookupValues[ $lookupKey ] ?? '' ) );
		}

		return [
			'lookup_key'   => $lookupKey,
			'subject_title' => $lookupValue,
			'subject_icon'  => (string)$subject[ 'icon_class' ],
			'subject_meta'  => (string)$subject[ 'stat_text' ],
		];
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   stat_text:string,
	 *   subnav_hint:string|null,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   render_action:string,
	 *   render_nav:string,
	 *   render_subnav:string,
	 *   lookup_key:string|null,
	 *   is_enabled:bool,
	 *   is_pro:bool
	 * } $subject
	 */
	private function shouldPreloadSubjectPanel( array $subject, string $activeSubject ) :bool {
		return $subject[ 'is_enabled' ]
			   && !empty( $subject[ 'render_action' ] )
			   && $subject[ 'key' ] === $activeSubject;
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   stat_text:string,
	 *   subnav_hint:string|null,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   render_action:string,
	 *   render_nav:string,
	 *   render_subnav:string,
	 *   lookup_key:string|null,
	 *   is_enabled:bool,
	 *   is_pro:bool
	 * } $subject
	 * @return array<string,mixed>
	 */
	private function buildPanelRenderActionData( array $subject ) :array {
		$renderAction = $subject[ 'render_action' ];
		if ( empty( $renderAction ) ) {
			return [];
		}

		return $this->buildAjaxRenderActionData( $renderAction, [
			Constants::NAV_ID     => $subject[ 'render_nav' ],
			Constants::NAV_SUB_ID => $subject[ 'render_subnav' ],
		] );
	}

	/**
	 * @param class-string<BasePluginAdminPage|PageModeLandingBase> $renderAction
	 * @param array<string,mixed> $auxData
	 * @return array<string,mixed>
	 */
	protected function buildAjaxRenderActionData( string $renderAction, array $auxData = [] ) :array {
		return ActionData::BuildAjaxRender( $renderAction, $auxData );
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   stat_text:string,
	 *   subnav_hint:string|null,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   render_action:string,
	 *   render_nav:string,
	 *   render_subnav:string,
	 *   lookup_key:string|null,
	 *   is_enabled:bool,
	 *   is_pro:bool
	 * } $subject
	 */
	private function buildSubjectPanelBody( array $subject, string $activeSubject ) :string {
		if ( !$subject[ 'is_enabled' ] ) {
			return '';
		}

		$renderAction = $subject[ 'render_action' ];
		if ( empty( $renderAction ) ) {
			return '';
		}

		$actionData = [
			Constants::NAV_ID     => $subject[ 'render_nav' ],
			Constants::NAV_SUB_ID => $subject[ 'render_subnav' ],
		];

		$lookupKey = $subject[ 'lookup_key' ];
		if ( \is_string( $lookupKey ) && !empty( $lookupKey ) && $subject[ 'key' ] === $activeSubject ) {
			$lookupValues = $this->getLookupValues();
			$lookupValue = $lookupValues[ $lookupKey ] ?? '';
			if ( !empty( $lookupValue ) ) {
				$actionData[ $lookupKey ] = $lookupValue;
			}
		}

		$renderOutput = self::con()->action_router->render( $renderAction, $actionData );
		$panelBody = $this->extractInnerPageBodyHtml( $renderOutput );

		if ( empty( \trim( $panelBody ) ) ) {
			$panelBody = '<div class="alert alert-warning mb-0">'
						 .$this->getPanelLoadErrorMessage()
						 .'</div>';
		}

		return $panelBody;
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   stat_text:string,
	 *   subnav_hint:string|null,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   render_action:string,
	 *   render_nav:string,
	 *   render_subnav:string,
	 *   lookup_key:string|null,
	 *   is_enabled:bool,
	 *   is_pro:bool
	 * } $subject
	 */
	private function buildSubjectUnloadedPanelBody( array $subject ) :string {
		if ( !$subject[ 'is_enabled' ] || empty( $subject[ 'render_action' ] ) ) {
			return '';
		}
		return '<div class="text-muted small" data-investigate-panel-placeholder="1">'
			   .$this->getPanelLoadingMessage()
			   .'</div>';
	}

	/**
	 * @param array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   stat_text:string,
	 *   subnav_hint:string|null,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   render_action:string,
	 *   render_nav:string,
	 *   render_subnav:string,
	 *   lookup_key:string|null,
	 *   is_enabled:bool,
	 *   is_pro:bool
	 * } $subject
	 */
	private function isLiveTrafficSubject( array $subject ) :bool {
		return $subject[ 'key' ] === self::SUBJECT_LIVE_TRAFFIC;
	}

	private function getPanelLoadingMessage() :string {
		return __( 'Loading investigation panel...', 'wp-simple-firewall' );
	}

	private function getPanelLoadErrorMessage() :string {
		return __( 'Unable to load this investigation panel. Please try again.', 'wp-simple-firewall' );
	}

	private function getActiveSubject() :string {
		if ( $this->activeSubjectCache === null ) {
			$subject = sanitize_key( $this->getTextInputFromRequestOrActionData( 'subject' ) );
			$definitions = $this->getSubjectDefinitions();

			if ( !isset( $definitions[ $subject ] ) || !$definitions[ $subject ][ 'is_enabled' ] ) {
				$subject = '';
			}

			if ( empty( $subject ) ) {
				$lookupValues = $this->getLookupValues();
				foreach ( $definitions as $key => $definition ) {
					$lookupKey = $definition[ 'lookup_key' ];
					if ( \is_string( $lookupKey ) && !empty( $lookupKey ) && !empty( $lookupValues[ $lookupKey ] ?? '' ) ) {
						$subject = $key;
						break;
					}
				}
			}

			$this->activeSubjectCache = $subject;
		}

		return $this->activeSubjectCache;
	}

	/**
	 * @return array<string,string>
	 */
	private function getLookupValues() :array {
		if ( $this->lookupValuesCache === null ) {
			$values = [];
			foreach ( $this->getSubjectDefinitions() as $definition ) {
				$lookupKey = $definition[ 'lookup_key' ];
				if ( !\is_string( $lookupKey ) || empty( $lookupKey ) ) {
					continue;
				}
				$values[ $lookupKey ] = $this->getTextInputFromRequestOrActionData( $lookupKey );
			}
			$this->lookupValuesCache = $values;
		}
		return $this->lookupValuesCache;
	}

	private function extractInnerPageBodyHtml( string $renderOutput ) :string {
		if ( empty( \trim( $renderOutput ) ) ) {
			return '';
		}

		$prev = libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$loaded = $dom->loadHTML(
			'<?xml encoding="utf-8" ?>'.$renderOutput,
			\LIBXML_HTML_NODEFDTD | \LIBXML_HTML_NOIMPLIED
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( !$loaded ) {
			return $renderOutput;
		}

		$xpath = new \DOMXPath( $dom );
		$nodes = $xpath->query( '//*[@data-inner-page-body-shell="1"]' );
		if ( !( $nodes instanceof \DOMNodeList ) || $nodes->length < 1 ) {
			$nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " inner-page-body-shell ")]' );
		}
		if ( !( $nodes instanceof \DOMNodeList ) || $nodes->length < 1 ) {
			return $renderOutput;
		}

		$container = $nodes->item( 0 );
		if ( !( $container instanceof \DOMNode ) ) {
			return $renderOutput;
		}

		return $this->renderChildNodesHtml( $container );
	}

	private function renderChildNodesHtml( \DOMNode $container ) :string {
		$html = '';
		foreach ( $container->childNodes as $childNode ) {
			$html .= (string)$container->ownerDocument->saveHTML( $childNode );
		}
		return $html;
	}

	/**
	 * @return array<string,array{
	 *   key:string,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   stat_text:string,
	 *   subnav_hint:string|null,
	 *   panel_title:string,
	 *   panel_status:string,
	 *   render_action:string,
	 *   render_nav:string,
	 *   render_subnav:string,
	 *   lookup_key:string|null,
	 *   is_enabled:bool,
	 *   is_pro:bool
	 * }>
	 */
	protected function getSubjectDefinitions() :array {
		if ( $this->subjectDefinitionsCache === null ) {
			$this->subjectDefinitionsCache = [];
			foreach ( PluginNavs::investigateLandingSubjectDefinitions() as $subjectKey => $subject ) {
				$subject[ 'key' ] = $subjectKey;
				$this->subjectDefinitionsCache[ $subjectKey ] = $subject;
			}
		}
		return $this->subjectDefinitionsCache;
	}
}
