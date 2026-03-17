<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
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
 *   settings_href:string,
 *   settings_label:string,
 *   settings_action:array<string,mixed>,
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
 *   strip_text:string,
 *   strip_badge:string,
 *   context:array{
 *     path:list<string>,
 *     focus:string,
 *     next_step:string
 *   }
 * }
 * @phpstan-type DiagnosisFinding array{
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
 *   stat_line:string,
 *   preview_text:string,
 *   risk_context:string,
 *   next_move_heading:string,
 *   next_move:string,
 *   findings:list<DiagnosisFinding>,
 *   findings_count:int,
 *   context:array{
 *     path:list<string>,
 *     focus:string,
 *     next_step:string
 *   },
 *   strip_text:string,
 *   strip_badge:string,
 *   strip_badge_status:string,
 *   editor_context:array{
 *     path:list<string>,
 *     focus:string,
 *     next_step:string
 *   },
 *   editor_strip_text:string,
 *   editor_strip_badge:string,
 *   editor_strip_badge_status:string,
 *   zone_selection:DrillSelection,
 *   zone_selection_json:string,
 *   editor_selection:DrillSelection,
 *   editor_selection_json:string,
 *   settings_cta_label:string,
 *   is_review_state:bool
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
		$issueRows = $this->issueRows( $detailGroups );
		$firstIssue = $issueRows[ 0 ] ?? null;
		$isReviewState = $zoneKey === 'general' || \count( $issueRows ) < 1;

		$previewText = $isReviewState
			? $this->buildReviewPreviewText( $zoneTile )
			: $this->buildIssuePreviewText( $firstIssue, $zoneLabel );
		$nextMove = $isReviewState
			? $this->buildReviewNextMove( $zoneTile )
			: $this->buildIssueNextMove( $firstIssue, $zoneLabel );
		$riskContext = $this->buildRiskContext( $zoneTile, $previewText, $isReviewState );
		$findings = \array_values( \array_map(
			fn( array $row ) :array => $this->buildFinding( $row ),
			$issueRows
		) );
		$context = [
			'path'      => [
				__( 'Configure', 'wp-simple-firewall' ),
				$zoneLabel,
			],
			'focus'     => $previewText,
			'next_step' => __( 'Open settings for this zone.', 'wp-simple-firewall' ),
		];
		$editorContext = [
			'path'      => [
				__( 'Configure', 'wp-simple-firewall' ),
				$zoneLabel,
				__( 'Settings', 'wp-simple-firewall' ),
			],
			'focus'     => $nextMove,
			'next_step' => __( 'Adjust the settings and save your changes.', 'wp-simple-firewall' ),
		];
		$stripText = $isReviewState
			? sprintf( __( 'Review %s', 'wp-simple-firewall' ), $zoneLabel )
			: $this->fallbackText(
				(string)( $firstIssue[ 'title' ] ?? '' ),
				sprintf( __( 'Review %s', 'wp-simple-firewall' ), $zoneLabel )
			);
		$stripBadge = $isReviewState
			? $this->buildReviewBadge( $zoneTile )
			: $this->buildFindingsBadge( \count( $findings ) );

		$zoneSelection = [
			'key'         => $zoneKey,
			'label'       => $zoneLabel,
			'status'      => $zoneTile[ 'status' ],
			'strip_text'  => $stripText,
			'strip_badge' => $stripBadge,
			'context'     => $context,
		];
		$editorSelection = [
			'key'         => $zoneKey,
			'label'       => $zoneLabel,
			'status'      => $zoneTile[ 'status' ],
			'strip_text'  => sprintf( __( 'Edit %s Settings', 'wp-simple-firewall' ), $zoneLabel ),
			'strip_badge' => $zoneTile[ 'key' ] === 'general'
				? __( 'Review', 'wp-simple-firewall' )
				: $zoneTile[ 'status_label' ],
			'context'     => $editorContext,
		];

		return [
			'zone_key'                  => $zoneKey,
			'zone_label'                => $zoneLabel,
			'zone_icon_class'           => $zoneTile[ 'icon_class' ],
			'zone_status'               => $zoneTile[ 'status' ],
			'zone_status_label'         => $zoneTile[ 'status_label' ],
			'stat_line'                 => $zoneTile[ 'stat_line' ],
			'preview_text'              => $previewText,
			'risk_context'              => $riskContext,
			'next_move_heading'         => __( 'Next move', 'wp-simple-firewall' ),
			'next_move'                 => $nextMove,
			'findings'                  => $findings,
			'findings_count'            => \count( $findings ),
			'context'                   => $context,
			'strip_text'                => $stripText,
			'strip_badge'               => $stripBadge,
			'strip_badge_status'        => $zoneTile[ 'status' ],
			'editor_context'            => $editorContext,
			'editor_strip_text'         => $editorSelection[ 'strip_text' ],
			'editor_strip_badge'        => $editorSelection[ 'strip_badge' ],
			'editor_strip_badge_status' => $zoneTile[ 'status' ],
			'zone_selection'            => $zoneSelection,
			'zone_selection_json'       => $this->encodeJson( $zoneSelection ),
			'editor_selection'          => $editorSelection,
			'editor_selection_json'     => $this->encodeJson( $editorSelection ),
			'settings_cta_label'        => __( 'Open settings', 'wp-simple-firewall' ),
			'is_review_state'           => $isReviewState,
		];
	}

	/**
	 * @param list<DetailGroup> $detailGroups
	 * @return list<DetailGroupRow>
	 */
	private function issueRows( array $detailGroups ) :array {
		$rows = [];
		foreach ( $detailGroups as $group ) {
			if ( !\in_array( $group[ 'status' ], [ 'critical', 'warning' ], true ) ) {
				continue;
			}
			foreach ( $group[ 'rows' ] as $row ) {
				$rows[] = $row;
			}
		}
		return $rows;
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
			return sprintf( __( 'Open %s settings and review the current configuration.', 'wp-simple-firewall' ), $zoneLabel );
		}

		return sprintf(
			__( 'Open %1$s settings and review %2$s first.', 'wp-simple-firewall' ),
			$zoneLabel,
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
			return __( 'Open these settings and confirm the general controls still match how the site should operate.', 'wp-simple-firewall' );
		}

		return sprintf(
			__( 'Open %s settings to confirm this zone still matches the site.', 'wp-simple-firewall' ),
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

	private function encodeJson( array $data ) :string {
		return (string)( \json_encode( $data ) ?: '' );
	}
}
