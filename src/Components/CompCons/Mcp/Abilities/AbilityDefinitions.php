<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Abilities;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildScanFindings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class AbilityDefinitions {

	use PluginControllerConsumer;

	public const CATEGORY_SLUG = 'shield-security';

	/**
	 * @return list<array{name:string,args:array<string,mixed>}>
	 */
	public function build() :array {
		return [
			$this->buildReadOnlyAbility(
				'shield/posture/overview/get',
				__( 'Shield Site Overview', 'wp-simple-firewall' ),
				__( 'Returns the current overall security posture summary for the site.', 'wp-simple-firewall' ),
				fn() :array => self::con()->comps->site_query->overview()
			),
			$this->buildReadOnlyAbility(
				'shield/posture/attention/get',
				__( 'Shield Attention Items', 'wp-simple-firewall' ),
				__( 'Returns the current Shield attention items that need operator review.', 'wp-simple-firewall' ),
				fn() :array => self::con()->comps->site_query->attention()
			),
			$this->buildReadOnlyAbility(
				'shield/activity/recent/get',
				__( 'Shield Recent Activity', 'wp-simple-firewall' ),
				__( 'Returns the recent Shield activity summary based on the current recent-events policy.', 'wp-simple-firewall' ),
				fn() :array => self::con()->comps->site_query->recentActivity()
			),
			$this->buildReadOnlyAbility(
				'shield/scan/findings/get',
				__( 'Shield Scan Findings', 'wp-simple-firewall' ),
				__( 'Returns the latest Shield scan findings with optional scan and item-state filters.', 'wp-simple-firewall' ),
				function ( $input = null ) :array {
					$input = \is_array( $input ) ? $input : [];
					return self::con()->comps->site_query->scanFindings(
						\array_values( \array_filter( \is_array( $input[ 'scan_slugs' ] ?? null ) ? $input[ 'scan_slugs' ] : [] ) ),
						\array_values( \array_filter( \is_array( $input[ 'filter_item_state' ] ?? null ) ? $input[ 'filter_item_state' ] : [] ) )
					);
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
				'execute_callback'    => $executeCallback,
				'permission_callback' => fn( $input = null ) => ( new AbilityPermissions() )->canExecute( $input ),
				'output_schema'       => $this->buildObjectSchema(
					$outputDescription !== '' ? $outputDescription : $description
				),
				'meta'                => $this->buildReadOnlyMeta(),
				'input_schema'        => !empty( $inputSchema ) ? $inputSchema : null,
			], static fn( $value ) :bool => $value !== null ),
		];
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
