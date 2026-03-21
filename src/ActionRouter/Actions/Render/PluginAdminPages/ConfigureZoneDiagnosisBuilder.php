<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type DrillLayerHeaderInput from OperatorChromeContract
 * @phpstan-import-type DetailAction from StatusDetailGroupsBuilder
 * @phpstan-import-type DetailActionData from StatusDetailGroupsBuilder
 * @phpstan-import-type DetailGroup from StatusDetailGroupsBuilder
 * @phpstan-import-type DetailGroupRow from StatusDetailGroupsBuilder
 * @phpstan-type ConfigureZoneTile array{
 *   key:string,
 *   include_in_posture:bool,
 *   label:string,
 *   icon_class:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string,
 *   stat_line:string,
 *   panel:array{
 *     title:string,
 *     status:string,
 *     status_label:string,
 *     components:list<array<string,mixed>>,
 *     detail_groups:list<DetailGroup>
 *   }
 * }
 * @phpstan-type DrillSelection array{
 *   key:string,
 *   label:string,
 *   status:string,
 *   icon_class:string,
 *   header:DrillLayerHeaderInput
 * }
 * @phpstan-type DiagnosisExpandAction array{
 *   is_expandable:bool,
 *   label:string,
 *   title:string,
 *   data_attributes:DetailActionData
 * }
 * @phpstan-type DiagnosisFinding array{
 *   title:string,
 *   summary:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string,
 *   explanations:list<string>,
 *   expand_action:DiagnosisExpandAction
 * }
 * @phpstan-type DiagnosisReviewFallbackCard array{
 *   title:string,
 *   summary:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string
 * }
 * @phpstan-type DiagnosisContract array{
 *   zone_key:string,
 *   zone_label:string,
 *   zone_icon_class:string,
 *   zone_status:string,
 *   zone_status_label:string,
 *   preview_text:string,
 *   risk_context:string,
 *   next_move_heading:string,
 *   next_move:string,
 *   problem_rows:list<DiagnosisFinding>,
 *   review_rows:list<DiagnosisFinding>,
 *   healthy_rows:list<DiagnosisFinding>,
 *   review_fallback_card:array{}|DiagnosisReviewFallbackCard,
 *   review_rows_heading:string,
 *   healthy_rows_heading:string,
 *   header:DrillLayerHeaderInput,
 *   zone_selection:DrillSelection,
 *   zone_selection_json:string
 * }
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
	];

	/**
	 * @param ConfigureZoneTile $zoneTile
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

		$previewText = $isReviewState
			? $this->buildReviewPreviewText( $zoneTile )
			: $this->buildIssuePreviewText( $firstIssue, $zoneLabel );
		$nextMove = $isReviewState
			? $this->buildReviewNextMove( $zoneTile )
			: $this->buildIssueNextMove( $firstIssue, $zoneLabel );
		$riskContext = $this->buildRiskContext( $zoneTile, $previewText, $isReviewState );
		$problemFindings = \array_values( \array_map(
			fn( array $row ) :array => $this->buildFinding( $row ),
			$problemRows
		) );
		$reviewFindings = \array_values( \array_map(
			fn( array $row ) :array => $this->buildFinding( $row ),
			$reviewRows
		) );
		$healthyFindings = \array_values( \array_map(
			fn( array $row ) :array => $this->buildFinding( $row ),
			$healthyRows
		) );
		$healthyFindingsCount = \count( $healthyFindings );
		$zoneBadge = $isReviewState
			? $this->buildReviewBadge( $zoneTile )
			: $this->buildFindingsBadge( \count( $problemFindings ) );
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
		] );
		$reviewFallbackCard = $this->buildReviewFallbackCard(
			$zoneBadge,
			$zoneTile[ 'stat_line' ],
			$problemFindings,
			$reviewFindings,
			$healthyFindings
		);

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
			'next_move_heading'         => __( 'Next move', 'wp-simple-firewall' ),
			'next_move'                 => $nextMove,
			'problem_rows'              => $problemFindings,
			'review_rows'               => $reviewFindings,
			'healthy_rows'              => $healthyFindings,
			'review_fallback_card'      => $reviewFallbackCard,
			'review_rows_heading'       => __( 'Review these settings', 'wp-simple-firewall' ),
			'healthy_rows_heading'      => sprintf(
				_n(
					'%s setting configured correctly',
					'%s settings configured correctly',
					$healthyFindingsCount,
					'wp-simple-firewall'
				),
				$healthyFindingsCount
			),
			'header'                    => $header,
			'zone_selection'            => $zoneSelection,
			'zone_selection_json'       => OperatorChromeContract::encodeJson( $zoneSelection ),
		];
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
	private function buildFinding( array $row ) :array {
		return [
			'title'             => $row[ 'title' ],
			'summary'           => $this->primarySummary( $row ),
			'status'            => $row[ 'status' ],
			'status_label'      => $row[ 'status_label' ],
			'status_icon_class' => $row[ 'status_icon_class' ],
			'explanations'      => $row[ 'explanations' ],
			'expand_action'     => $this->buildExpandAction( $row[ 'action' ] ),
		];
	}

	/**
	 * @param list<DiagnosisFinding> $problemFindings
	 * @param list<DiagnosisFinding> $reviewFindings
	 * @param list<DiagnosisFinding> $healthyFindings
	 * @return array{}|DiagnosisReviewFallbackCard
	 */
	private function buildReviewFallbackCard(
		string $title,
		string $summary,
		array $problemFindings,
		array $reviewFindings,
		array $healthyFindings
	) :array {
		return ( \count( $problemFindings ) === 0 && \count( $reviewFindings ) === 0 && \count( $healthyFindings ) === 0 )
			? [
				'title'             => $title,
				'summary'           => $summary,
				'status'            => 'neutral',
				'status_label'      => __( 'Review', 'wp-simple-firewall' ),
				'status_icon_class' => 'bi bi-info-circle-fill',
			]
			: [];
	}

	/**
	 * @param array{}|DetailAction $action
	 * @return DiagnosisExpandAction
	 */
	private function buildExpandAction( array $action ) :array {
		$dataAttributes = [];
		$isExpandable = false;

		if ( !empty( $action ) ) {
			$dataAttributes = $action[ 'data' ] ?? [];
			$isExpandable = !empty( $dataAttributes[ 'zone_component_slug' ] )
				&& !empty( $dataAttributes[ 'zone_component_action' ] );
		}

		return [
			'is_expandable'   => $isExpandable,
			'label'           => (string)( $action[ 'label' ] ?? __( 'Configure', 'wp-simple-firewall' ) ),
			'title'           => (string)( $action[ 'title' ] ?? '' ),
			'data_attributes' => $dataAttributes,
		];
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
	 * @param ConfigureZoneTile $zoneTile
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
	 * @param ConfigureZoneTile $zoneTile
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
	 * @param ConfigureZoneTile $zoneTile
	 */
	private function buildRiskContext( array $zoneTile, string $previewText, bool $isReviewState ) :string {
		$impact = self::IMPACT_MAP[ $zoneTile[ 'key' ] ] ?? '';
		return \trim( $impact.' '.( $isReviewState ? $zoneTile[ 'stat_line' ] : $previewText ) );
	}

	/**
	 * @param ConfigureZoneTile $zoneTile
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
