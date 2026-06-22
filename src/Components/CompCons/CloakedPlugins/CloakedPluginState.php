<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU\MUHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type CloakedPluginFindingState array{
 *   all:list<CloakedPluginFinding>,
 *   active:list<CloakedPluginFinding>,
 *   ignored:list<CloakedPluginFinding>,
 *   system_suppressed:list<CloakedPluginFinding>,
 *   new_active:list<CloakedPluginFinding>
 * }
 */
class CloakedPluginState {

	use PluginControllerConsumer;

	public const OPT_KEY = 'hidden_plugins_alert_state';
	public const IGNORE_OPT_KEY = 'ignored_hidden_plugins';

	/**
	 * @param list<CloakedPluginFinding> $findings
	 * @return CloakedPluginFindingState
	 */
	public function classify( array $findings ) :array {
		$ignoredIdentities = \array_fill_keys( $this->getNormalizedIgnoredIdentities( $findings ), true );
		$active = [];
		$ignored = [];
		$systemSuppressed = [];

		foreach ( $findings as $finding ) {
			if ( $this->isSystemSuppressed( $finding ) ) {
				$systemSuppressed[] = $finding;
			}
			elseif ( isset( $ignoredIdentities[ $finding->identityKey() ] ) ) {
				$ignored[] = $finding;
			}
			else {
				$active[] = $finding;
			}
		}

		return [
			'all'               => \array_values( $findings ),
			'active'            => $active,
			'ignored'           => $ignored,
			'system_suppressed' => $systemSuppressed,
			'new_active'        => $this->rememberNewCandidates(
				\array_merge( $active, $ignored ),
				$active
			),
		];
	}

	/**
	 * @param list<CloakedPluginFinding> $findings
	 * @return list<CloakedPluginFinding>
	 */
	public function rememberNew( array $findings ) :array {
		return $this->rememberNewCandidates( $findings, $findings );
	}

	/**
	 * @param list<CloakedPluginFinding> $trackedFindings
	 * @param list<CloakedPluginFinding> $candidateFindings
	 * @return list<CloakedPluginFinding>
	 */
	private function rememberNewCandidates( array $trackedFindings, array $candidateFindings ) :array {
		$stored = $this->load();
		$next = [];
		$new = [];
		$now = \time();
		$candidateFingerprints = \array_fill_keys( \array_map(
			static fn( CloakedPluginFinding $finding ) :string => $finding->fingerprint(),
			$candidateFindings
		), true );

		foreach ( $trackedFindings as $finding ) {
			$fingerprint = $finding->fingerprint();
			if ( isset( $candidateFingerprints[ $fingerprint ] ) && !isset( $stored[ $fingerprint ] ) ) {
				$new[] = $finding;
			}
			$next[ $fingerprint ] = [
				'notified_at' => (int)( $stored[ $fingerprint ][ 'notified_at' ] ?? $now ),
				'last_seen_at' => $now,
			];
		}

		$this->store( $next );
		return $new;
	}

	public function ignoreIdentity( string $identity, ?array $validFindings = null ) :bool {
		$identity = $this->normalizeIdentity( $identity );
		if ( $identity === '' || !$this->isValidIdentity( $identity, $validFindings ) ) {
			return false;
		}

		$ignored = $this->getNormalizedIgnoredIdentities( $validFindings );
		$ignored[] = $identity;
		$this->storeIgnoredIdentities( $this->normalizeIdentityList( $ignored ) );
		return true;
	}

	public function unignoreIdentity( string $identity, ?array $validFindings = null ) :bool {
		$identity = $this->normalizeIdentity( $identity );
		if ( $identity === '' || !$this->isValidIdentity( $identity, $validFindings ) ) {
			return false;
		}

		$ignored = \array_values( \array_diff(
			$this->getNormalizedIgnoredIdentities( $validFindings ),
			[ $identity ]
		) );
		$this->storeIgnoredIdentities( $ignored );
		return true;
	}

	/**
	 * @param list<CloakedPluginFinding>|null $validFindings
	 * @return list<string>
	 */
	public function getNormalizedIgnoredIdentities( ?array $validFindings = null ) :array {
		$stored = $this->loadIgnoredIdentities();
		$normalized = $this->normalizeIdentityList( $stored );

		if ( $validFindings !== null ) {
			$validIdentities = \array_fill_keys( \array_map(
				static fn( CloakedPluginFinding $finding ) :string => $finding->identityKey(),
				\array_values( \array_filter(
					$validFindings,
					fn( CloakedPluginFinding $finding ) :bool => !$this->isSystemSuppressed( $finding )
				) )
			), true );
			$normalized = \array_values( \array_filter(
				$normalized,
				static fn( string $identity ) :bool => isset( $validIdentities[ $identity ] )
			) );
		}

		if ( $normalized !== $stored ) {
			$this->storeIgnoredIdentities( $normalized );
		}

		return $normalized;
	}

	/**
	 * @param list<CloakedPluginFinding>|null $validFindings
	 */
	private function isValidIdentity( string $identity, ?array $validFindings ) :bool {
		if ( $validFindings === null ) {
			return true;
		}

		foreach ( $validFindings as $finding ) {
			if ( !$this->isSystemSuppressed( $finding ) && $finding->identityKey() === $identity ) {
				return true;
			}
		}
		return false;
	}

	private function isSystemSuppressed( CloakedPluginFinding $finding ) :bool {
		return $finding->entry->type === PluginType::MustUse
			   && $finding->entry->file === MUHandler::PLUGIN_FILE_NAME
			   && self::con()->comps->mu->isGeneratedMuLoader( $finding->entry->file, $finding->entry->path );
	}

	private function load() :array {
		$state = self::con()->opts->optGet( self::OPT_KEY );
		if ( !\is_array( $state ) ) {
			return [];
		}

		return \array_filter(
			$state,
			static fn( $item ) :bool => \is_array( $item ) && isset( $item[ 'notified_at' ] )
		);
	}

	private function store( array $state ) :void {
		self::con()->opts->optSet( self::OPT_KEY, $state )->store();
	}

	private function loadIgnoredIdentities() :array {
		$state = self::con()->opts->optGet( self::IGNORE_OPT_KEY );
		return \is_array( $state ) ? $state : [];
	}

	/**
	 * @param list<string> $identities
	 */
	private function storeIgnoredIdentities( array $identities ) :void {
		self::con()->opts->optSet( self::IGNORE_OPT_KEY, $identities )->store();
	}

	/**
	 * @param array<mixed> $identities
	 * @return list<string>
	 */
	private function normalizeIdentityList( array $identities ) :array {
		return \array_values( \array_unique( \array_filter(
			\array_map( fn( $identity ) :string => $this->normalizeIdentity( (string)$identity ), $identities ),
			static fn( string $identity ) :bool => $identity !== ''
		) ) );
	}

	private function normalizeIdentity( string $identity ) :string {
		$identity = \strtolower( \trim( $identity ) );
		return \preg_match( '/^[a-f0-9]{40}$/', $identity ) === 1 ? $identity : '';
	}
}
