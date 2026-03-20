<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

/**
 * @phpstan-import-type DrillLayerHeaderInput from PageDrillDownLandingBase
 * @phpstan-type SubjectDefinition array{
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
 * }
 * @phpstan-type SubjectTile array{
 *   key:string,
 *   is_enabled:bool,
 *   is_disabled:bool,
 *   is_pro:bool,
 *   is_live:bool,
 *   is_live_attr:string,
 *   title:string,
 *   icon_class:string,
 *   status:string,
 *   stat_text:string,
 *   lookup_key:string,
 *   render_action:array<string,mixed>,
 *   render_action_json:string,
 *   header:DrillLayerHeaderInput,
 *   header_json:string
 * }
 * @phpstan-type PanelLayerData array{
 *   subject_key:string,
 *   is_loaded:string,
 *   is_live:string,
 *   render_action_json:string,
 *   body:string
 * }
 * @phpstan-import-type RawDrillLayer from PageDrillDownLandingBase
 */
class PageInvestigateLanding extends PageDrillDownLandingBase {

	public const SLUG = 'plugin_admin_page_investigate_landing';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/investigate_landing.twig';
	private const SUBJECT_LIVE_TRAFFIC = 'live_traffic';

	/**
	 * @var array<string,SubjectDefinition>|null
	 */
	private ?array $subjectDefinitionsCache = null;

	/**
	 * @var list<SubjectTile>|null
	 */
	private ?array $subjectTilesCache = null;

	/**
	 * @var array<string,SubjectTile>|null
	 */
	private ?array $subjectTileLookupCache = null;

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

	protected function getLandingStrings() :array {
		return [
			'label_pro'        => __( 'PRO', 'wp-simple-firewall' ),
			'panel_loading'    => $this->getPanelLoadingMessage(),
			'panel_load_error' => $this->getPanelLoadErrorMessage(),
			'landing_hint'     => __( 'Select a subject above to begin investigating.', 'wp-simple-firewall' ),
			'subjects_heading' => __( 'Choose a subject to investigate', 'wp-simple-firewall' ),
		];
	}

	protected function getOperatorRootStep() :array {
		return \array_replace(
			parent::getOperatorRootStep(),
			[
				'focus'     => __( 'Users, IPs, assets, and live traffic stay available from the same drill path.', 'wp-simple-firewall' ),
				'next_step' => __( 'Choose the subject you need to investigate next.', 'wp-simple-firewall' ),
			]
		);
	}

	/**
	 * @return list<RawDrillLayer>
	 */
	protected function getLayers() :array {
		$activeSubject = $this->getActiveSubject();
		$activeTile = $this->getActiveSubjectTile();

		return [
			[
				'key'    => 'subjects',
				'body'   => $this->renderSubjectsLayer(),
				'header' => [
					'compact_back_label' => $this->buildBackLabel( __( 'Investigate', 'wp-simple-firewall' ) ),
				],
			],
			[
				'key'    => 'panel',
				'body'   => $this->renderPanelLayer( $activeSubject ),
				'header' => $activeTile === null
					? $this->buildIdlePanelHeader()
					: $activeTile[ 'header' ],
			],
		];
	}

	protected function getActiveLayerIndex() :int {
		return $this->getActiveSubject() === '' ? 0 : 1;
	}

	/**
	 * @param class-string<BasePluginAdminPage|PageModeLandingBase> $renderAction
	 * @param array<string,mixed> $auxData
	 * @return array<string,mixed>
	 */
	protected function buildAjaxRenderActionData( string $renderAction, array $auxData = [] ) :array {
		return ActionData::BuildAjaxRender( $renderAction, $auxData );
	}

	protected function renderSubjectsLayer() :string {
		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/investigate/layer_subjects.twig' )
			->setData( [
				'subjects' => $this->getSubjectTiles(),
				'strings'  => $this->getLandingStrings(),
			] )
			->render();
	}

	protected function renderPanelLayer( string $activeSubject ) :string {
		$panel = $this->buildPanelLayerData( $activeSubject );

		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/investigate/layer_panel.twig' )
			->setData( [
				'panel' => $panel,
			] )
			->render();
	}

	/**
	 * @return list<SubjectTile>
	 */
	protected function getSubjectTiles() :array {
		if ( $this->subjectTilesCache === null ) {
			$this->subjectTilesCache = [];
			foreach ( $this->getSubjectDefinitions() as $subject ) {
				$lookupKey = \is_string( $subject[ 'lookup_key' ] ) ? $subject[ 'lookup_key' ] : '';
				$renderAction = $subject[ 'is_enabled' ]
					? $this->buildPanelRenderActionData( $subject )
					: [];
				$header = $this->buildSubjectHeader( $subject, $lookupKey );
				$this->subjectTilesCache[] = [
					'key'               => $subject[ 'key' ],
					'is_enabled'        => $subject[ 'is_enabled' ],
					'is_disabled'       => !$subject[ 'is_enabled' ],
					'is_pro'            => $subject[ 'is_pro' ],
					'is_live'           => $this->isLiveTrafficSubject( $subject ),
					'is_live_attr'      => $this->isLiveTrafficSubject( $subject ) ? '1' : '0',
					'title'             => $subject[ 'label' ],
					'icon_class'        => $subject[ 'icon_class' ],
					'status'            => $subject[ 'status' ],
					'stat_text'         => $subject[ 'stat_text' ],
					'lookup_key'        => $lookupKey,
					'render_action'     => $renderAction,
					'render_action_json'=> $this->encodeJson( $renderAction ),
					'header'            => $header,
					'header_json'       => $this->encodeJson( $header ),
				];
			}
		}

		return $this->subjectTilesCache;
	}

	/**
	 * @return array<string,SubjectTile>
	 */
	protected function getSubjectTileLookup() :array {
		if ( $this->subjectTileLookupCache === null ) {
			$this->subjectTileLookupCache = [];
			foreach ( $this->getSubjectTiles() as $subjectTile ) {
				$this->subjectTileLookupCache[ $subjectTile[ 'key' ] ] = $subjectTile;
			}
		}

		return $this->subjectTileLookupCache;
	}

	protected function getActiveSubjectTile() :?array {
		$activeSubject = $this->getActiveSubject();
		return $activeSubject === '' ? null : $this->getSubjectTileLookup()[ $activeSubject ];
	}

	/**
	 * @return PanelLayerData
	 */
	protected function buildPanelLayerData( string $activeSubject ) :array {
		if ( $activeSubject === '' ) {
			return $this->buildEmptyPanelLayerData();
		}

		$subjectTile = $this->getSubjectTileLookup()[ $activeSubject ] ?? null;
		$subjectDefinition = $this->getSubjectDefinitions()[ $activeSubject ] ?? null;
		if ( $subjectTile === null || $subjectDefinition === null ) {
			return $this->buildEmptyPanelLayerData();
		}

		return [
			'subject_key'       => $subjectTile[ 'key' ],
			'is_loaded'         => '1',
			'is_live'           => $subjectTile[ 'is_live_attr' ],
			'render_action_json'=> $subjectTile[ 'render_action_json' ],
			'body'              => $this->buildSubjectPanelBody( $subjectDefinition, $activeSubject ),
		];
	}

	/**
	 * @return PanelLayerData
	 */
	private function buildEmptyPanelLayerData() :array {
		return [
			'subject_key'       => '',
			'is_loaded'         => '0',
			'is_live'           => '0',
			'render_action_json'=> '',
			'body'              => '',
		];
	}

	/**
	 * @param SubjectDefinition $subject
	 * @return DrillLayerHeaderInput
	 */
	private function buildSubjectHeader( array $subject, string $lookupKey ) :array {
		$nextStep = $lookupKey === ''
			? __( 'Review tabs for activity details. Use actions to respond.', 'wp-simple-firewall' )
			: __( 'Use the panel below to look up and explore.', 'wp-simple-firewall' );

		return [
			'compact_back_label' => $this->buildBackLabel( $subject[ 'label' ] ),
			'active_back_label'  => $this->buildBackLabel( __( 'Investigate', 'wp-simple-firewall' ) ),
			'breadcrumb_label'   => $subject[ 'label' ],
			'title'              => $subject[ 'label' ],
			'summary'            => $nextStep,
			'focus'              => $subject[ 'stat_text' ],
			'next_step'          => __( 'Use the panel tabs and actions to continue the investigation.', 'wp-simple-firewall' ),
			'icon_class'         => $subject[ 'icon_class' ],
			'badge'              => $subject[ 'stat_text' ],
			'badge_status'       => $subject[ 'status' ],
			'color_key'          => $subject[ 'status' ],
		];
	}

	/**
	 * @return DrillLayerHeaderInput
	 */
	private function buildIdlePanelHeader() :array {
		return [
			'compact_back_label' => $this->buildBackLabel( __( 'Investigate', 'wp-simple-firewall' ) ),
			'active_back_label'  => $this->buildBackLabel( __( 'Investigate', 'wp-simple-firewall' ) ),
			'breadcrumb_label'   => __( 'Investigation', 'wp-simple-firewall' ),
			'title'              => __( 'Investigation', 'wp-simple-firewall' ),
			'summary'            => __( 'Select a subject from the grid.', 'wp-simple-firewall' ),
			'next_step'          => __( 'Choose a subject to load the investigation panel.', 'wp-simple-firewall' ),
			'icon_class'         => 'bi bi-search',
			'badge_status'       => 'info',
			'color_key'          => 'info',
		];
	}

	/**
	 * @param SubjectDefinition $subject
	 * @return array<string,mixed>
	 */
	private function buildPanelRenderActionData( array $subject ) :array {
		if ( empty( $subject[ 'render_action' ] ) ) {
			return [];
		}

		return $this->buildAjaxRenderActionData( $subject[ 'render_action' ], [
			Constants::NAV_ID     => $subject[ 'render_nav' ],
			Constants::NAV_SUB_ID => $subject[ 'render_subnav' ],
		] );
	}

	/**
	 * @param SubjectDefinition $subject
	 */
	private function buildSubjectPanelBody( array $subject, string $activeSubject ) :string {
		if ( !$subject[ 'is_enabled' ] || empty( $subject[ 'render_action' ] ) ) {
			return '';
		}

		$actionData = [
			Constants::NAV_ID     => $subject[ 'render_nav' ],
			Constants::NAV_SUB_ID => $subject[ 'render_subnav' ],
		];

		$lookupKey = $subject[ 'lookup_key' ];
		if ( \is_string( $lookupKey ) && $lookupKey !== '' && $subject[ 'key' ] === $activeSubject ) {
			$lookupValue = $this->getLookupValues()[ $lookupKey ] ?? '';
			if ( $lookupValue !== '' ) {
				$actionData[ $lookupKey ] = $lookupValue;
			}
		}

		$renderOutput = self::con()->action_router->render( $subject[ 'render_action' ], $actionData );
		$panelBody = $this->extractInnerPageBodyHtml( $renderOutput );

		if ( \trim( $panelBody ) === '' ) {
			$panelBody = '<div class="alert alert-warning mb-0">'
						 .$this->getPanelLoadErrorMessage()
						 .'</div>';
		}

		return $panelBody;
	}

	/**
	 * @param SubjectDefinition $subject
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

	private function buildBackLabel( string $label ) :string {
		return sprintf(
			__( 'Back to %s', 'wp-simple-firewall' ),
			$label
		);
	}

	private function getActiveSubject() :string {
		if ( $this->activeSubjectCache === null ) {
			$subject = sanitize_key( $this->getTextInputFromRequestOrActionData( 'subject' ) );
			$definitions = $this->getSubjectDefinitions();

			if ( !isset( $definitions[ $subject ] ) || !$definitions[ $subject ][ 'is_enabled' ] ) {
				$subject = '';
			}

			if ( $subject === '' ) {
				$lookupValues = $this->getLookupValues();
				foreach ( $definitions as $key => $definition ) {
					$lookupKey = $definition[ 'lookup_key' ];
					if ( \is_string( $lookupKey ) && $lookupKey !== '' && ( $lookupValues[ $lookupKey ] ?? '' ) !== '' ) {
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
			$this->lookupValuesCache = [];
			foreach ( $this->getSubjectDefinitions() as $definition ) {
				$lookupKey = $definition[ 'lookup_key' ];
				if ( !\is_string( $lookupKey ) || $lookupKey === '' ) {
					continue;
				}
				$this->lookupValuesCache[ $lookupKey ] = $this->getTextInputFromRequestOrActionData( $lookupKey );
			}
		}

		return $this->lookupValuesCache;
	}

	private function extractInnerPageBodyHtml( string $renderOutput ) :string {
		if ( \trim( $renderOutput ) === '' ) {
			return '';
		}

		$prev = libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$loaded = $dom->loadHTML(
			'<?xml encoding="utf-8" ?><div>'.$renderOutput.'</div>',
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
	 * @return array<string,SubjectDefinition>
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
