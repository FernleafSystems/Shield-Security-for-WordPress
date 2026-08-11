<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type CloakedPluginFindingState array{
 *   all:list<CloakedPluginFinding>,
 *   active:list<CloakedPluginFinding>,
 *   ignored:list<CloakedPluginFinding>,
 *   new_active:list<CloakedPluginFinding>
 * }
 */
class CloakedPluginState {

	use PluginControllerConsumer;

	public const OPT_KEY = 'hidden_plugins_alert_state';
	public const IGNORE_OPT_KEY = 'ignored_hidden_plugins';
	public const FINDINGS_OPT_KEY = 'cloaked_plugin_findings';

	/**
	 * @param list<CloakedPluginFinding> $observedFindings
	 * @param list<PluginEntry> $entries
	 * @return CloakedPluginFindingState
	 */
	public function reconcile(
		array $observedFindings,
		array $entries,
		AdminPluginVisibilitySnapshot $visibility,
		bool $authoritative
	) :array {
		$reconciled = $authoritative ? [] : $this->loadPersistedFindings( $entries, $visibility );

		foreach ( $observedFindings as $finding ) {
			$reconciled[ $finding->identityKey() ] = $finding;
		}

		$this->storeFindings( $reconciled );
		return $this->classify( \array_values( $reconciled ) );
	}

	/**
	 * @param list<CloakedPluginFinding> $findings
	 * @return CloakedPluginFindingState
	 */
	public function classify( array $findings ) :array {
		$ignoredIdentities = $this->getNormalizedIgnoredIdentities( $findings );
		$userIgnoredIdentityLookup = \array_fill_keys( $ignoredIdentities, true );
		$active = [];
		$ignored = [];
		$userIgnored = [];

		foreach ( $findings as $finding ) {
			$identity = $finding->identityKey();
			if ( $this->isShieldMuLoader( $finding ) ) {
				if ( $this->isAutoIgnored( $finding ) ) {
					$ignored[] = $finding;
				}
				else {
					$active[] = $finding;
				}
			}
			elseif ( isset( $userIgnoredIdentityLookup[ $identity ] ) ) {
				$ignored[] = $finding;
				$userIgnored[] = $finding;
			}
			else {
				$active[] = $finding;
			}
		}

		return [
			'all'        => \array_values( $findings ),
			'active'     => $active,
			'ignored'    => $ignored,
			'new_active' => $this->rememberNewCandidates(
				\array_merge( $active, $userIgnored ),
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
		$stored = $this->loadNotificationState();
		$storedByIdentity = [];
		foreach ( $stored as $record ) {
			$identity = $this->normalizeIdentity( (string)( $record[ 'identity' ] ?? '' ) );
			if ( $identity !== '' && !isset( $storedByIdentity[ $identity ] ) ) {
				$storedByIdentity[ $identity ] = $record;
			}
		}

		$next = [];
		$new = [];
		$now = \time();
		$candidateFingerprints = \array_fill_keys( \array_map(
			static fn( CloakedPluginFinding $finding ) :string => $finding->fingerprint(),
			$candidateFindings
		), true );

		foreach ( $trackedFindings as $finding ) {
			$fingerprint = $finding->fingerprint();
			$identity = $finding->identityKey();
			$priorRecord = $stored[ $fingerprint ] ?? $storedByIdentity[ $identity ] ?? null;
			if ( isset( $candidateFingerprints[ $fingerprint ] ) && $priorRecord === null ) {
				$new[] = $finding;
			}
			$next[ $fingerprint ] = [
				'identity'     => $identity,
				'notified_at' => (int)( $priorRecord[ 'notified_at' ] ?? $now ),
				'last_seen_at' => $now,
			];
		}

		$this->storeNotificationState( $next );
		return $new;
	}

	public function ignoreIdentity( string $identity, ?array $validFindings = null ) :bool {
		$identity = $this->normalizeIdentity( $identity );
		if ( $identity === '' || !$this->isValidIdentity( $identity, $validFindings ) ) {
			return false;
		}
		if ( $this->isShieldMuLoaderIdentity( $identity, $validFindings ) ) {
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

		if ( $this->isShieldMuLoaderIdentity( $identity, $validFindings ) ) {
			$currentIgnored = $this->normalizeIdentityList( $this->loadIgnoredIdentities() );
			if ( !\in_array( $identity, $currentIgnored, true ) ) {
				return false;
			}
			$ignored = \array_values( \array_diff(
				$currentIgnored,
				[ $identity ]
			) );
			$this->storeIgnoredIdentities( $ignored );
			return true;
		}

		$currentIgnored = $this->getNormalizedIgnoredIdentities( $validFindings );
		$ignored = \array_values( \array_diff(
			$currentIgnored,
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
					fn( CloakedPluginFinding $finding ) :bool => !$this->isShieldMuLoader( $finding )
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
			if ( $finding->identityKey() === $identity ) {
				return true;
			}
		}
		return false;
	}

	public function isAutoIgnored( CloakedPluginFinding $finding ) :bool {
		return ( new ShieldGeneratedMuPlugin() )->isGeneratedShieldMuLoaderFinding( $finding );
	}

	public function isShieldMuLoader( CloakedPluginFinding $finding ) :bool {
		return ( new ShieldGeneratedMuPlugin() )->isShieldMuLoaderFinding( $finding );
	}

	/**
	 * @param list<CloakedPluginFinding>|null $validFindings
	 */
	private function isShieldMuLoaderIdentity( string $identity, ?array $validFindings ) :bool {
		if ( $validFindings === null ) {
			return false;
		}

		foreach ( $validFindings as $finding ) {
			if ( $finding->identityKey() === $identity && $this->isShieldMuLoader( $finding ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param list<PluginEntry> $entries
	 * @return array<string,CloakedPluginFinding>
	 */
	private function loadPersistedFindings( array $entries, AdminPluginVisibilitySnapshot $visibility ) :array {
		$stored = self::con()->opts->optGet( self::FINDINGS_OPT_KEY );
		if ( !\is_array( $stored ) ) {
			return [];
		}

		$entryLookup = [];
		foreach ( $entries as $entry ) {
			$entryLookup[ $entry->type ][ $entry->file ] = $entry;
		}

		$findings = [];
		foreach ( $stored as $identity => $record ) {
			$finding = $this->rehydrateFinding( $identity, $record, $entryLookup, $visibility );
			if ( $finding !== null ) {
				$findings[ $identity ] = $finding;
			}
		}

		return $findings;
	}

	/**
	 * @param mixed $identity
	 * @param mixed $record
	 * @param array<string,array<string,PluginEntry>> $entryLookup
	 */
	private function rehydrateFinding(
		$identity,
		$record,
		array $entryLookup,
		AdminPluginVisibilitySnapshot $visibility
	) :?CloakedPluginFinding {
		if ( !\is_string( $identity ) || !\is_array( $record ) ) {
			return null;
		}

		$type = $record[ 'type' ] ?? null;
		$file = $record[ 'file' ] ?? null;
		$reasons = $record[ 'cloak_reasons' ] ?? null;
		$detectedAt = $record[ 'detected_at' ] ?? null;
		if ( !\is_string( $type )
			 || !\in_array( $type, PluginType::ALL, true )
			 || !\is_string( $file )
			 || $file === ''
			 || !\is_int( $detectedAt )
			 || $detectedAt <= 0
			 || !\is_array( $reasons )
			 || !$this->areCanonicalCloakReasons( $reasons ) ) {
			return null;
		}

		$entry = $entryLookup[ $type ][ $file ] ?? null;
		if ( !$entry instanceof PluginEntry ) {
			return null;
		}

		$finding = new CloakedPluginFinding(
			$entry,
			$reasons,
			$type === PluginType::MustUse || $visibility->isActive( $file ),
			$type === PluginType::MustUse || $visibility->isNetworkActive( $file ),
			$detectedAt
		);

		return $finding->identityKey() === $identity ? $finding : null;
	}

	private function areCanonicalCloakReasons( array $reasons ) :bool {
		if ( empty( $reasons ) || \array_keys( $reasons ) !== \range( 0, \count( $reasons ) - 1 ) ) {
			return false;
		}

		$canonical = [];
		foreach ( CloakReason::ALL as $reason ) {
			if ( \in_array( $reason, $reasons, true ) ) {
				$canonical[] = $reason;
			}
		}

		return $canonical === $reasons;
	}

	/**
	 * @param array<string,CloakedPluginFinding> $findings
	 */
	private function storeFindings( array $findings ) :void {
		$stored = [];
		foreach ( $findings as $identity => $finding ) {
			$stored[ $identity ] = [
				'type'          => $finding->entry->type,
				'file'          => $finding->entry->file,
				'cloak_reasons' => \array_values( $finding->cloakReasons ),
				'detected_at'   => $finding->detectedAt,
			];
		}

		self::con()->opts->optSet( self::FINDINGS_OPT_KEY, $stored )->store();
	}

	private function loadNotificationState() :array {
		$state = self::con()->opts->optGet( self::OPT_KEY );
		if ( !\is_array( $state ) ) {
			return [];
		}

		return \array_filter(
			$state,
			static fn( $item ) :bool => \is_array( $item ) && isset( $item[ 'notified_at' ] )
		);
	}

	private function storeNotificationState( array $state ) :void {
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
