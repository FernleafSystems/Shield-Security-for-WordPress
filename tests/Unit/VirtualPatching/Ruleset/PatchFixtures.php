<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Ruleset;

trait PatchFixtures {

	private static function patch( array $conditions = [], array $overrides = [] ) :array {
		return \array_replace( [
			'contractVersion'        => 2,
			'candidateRevisionId'    => 'candidate-1',
			'targetExtension'        => [
				'kind'                  => 'plugin',
				'slug'                  => 'example',
				'affectedVersionRanges' => [
					[
						'firstAffectedVersion'   => '1.0',
						'lastAffectedVersion'    => '1.4.2',
						'firstKnownFixedVersion' => '1.4.3',
					],
				],
			],
			'publicVulnerabilityIds' => [
				[ 'authority' => 'CVE', 'identifier' => 'CVE-2026-0001' ],
			],
			'publicSources'          => [],
			'reviewRationale'        => 'Blocks the affected export request.',
			'virtualPatch'           => [
				'action'  => 'block',
				'matcher' => [
					'conditions' => $conditions ?: [
						[ 'kind' => 'path', 'negate' => false, 'operator' => 'equals', 'value' => '/wp-admin/admin-ajax.php' ],
						[ 'kind' => 'parameter_value', 'negate' => false, 'source' => 'form', 'name' => 'action', 'operator' => 'equals', 'value' => 'example_export' ],
					],
				],
			],
		], $overrides );
	}
}
