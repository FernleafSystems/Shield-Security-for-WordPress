<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset;

/**
 * Turns checked virtual patch rules into the ruleset the request passes use. Only rules for a plugin or theme that
 * is installed at an affected version are kept; the rest are reported with the reason. Each rule keeps its mode:
 * a log rule records what it would have blocked, and a block rule blocks.
 *
 * A rule for an installed but inactive plugin is kept: its files can still be requested directly, and the rule's
 * own plugin_active condition decides whether activity matters.
 */
class RulesetBuilder {

	public const MODES = [ 'log', 'block' ];

	private AffectedVersions $versions;

	public function __construct( ?AffectedVersions $versions = null ) {
		$this->versions = $versions ?? new AffectedVersions();
	}

	/**
	 * @param array[] $entries each { kind, slug, mode, rule }, the rule already checked and decoded to arrays: its
	 *                         id, publicVulnerabilityIds, affectedVersionRanges and conditions
	 * @param array   $extra   further top-level ruleset keys, such as bypass settings
	 * @return array{ruleset: array, rejected: array<string, string>, not_applicable: array<string, string>}
	 */
	public function build( array $entries, SiteState $site, int $generatedAt, array $extra = [] ) :array {
		$rules = [];
		$rejected = [];
		$notApplicable = [];

		foreach ( $entries as $entry ) {
			$rule = $entry[ 'rule' ];
			$id = (string)$rule[ 'id' ];
			if ( isset( $rules[ $id ] ) ) {
				$rejected[ $id ] = 'duplicate rule id';
				continue;
			}
			if ( !\in_array( $entry[ 'mode' ], self::MODES, true ) ) {
				$rejected[ $id ] = 'mode must be log or block';
				continue;
			}

			$installed = $site->installedVersion( $entry[ 'kind' ], $entry[ 'slug' ] );
			if ( $installed === null ) {
				$notApplicable[ $id ] = 'not installed';
				continue;
			}
			if ( !$this->versions->isAffected( $installed, $rule[ 'affectedVersionRanges' ] ) ) {
				$notApplicable[ $id ] = 'installed version '.$installed.' is not affected';
				continue;
			}

			$rules[ $id ] = [
				'id'         => $id,
				'mode'       => $entry[ 'mode' ],
				'advisories' => \array_values( \array_unique( \array_map(
					fn( array $vulnerabilityId ) => $vulnerabilityId[ 'identifier' ],
					$rule[ 'publicVulnerabilityIds' ]
				) ) ),
				'conditions' => $rule[ 'conditions' ],
			];
		}

		return [
			'ruleset'        => \array_merge( $extra, [
				'format'         => RulesetFile::FORMAT,
				'generated_at'   => $generatedAt,
				'active_plugins' => \array_values( $site->activePlugins ),
				'active_themes'  => \array_values( $site->activeThemes ),
				'rules'          => \array_values( $rules ),
			] ),
			'rejected'       => $rejected,
			'not_applicable' => $notApplicable,
		];
	}
}
