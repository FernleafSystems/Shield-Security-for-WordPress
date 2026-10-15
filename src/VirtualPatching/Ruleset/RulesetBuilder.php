<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset;

use FernleafSystems\Orle\VirtualPatchingRules\PatchValidator;

/**
 * Turns virtual patches into the ruleset the request passes use. Only valid patches for a plugin or theme that
 * is installed at an affected version are kept; the rest are reported with the reason.
 *
 * A patch for an installed but inactive plugin is kept: its files can still be requested directly, and the
 * patch's own plugin_active condition decides whether activity matters.
 */
class RulesetBuilder {

	private PatchValidator $validator;

	private AffectedVersions $versions;

	public function __construct( ?PatchValidator $validator = null, ?AffectedVersions $versions = null ) {
		$this->validator = $validator ?? new PatchValidator();
		$this->versions = $versions ?? new AffectedVersions();
	}

	/**
	 * @param mixed[] $patches decoded JSON with objects kept as objects, as PatchValidator requires
	 * @param array   $extra   further top-level ruleset keys, such as bypass settings
	 * @return array{ruleset: array, rejected: array<string, string>, not_applicable: array<string, string>}
	 */
	public function build( array $patches, SiteState $site, int $generatedAt, array $extra = [] ) :array {
		$rules = [];
		$rejected = [];
		$notApplicable = [];

		foreach ( \array_values( $patches ) as $index => $patch ) {
			$id = $patch instanceof \stdClass && \is_string( $patch->candidateRevisionId ?? null )
				? $patch->candidateRevisionId : '#'.$index;

			$problems = $patch instanceof \stdClass ? $this->validator->validate( $patch ) : [ 'a patch must be an object' ];
			if ( !empty( $problems ) ) {
				$rejected[ $id ] = \implode( '; ', $problems );
				continue;
			}
			$patch = \json_decode( (string)\json_encode( $patch ), true );
			if ( isset( $rules[ $id ] ) ) {
				$rejected[ $id ] = 'duplicate candidateRevisionId';
				continue;
			}

			$target = $patch[ 'targetExtension' ];
			$installed = $site->installedVersion( $target[ 'kind' ], $target[ 'slug' ] );
			if ( $installed === null ) {
				$notApplicable[ $id ] = 'not installed';
				continue;
			}
			if ( !$this->versions->isAffected( $installed, $target[ 'affectedVersionRanges' ] ) ) {
				$notApplicable[ $id ] = 'installed version '.$installed.' is not affected';
				continue;
			}

			$rules[ $id ] = [
				'id'         => $id,
				'advisories' => \array_values( \array_unique( \array_map(
					fn( array $vulnerabilityId ) => $vulnerabilityId[ 'identifier' ],
					$patch[ 'publicVulnerabilityIds' ]
				) ) ),
				'conditions' => $patch[ 'virtualPatch' ][ 'matcher' ][ 'conditions' ],
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
