<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\SecurityAdminDisableActionBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Secadmin;

/**
 * @phpstan-import-type ConfigureLandingTile from ConfigureLandingRenderContracts
 * @phpstan-import-type DiagnosisContract from ConfigureLandingRenderContracts
 * @phpstan-import-type DiagnosisExpandAction from ConfigureLandingRenderContracts
 * @phpstan-import-type DiagnosisFinding from ConfigureLandingRenderContracts
 * @phpstan-import-type DetailAction from StatusDetailGroupsBuilder
 * @phpstan-import-type DetailGroup from StatusDetailGroupsBuilder
 * @phpstan-import-type DetailGroupRow from StatusDetailGroupsBuilder
 * @phpstan-import-type OperatorChromeActionInput from OperatorChromeContract
 */
class ConfigureZoneDiagnosisBuilder {

	private const IMPACT_MAP = [
		'secadmin' => 'Security Admin protects access to the plugin itself, so weak settings here can undermine every other control.',
		'firewall' => 'Firewall coverage shapes how aggressively Shield blocks bad traffic before it reaches WordPress.',
		'ips'      => 'Bots and IP controls decide how suspicious visitors are identified, allowed, or blocked across the site.',
		'scans'    => 'Scan coverage determines how quickly file changes, malware signals, and vulnerable assets are surfaced.',
		'login'    => 'Login protections reduce credential abuse, brute force attempts, and weak sign-in workflows.',
		'users'    => 'User controls reduce account misuse and tighten how privileged users can operate.',
		'spam'     => 'SPAM controls reduce low-value submissions before they become moderation or abuse problems.',
		'headers'  => 'HTTP headers strengthen the browser-facing security posture around transport and content handling.',
		'general'  => 'General controls shape site-wide defaults, logging, and supporting security behaviour that other zones depend on.',
		'reports_alerts' => 'Reports and alerts control how site security activity is surfaced to administrators.',
	];

	private ?SecurityAdminDisableActionBuilder $securityAdminDisableActionBuilder;

	public function __construct( ?SecurityAdminDisableActionBuilder $securityAdminDisableActionBuilder = null ) {
		$this->securityAdminDisableActionBuilder = $securityAdminDisableActionBuilder;
	}

	/**
	 * @param ConfigureLandingTile $zoneTile
	 * @return DiagnosisContract
	 */
	public function build( array $zoneTile ) :array {
		$zoneKey = $zoneTile[ 'key' ];
		$zoneLabel = $zoneTile[ 'label' ];
		$detailGroups = $zoneTile[ 'panel' ][ 'detail_groups' ];
		$separatedRows = $this->splitRowsBySeverity( $detailGroups );
		$problemRows = $separatedRows[ 'problem' ];
		$reviewRows = $separatedRows[ 'review' ];
		$healthyRows = $separatedRows[ 'healthy' ];
		$firstIssue = $problemRows[ 0 ] ?? null;
		$isReviewState = $zoneKey === 'general' || \count( $problemRows ) < 1;

		$previewText = $this->buildPreviewText( $zoneTile, $firstIssue, $zoneLabel, $isReviewState );
		$nextMove = $isReviewState
			? $this->buildReviewNextMove( $zoneTile )
			: $this->buildIssueNextMove( $firstIssue, $zoneLabel );
		$riskContext = $this->buildRiskContext( $zoneTile, $previewText, $isReviewState );
		$problemFindings = $this->buildFindingsForSection( $problemRows, $zoneKey );
		$reviewFindings = $this->buildFindingsForSection( $reviewRows, $zoneKey );
		$healthyFindings = $this->buildFindingsForSection( $healthyRows, $zoneKey );
		$zoneBadge = $isReviewState
			? $this->buildReviewBadge( $zoneTile )
			: $this->buildFindingsBadge( \count( $problemFindings ) );
		if ( \count( $problemFindings ) === 0
			&& \count( $reviewFindings ) === 0
			&& \count( $healthyFindings ) === 0 ) {
			$reviewFindings[] = $this->buildFallbackReviewFinding( $zoneBadge, $zoneTile[ 'stat_line' ] );
		}
		$header = OperatorChromeContract::normalizeHeader( [
			'compact_back_label' => $this->buildBackLabel( $zoneLabel ),
			'active_back_label'  => $this->buildBackLabel( __( 'Configure', 'wp-simple-firewall' ) ),
			'breadcrumb_label'   => $zoneLabel,
			'title'              => $zoneLabel,
			'meta'               => $zoneTile[ 'status_label' ],
			'summary'            => $riskContext,
			'focus'              => $previewText,
			'next_step'          => $nextMove,
			'icon_class'         => $zoneTile[ 'icon_class' ],
			'badge'              => $zoneBadge,
			'badge_status'       => $zoneTile[ 'status' ],
			'color_key'          => $zoneTile[ 'status' ],
			'actions'            => $this->buildHeaderActions( $zoneKey ),
		] );

		$zoneSelection = [
			'key'        => $zoneKey,
			'label'      => $zoneLabel,
			'status'     => $zoneTile[ 'status' ],
			'icon_class' => $zoneTile[ 'icon_class' ],
			'header'     => $header,
		];

		return [
			'zone_key'                  => $zoneKey,
			'zone_label'                => $zoneLabel,
			'zone_icon_class'           => $zoneTile[ 'icon_class' ],
			'zone_status'               => $zoneTile[ 'status' ],
			'zone_status_label'         => $zoneTile[ 'status_label' ],
			'preview_text'              => $previewText,
			'risk_context'              => $riskContext,
			'problem_rows'              => $problemFindings,
			'review_rows'               => $reviewFindings,
			'healthy_rows'              => $healthyFindings,
			'header'                    => $header,
			'zone_selection'            => $zoneSelection,
			'zone_selection_json'       => OperatorChromeContract::encodeJson( $zoneSelection ),
		];
	}

	/**
	 * @return list<OperatorChromeActionInput>
	 */
	private function buildHeaderActions( string $zoneKey ) :array {
		return $zoneKey === Secadmin::Slug()
			? $this->getSecurityAdminDisableActionBuilder()->buildConfigureContextActions()
			: [];
	}

	private function getSecurityAdminDisableActionBuilder() :SecurityAdminDisableActionBuilder {
		return $this->securityAdminDisableActionBuilder ??= new SecurityAdminDisableActionBuilder();
	}

	/**
	 * @param list<DetailGroup> $detailGroups
	 * @return array{problem:list<DetailGroupRow>,review:list<DetailGroupRow>,healthy:list<DetailGroupRow>}
	 */
	private function splitRowsBySeverity( array $detailGroups ) :array {
		$issueRows = [];
		$reviewRows = [];
		$healthyRows = [];
		foreach ( $detailGroups as $group ) {
			if ( \in_array( $group[ 'status' ], [ 'critical', 'warning' ], true ) ) {
				foreach ( $group[ 'rows' ] as $row ) {
					$issueRows[] = $row;
				}
			}
			elseif ( $group[ 'status' ] === 'good' ) {
				foreach ( $group[ 'rows' ] as $row ) {
					$healthyRows[] = $row;
				}
			}
			else {
				foreach ( $group[ 'rows' ] as $row ) {
					$reviewRows[] = $row;
				}
			}
		}
		return [
			'problem' => $issueRows,
			'review'  => $reviewRows,
			'healthy' => $healthyRows,
		];
	}

	/**
	 * @param DetailGroupRow $row
	 * @return DiagnosisFinding
	 */
	private function buildFinding( array $row, string $zoneKey ) :array {
		$rowKey = $this->requireRowKey( $row );

		return [
			'key'               => $rowKey,
			'title'             => $row[ 'title' ],
			'summary'           => $this->primarySummary( $row ),
			'status'            => $row[ 'status' ],
			'status_label'      => $row[ 'status_label' ],
			'status_icon_class' => $row[ 'status_icon_class' ],
			'explanations'      => $row[ 'explanations' ],
			'expand_action'     => $this->buildExpandAction( $row[ 'action' ], $this->buildExpandId( $zoneKey, $rowKey ) ),
		];
	}

	/**
	 * @param list<DetailGroupRow> $rows
	 * @return list<DiagnosisFinding>
	 */
	private function buildFindingsForSection( array $rows, string $zoneKey ) :array {
		return \array_values( \array_map(
			fn( array $row ) :array => $this->buildFinding( $row, $zoneKey ),
			$rows
		) );
	}

	/**
	 * @return DiagnosisFinding
	 */
	private function buildFallbackReviewFinding(
		string $title,
		string $summary
	) :array {
		return [
			'key'               => 'review_fallback',
			'title'             => $title,
			'summary'           => $summary,
			'status'            => 'neutral',
			'status_label'      => __( 'Review', 'wp-simple-firewall' ),
			'status_icon_class' => 'bi bi-info-circle-fill',
			'explanations'      => [],
			'expand_action'     => $this->buildCollapsedExpandAction(),
		];
	}

	/**
	 * @param array{}|DetailAction $action
	 * @return DiagnosisExpandAction
	 */
	private function buildExpandAction( array $action, string $expandId ) :array {
		$dataAttributes = [];
		$isExpandable = false;

		if ( !empty( $action ) ) {
			$dataAttributes = $action[ 'data' ] ?? [];
			$isExpandable = !empty( $dataAttributes[ 'zone_component_slug' ] )
				&& !empty( $dataAttributes[ 'zone_component_action' ] );
		}

		return [
			'id'              => $isExpandable ? $expandId : '',
			'is_expandable'   => $isExpandable,
			'label'           => (string)( $action[ 'label' ] ?? __( 'Configure', 'wp-simple-firewall' ) ),
			'title'           => (string)( $action[ 'title' ] ?? '' ),
			'data_attributes' => $dataAttributes,
		];
	}

	/**
	 * @return DiagnosisExpandAction
	 */
	private function buildCollapsedExpandAction() :array {
		return [
			'id'              => '',
			'is_expandable'   => false,
			'label'           => '',
			'title'           => '',
			'data_attributes' => [],
		];
	}

	private function buildExpandId( string $zoneKey, string $rowKey ) :string {
		return \sprintf(
			'configure-diagnosis-%s-%s',
			$this->normalizeExpandIdSegment( $zoneKey ),
			$this->normalizeExpandIdSegment( $rowKey )
		);
	}

	/**
	 * @param DetailGroupRow $row
	 */
	private function requireRowKey( array $row ) :string {
		$rowKey = (string)( $row[ 'key' ] ?? '' );
		if ( $rowKey === '' ) {
			throw new \LogicException(
				'Configure diagnosis rows require a non-empty producer-owned row key: '.(string)( $row[ 'title' ] ?? '[untitled]' )
			);
		}
		return $rowKey;
	}

	private function normalizeExpandIdSegment( string $value ) :string {
		return (string)( \preg_replace( '/[^a-z0-9_-]+/', '-', \strtolower( \trim( $value ) ) ) ?? '' );
	}

	/**
	 * @param ConfigureLandingTile $zoneTile
	 * @param DetailGroupRow|null $firstIssue
	 */
	private function buildPreviewText(
		array $zoneTile,
		?array $firstIssue,
		string $zoneLabel,
		bool $isReviewState
	) :string {
		if ( $zoneTile[ 'key' ] === 'login' ) {
			return __( 'Protect the WordPress login and verify user logins with two-factor authentication.', 'wp-simple-firewall' );
		}

		return $isReviewState
			? $this->buildReviewPreviewText( $zoneTile )
			: $this->buildIssuePreviewText( $firstIssue, $zoneLabel );
	}

	/**
	 * @param DetailGroupRow|null $row
	 */
	private function buildIssuePreviewText( ?array $row, string $zoneLabel ) :string {
		if ( $row === null ) {
			return sprintf( __( 'Review the current %s findings.', 'wp-simple-firewall' ), $zoneLabel );
		}

		return $this->fallbackText(
			$this->primarySummary( $row ),
			sprintf( __( '%s needs attention.', 'wp-simple-firewall' ), $row[ 'title' ] )
		);
	}

	/**
	 * @param DetailGroupRow|null $row
	 */
	private function buildIssueNextMove( ?array $row, string $zoneLabel ) :string {
		if ( $row === null ) {
			return sprintf(
				__( 'Review the settings below and confirm the current %s configuration.', 'wp-simple-firewall' ),
				$zoneLabel
			);
		}

		return sprintf(
			__( 'Review %s below next.', 'wp-simple-firewall' ),
			$row[ 'title' ]
		);
	}

	/**
	 * @param ConfigureLandingTile $zoneTile
	 */
	private function buildReviewPreviewText( array $zoneTile ) :string {
		if ( $zoneTile[ 'key' ] === 'general' ) {
			return __( 'Review the site-wide controls that support the rest of Shield.', 'wp-simple-firewall' );
		}

		return sprintf(
			__( '%s currently shows no active findings.', 'wp-simple-firewall' ),
			$zoneTile[ 'label' ]
		);
	}

	/**
	 * @param ConfigureLandingTile $zoneTile
	 */
	private function buildReviewNextMove( array $zoneTile ) :string {
		if ( $zoneTile[ 'key' ] === 'general' ) {
			return __( 'Review these settings below and confirm the general controls still match how the site should operate.', 'wp-simple-firewall' );
		}

		return sprintf(
			__( 'Review the settings below to confirm %s still matches the site.', 'wp-simple-firewall' ),
			$zoneTile[ 'label' ]
		);
	}

	/**
	 * @param ConfigureLandingTile $zoneTile
	 */
	private function buildRiskContext( array $zoneTile, string $previewText, bool $isReviewState ) :string {
		$impact = self::IMPACT_MAP[ $zoneTile[ 'key' ] ] ?? '';
		return \trim( $impact.' '.( $isReviewState ? $zoneTile[ 'stat_line' ] : $previewText ) );
	}

	/**
	 * @param ConfigureLandingTile $zoneTile
	 */
	private function buildReviewBadge( array $zoneTile ) :string {
		return $zoneTile[ 'key' ] === 'general'
			? __( 'Review', 'wp-simple-firewall' )
			: $zoneTile[ 'status_label' ];
	}

	private function buildFindingsBadge( int $findingsCount ) :string {
		return sprintf(
			_n( '%s finding', '%s findings', $findingsCount, 'wp-simple-firewall' ),
			$findingsCount
		);
	}

	private function buildBackLabel( string $label ) :string {
		return sprintf(
			__( 'Back to %s', 'wp-simple-firewall' ),
			$label
		);
	}

	/**
	 * @param DetailGroupRow $row
	 */
	private function primarySummary( array $row ) :string {
		return $this->fallbackText(
			$row[ 'summary' ],
			$row[ 'explanations' ][ 0 ] ?? '',
			$row[ 'title' ]
		);
	}

	private function fallbackText( string ...$options ) :string {
		foreach ( $options as $option ) {
			$option = \trim( $option );
			if ( $option !== '' ) {
				return $option;
			}
		}
		return '';
	}

}
