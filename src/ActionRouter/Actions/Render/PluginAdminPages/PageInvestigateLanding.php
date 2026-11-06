<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\InvestigateLookup,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

/**
 * @phpstan-import-type DrillLayerHeader from OperatorChromeContract
 * @phpstan-type SubjectDefinition array{
 *   key:string,
 *   label:string,
 *   icon_class:string,
 *   status:string,
 *   stat_text:string,
 *   subnav_hint:string|null,
 *   context_summary:string,
 *   context_focus:string,
 *   context_next_step:string,
 *   context_badge:string,
 *   render_action:string,
 *   render_nav:string,
 *   render_subnav:string,
 *   lookup_key:string,
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
 *   primary_action_label:string,
 *   ip_rules_href:string,
 *   lookup_key:string,
 *   render_action:array<string,mixed>,
 *   render_action_json:string,
 *   header:DrillLayerHeader,
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

	protected function getLandingVars() :array {
		$vars = parent::getLandingVars();
		$vars[ 'investigate_current_tab' ] = $this->getCurrentTab();
		$vars[ 'investigate_tabs' ] = ( new InvestigateTabs() )->build( $this->getCurrentTab() );
		$vars[ 'investigate_lookup' ] = $this->renderLookup();
		$vars[ 'investigate_logs' ] = $this->buildLogTabs();
		$vars[ 'investigate_live' ] = $this->renderLiveTab();
		return $vars;
	}

	protected function renderLiveTab() :string {
		return self::con()->action_router->render( TrafficLogLivePanelBody::class, [
			Constants::NAV_ID     => PluginNavs::NAV_TRAFFIC,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_LIVE,
		] );
	}

	/**
	 * The log and IP rules tabs. Each table carries its own buttons, downloads among them.
	 * @return list<array{key:string,table_id:string}>
	 */
	protected function buildLogTabs() :array {
		return [
			[
				'key'      => InvestigateTabs::ACTIVITY,
				'table_id' => 'ShieldTable-ActivityLog',
			],
			[
				'key'      => InvestigateTabs::REQUESTS,
				'table_id' => 'ShieldTable-TrafficViewer',
			],
			[
				'key'      => InvestigateTabs::IP_RULES,
				'table_id' => 'ShieldTable-IpRules',
			],
			[
				'key'      => InvestigateTabs::SESSIONS,
				'table_id' => 'ShieldTable-SessionsViewer',
			],
		];
	}

	/**
	 * The tab to open with: the one whose link brought us here, or the lookup.
	 */
	private function getCurrentTab() :string {
		$tab = InvestigateTabs::forRoute(
			sanitize_key( (string)( $this->action_data[ Constants::NAV_ID ] ?? '' ) ),
			sanitize_key( (string)( $this->action_data[ Constants::NAV_SUB_ID ] ?? '' ) )
		);
		if ( sanitize_key( $this->getTextInputFromRequestOrActionData( 'subject' ) ) === self::SUBJECT_LIVE_TRAFFIC ) {
			$tab = InvestigateTabs::LIVE;
		}
		// A subject asked for by link opens beneath the lookup.
		return $tab === '' || $this->getActiveSubject() !== '' ? InvestigateTabs::LOOKUP : $tab;
	}

	/**
	 * @param SubjectTile $tile
	 * @return array<string,string>
	 */
	private function subjectAttributes( array $tile ) :array {
		return [
			'data-investigate-subject'       => $tile[ 'key' ],
			'data-investigate-header'        => $tile[ 'header_json' ],
			'data-investigate-render-action' => $tile[ 'render_action_json' ],
			'data-investigate-lookup-key'    => $tile[ 'lookup_key' ],
			'data-investigate-is-live'       => $tile[ 'is_live_attr' ],
		];
	}

	/**
	 * The kinds the lookup can narrow to, each carrying its subject so results open its panel.
	 * @return list<array{key:string,label:string,attributes:array<string,string>}>
	 */
	private function buildLookupKinds() :array {
		$labels = [
			'user'   => __( 'Users', 'wp-simple-firewall' ),
			'ip'     => __( 'IP addresses', 'wp-simple-firewall' ),
			'plugin' => __( 'Plugins', 'wp-simple-firewall' ),
			'theme'  => __( 'Themes', 'wp-simple-firewall' ),
			'core'   => __( 'WordPress core', 'wp-simple-firewall' ),
		];
		$kinds = [];
		foreach ( $labels as $key => $label ) {
			$tile = $this->getSubjectTileLookup()[ $key ] ?? null;
			if ( $tile !== null && $tile[ 'is_enabled' ] ) {
				$attributes = $this->subjectAttributes( $tile );
				if ( $key === 'core' ) {
					// WordPress core is one thing, so choosing it opens it straight away.
					$attributes[ 'data-drill-target' ] = 'panel';
				}
				elseif ( $key === 'ip' ) {
					// There are too many addresses to list, so they wait for something to search for.
					$attributes[ 'data-lookup-prompt' ] = __( 'Type to search for IP addresses.', 'wp-simple-firewall' );
				}
				$kinds[] = [
					'key'        => $key,
					'label'      => $label,
					'attributes' => $attributes,
				];
			}
		}
		return $kinds;
	}

	protected function getLandingStrings() :array {
		return [
			'lookup_label'            => __( 'Look up a user, IP address, plugin or theme', 'wp-simple-firewall' ),
			'lookup_placeholder'      => __( 'Search for a user, IP address, plugin or theme', 'wp-simple-firewall' ),
			'lookup_kinds_label'      => __( 'Looking up', 'wp-simple-firewall' ),
			'lookup_kind_any'         => __( 'Anything', 'wp-simple-firewall' ),
			'lookup_intro'            => __( 'Type a username, email address, IP address, or part of a plugin or theme name. Or choose what you are looking up to browse it.', 'wp-simple-firewall' ),
			'lookup_searching'        => __( 'Searching…', 'wp-simple-firewall' ),
			'lookup_none'             => __( 'Nothing matches "%s".', 'wp-simple-firewall' ),
			'lookup_none_kind'        => __( 'There is nothing to list here.', 'wp-simple-firewall' ),
			'lookup_count'            => __( '%s results', 'wp-simple-firewall' ),
			'lookup_count_one'        => __( '1 result', 'wp-simple-firewall' ),
			'lookup_failed'           => __( 'The lookup failed. Please try again.', 'wp-simple-firewall' ),
			'panel_loading'           => $this->getPanelLoadingMessage(),
			'panel_load_error'        => $this->getPanelLoadErrorMessage(),
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
				'body'   => '',
				'header' => [
					'compact_back_label' => $this->buildBackLabel( __( 'Investigate', 'wp-simple-firewall' ) ),
					'breadcrumb_label'   => __( 'Subjects', 'wp-simple-firewall' ),
					'title'              => __( 'Subjects', 'wp-simple-firewall' ),
					'summary'            => __( 'Choose a subject to investigate.', 'wp-simple-firewall' ),
					'next_step'          => __( 'Open the subject you need to inspect next.', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-search',
					'badge_status'       => 'info',
					'color_key'          => 'investigate',
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
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender> $renderAction
	 * @param array<string,mixed> $auxData
	 * @return array<string,mixed>
	 */
	protected function buildAjaxRenderActionData( string $renderAction, array $auxData = [] ) :array {
		return ActionData::BuildAjaxRender( $renderAction, $auxData );
	}

	/**
	 * The lookup sits above the subject it opens, so it stays in place while a subject is shown.
	 */
	protected function renderLookup() :string {
		return self::con()->comps->render
			->setTemplate( '/wpadmin/components/investigate/layer_subjects.twig' )
			->setData( [
				'kinds'              => $this->buildLookupKinds(),
				'lookup_action_json' => ActionData::BuildJson( InvestigateLookup::class ),
				'strings'            => $this->getLandingStrings(),
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
				$lookupKey = $subject[ 'lookup_key' ];
				$isLive = $this->isLiveTrafficSubject( $subject );
				$presentation = $this->getSubjectTilePresentation( $subject );
				$renderAction = $subject[ 'is_enabled' ]
					? $this->buildPanelRenderActionData( $subject )
					: [];
				$header = $this->buildSubjectHeader( $subject );
				$this->subjectTilesCache[] = [
					'key'               => $subject[ 'key' ],
					'is_enabled'        => $subject[ 'is_enabled' ],
					'is_disabled'       => !$subject[ 'is_enabled' ],
					'is_pro'            => $subject[ 'is_pro' ],
					'is_live'           => $isLive,
					'is_live_attr'      => $isLive ? '1' : '0',
					'title'             => $presentation[ 'title' ],
					'icon_class'        => $subject[ 'icon_class' ],
					'status'            => $subject[ 'status' ],
					'stat_text'         => $subject[ 'stat_text' ],
					'primary_action_label' => $presentation[ 'primary_action_label' ],
					'ip_rules_href'     => $subject[ 'key' ] === 'ip'
						? self::con()->plugin_urls->adminIpRules()
						: '',
					'lookup_key'        => $lookupKey,
					'render_action'     => $renderAction,
					'render_action_json'=> OperatorChromeContract::encodeJson( $renderAction ),
					'header'            => $header,
					'header_json'       => OperatorChromeContract::encodeJson( $header ),
				];
			}
		}

		return $this->subjectTilesCache;
	}

	/**
	 * @param SubjectDefinition $subject
	 * @return array{title:string,primary_action_label:string}
	 */
	private function getSubjectTilePresentation( array $subject ) :array {
		$presentation = [
			'title'                => $subject[ 'label' ],
			'primary_action_label' => sprintf( __( 'Explore %s', 'wp-simple-firewall' ), $subject[ 'label' ] ),
		];

		switch ( $subject[ 'key' ] ) {
			case 'user':
				$presentation = [
					'title'                => __( 'Users', 'wp-simple-firewall' ),
					'primary_action_label' => __( 'Explore users', 'wp-simple-firewall' ),
				];
				break;
			case 'ip':
				$presentation = [
					'title'                => __( 'IP Addresses', 'wp-simple-firewall' ),
					'primary_action_label' => __( 'Explore IP Addresses', 'wp-simple-firewall' ),
				];
				break;
			case 'plugin':
				$presentation = [
					'title'                => __( 'Plugins', 'wp-simple-firewall' ),
					'primary_action_label' => __( 'Explore plugins', 'wp-simple-firewall' ),
				];
				break;
			case 'theme':
				$presentation = [
					'title'                => __( 'Themes', 'wp-simple-firewall' ),
					'primary_action_label' => __( 'Explore themes', 'wp-simple-firewall' ),
				];
				break;
			case 'core':
				$presentation = [
					'title'                => $subject[ 'label' ],
					'primary_action_label' => __( 'Explore WordPress', 'wp-simple-firewall' ),
				];
				break;
			case 'live_traffic':
				$presentation = [
					'title'                => $subject[ 'label' ],
					'primary_action_label' => __( 'Watch Live Traffic', 'wp-simple-firewall' ),
				];
				break;
		}

		return $presentation;
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
	 * @return DrillLayerHeader
	 */
	private function buildSubjectHeader( array $subject ) :array {
		return OperatorChromeContract::normalizeHeader( [
			'compact_back_label' => $this->buildBackLabel( $subject[ 'label' ] ),
			'active_back_label'  => $this->buildBackLabel( __( 'Investigate', 'wp-simple-firewall' ) ),
			'breadcrumb_label'   => $subject[ 'label' ],
			'title'              => $subject[ 'label' ],
			'summary'            => $subject[ 'context_summary' ],
			'focus'              => $subject[ 'context_focus' ],
			'next_step'          => $subject[ 'context_next_step' ],
			'icon_class'         => $subject[ 'icon_class' ],
			'badge'              => $subject[ 'context_badge' ],
			'badge_status'       => $subject[ 'status' ],
			'color_key'          => 'investigate',
		] );
	}

	/**
	 * @return DrillLayerHeader
	 */
	private function buildIdlePanelHeader() :array {
		return OperatorChromeContract::normalizeHeader( [
			'compact_back_label' => $this->buildBackLabel( __( 'Investigate', 'wp-simple-firewall' ) ),
			'active_back_label'  => $this->buildBackLabel( __( 'Investigate', 'wp-simple-firewall' ) ),
			'breadcrumb_label'   => __( 'Investigation', 'wp-simple-firewall' ),
			'title'              => __( 'Investigation', 'wp-simple-firewall' ),
			'summary'            => __( 'Select a subject from the grid.', 'wp-simple-firewall' ),
			'next_step'          => __( 'Choose a subject to load the investigation panel.', 'wp-simple-firewall' ),
			'icon_class'         => 'bi bi-search',
			'badge_status'       => 'info',
			'color_key'          => 'investigate',
		] );
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
		if ( $lookupKey !== '' && $subject[ 'key' ] === $activeSubject ) {
			$lookupValue = $this->getLookupValues()[ $lookupKey ] ?? '';
			if ( $lookupValue !== '' ) {
				$actionData[ $lookupKey ] = $lookupValue;
			}
		}

		$panelBody = self::con()->action_router->render( $subject[ 'render_action' ], $actionData );

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

			// Live traffic has its own tab.
			if ( $subject === self::SUBJECT_LIVE_TRAFFIC
				 || !isset( $definitions[ $subject ] ) || !$definitions[ $subject ][ 'is_enabled' ] ) {
				$subject = '';
			}

			if ( $subject === '' ) {
				$lookupValues = $this->getLookupValues();
				foreach ( $definitions as $key => $definition ) {
					$lookupKey = $definition[ 'lookup_key' ];
					if ( $lookupKey !== '' && ( $lookupValues[ $lookupKey ] ?? '' ) !== '' ) {
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
				if ( $lookupKey === '' ) {
					continue;
				}
				$this->lookupValuesCache[ $lookupKey ] = $this->getTextInputFromRequestOrActionData( $lookupKey );
			}
		}

		return $this->lookupValuesCache;
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
