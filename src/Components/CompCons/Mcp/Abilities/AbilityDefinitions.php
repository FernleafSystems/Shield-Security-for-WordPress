<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Abilities;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildScanFindings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class AbilityDefinitions {

	use PluginControllerConsumer;

	public const CATEGORY_SLUG = 'shield-security';
	public const NAME_POSTURE_OVERVIEW = 'shield/posture/overview/get';
	public const NAME_POSTURE_ATTENTION = 'shield/posture/attention/get';
	public const NAME_ACTIVITY_RECENT = 'shield/activity/recent/get';
	public const NAME_SCAN_FINDINGS = 'shield/scan/findings/get';
	public const MCP_ABILITY_NAMES = [
		self::NAME_POSTURE_OVERVIEW,
		self::NAME_POSTURE_ATTENTION,
		self::NAME_ACTIVITY_RECENT,
		self::NAME_SCAN_FINDINGS,
	];

	/**
	 * @return list<array{name:string,args:array<string,mixed>}>
	 */
	public function build() :array {
		return [
			$this->buildReadOnlyAbility(
				self::NAME_POSTURE_OVERVIEW,
				__( 'Shield Site Overview', 'wp-simple-firewall' ),
				__( 'Returns the current overall security posture summary for the site.', 'wp-simple-firewall' ),
				function ( $input = null ) :array {
					unset( $input );
					return self::con()->comps->site_query->overview();
				}
			),
			$this->buildReadOnlyAbility(
				self::NAME_POSTURE_ATTENTION,
				__( 'Shield Attention Items', 'wp-simple-firewall' ),
				__( 'Returns the current Shield attention items that need operator review.', 'wp-simple-firewall' ),
				function ( $input = null ) :array {
					unset( $input );
					return self::con()->comps->site_query->attention();
				}
			),
			$this->buildReadOnlyAbility(
				self::NAME_ACTIVITY_RECENT,
				__( 'Shield Recent Activity', 'wp-simple-firewall' ),
				__( 'Returns the recent Shield activity summary based on the current recent-events policy.', 'wp-simple-firewall' ),
				function ( $input = null ) :array {
					unset( $input );
					return self::con()->comps->site_query->recentActivity();
				}
			),
			$this->buildReadOnlyAbility(
				self::NAME_SCAN_FINDINGS,
				__( 'Shield Scan Findings', 'wp-simple-firewall' ),
				__( 'Returns the latest Shield scan findings with optional scan and item-state filters.', 'wp-simple-firewall' ),
				function ( $input = null ) {
					$input = \is_array( $input ) ? $input : [];
					try {
						return self::con()->comps->site_query->scanFindings(
							\array_values( \array_filter( \is_array( $input[ 'scan_slugs' ] ?? null ) ? $input[ 'scan_slugs' ] : [] ) ),
							\array_values( \array_filter( \is_array( $input[ 'filter_item_state' ] ?? null ) ? $input[ 'filter_item_state' ] : [] ) )
						);
					}
					catch ( \InvalidArgumentException $e ) {
						return new \WP_Error(
							'shield_mcp_invalid_input',
							$e->getMessage(),
							[ 'status' => 400 ]
						);
					}
				},
				$this->buildScanFindingsInputSchema(),
				__( 'Latest Shield scan findings query result.', 'wp-simple-firewall' )
			),
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildReadOnlyAbility(
		string $name,
		string $label,
		string $description,
		callable $executeCallback,
		array $inputSchema = [],
		string $outputDescription = ''
	) :array {
		return [
			'name' => $name,
			'args' => \array_filter( [
				'label'               => $label,
				'description'         => $description,
				'category'            => self::CATEGORY_SLUG,
				'execute_callback'    => $this->buildAuditedExecuteCallback( $name, $executeCallback ),
				'permission_callback' => fn( $input = null ) => ( new AbilityPermissions() )->canExecute( $input ),
				'output_schema'       => $this->buildObjectSchema(
					$outputDescription !== '' ? $outputDescription : $description
				),
				'meta'                => $this->buildReadOnlyMeta(),
				'input_schema'        => !empty( $inputSchema ) ? $inputSchema : null,
			], static fn( $value ) :bool => $value !== null ),
		];
	}

	private function buildAuditedExecuteCallback( string $ability, callable $executeCallback ) :callable {
		return function ( $input = null ) use ( $ability, $executeCallback ) {
			$result = $executeCallback( $input );

			self::con()->comps->events->fireEvent( 'mcp_ability_called', [
				'audit_params' => [
					'ability' => $ability,
					'status'  => $this->determineExecutionStatus( $result ),
				],
			] );

			return $result;
		};
	}

	/**
	 * @param mixed $result
	 */
	private function determineExecutionStatus( $result ) :string {
		if ( $result instanceof \WP_Error ) {
			return $result->get_error_code() === 'shield_mcp_invalid_input' ? 'invalid_input' : 'error';
		}

		return 'success';
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildScanFindingsInputSchema() :array {
		return [
			'type'        => 'object',
			'description' => __( 'Optional filters for Shield scan findings.', 'wp-simple-firewall' ),
			'properties'  => [
				'scan_slugs'        => [
					'type'        => 'array',
					'description' => __( 'Optional list of scan slugs to include.', 'wp-simple-firewall' ),
					'items'       => [
						'type' => 'string',
						'enum' => self::con()->comps->scans->getScanSlugs(),
					],
				],
				'filter_item_state' => [
					'type'        => 'array',
					'description' => __( 'Optional list of scan item states to include.', 'wp-simple-firewall' ),
					'items'       => [
						'type' => 'string',
						'enum' => BuildScanFindings::SUPPORTED_STATES,
					],
				],
			],
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildObjectSchema( string $description ) :array {
		return [
			'type'        => 'object',
			'description' => $description,
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildReadOnlyMeta() :array {
		return [
			'show_in_rest' => false,
			'annotations'  => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
		];
	}
}
