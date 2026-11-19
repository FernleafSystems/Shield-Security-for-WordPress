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
 * @phpstan-type CloakedPluginIgnoreRecord array{
 *   mode:'path'|'contents',
 *   contents_hash:string,
 *   ignored_at:int
 * }
 */
class CloakedPluginState {

	use PluginControllerConsumer;

	public const OPT_KEY = 'hidden_plugins_alert_state';
	/**
	 * The identities of ignored plugins, as a plain list that every version of Shield can read.
	 */
	public const IGNORE_OPT_KEY = 'ignored_hidden_plugins';
	/**
	 * How each ignored plugin was ignored, by identity, beside that list.
	 */
	public const IGNORE_MODES_OPT_KEY = 'cloaked_plugin_ignore_modes';
	public const FINDINGS_OPT_KEY = 'cloaked_plugin_findings';

	/**
	 * Ignored wherever the plugin's file is, whatever it contains.
	 */
	public const IGNORE_MODE_PATH = 'path';
	/**
	 * Ignored only while the plugin's main file holds what it held when it was ignored.
	 */
	public const IGNORE_MODE_CONTENTS = 'contents';
	public const IGNORE_MODES = [
		self::IGNORE_MODE_PATH,
		self::IGNORE_MODE_CONTENTS,
	];

	/**
	 * @param list<CloakedPluginFinding> $observedFindings
	 * @param list<PluginEntry> $entries
	 * @return CloakedPluginFindingState
	 */
	public function reconcile(
		array $observedFindings,
		array $entries,
		AdminPluginVisibilitySnapshot $visibility
	) :array {
		$replaceTypes = \array_filter( PluginType::ALL, [ $visibility, 'canReplaceFindings' ] );
		$reconciled = \array_filter(
			$this->loadPersistedFindings( $entries, $visibility ),
			static fn( CloakedPluginFinding $finding ) :bool => !\in_array( $finding->entry->type, $replaceTypes, true )
		);

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
		// An ignore stands until the user stops it, whether or not its plugin is a finding right now: a plugin
		// briefly missing or visible (during an update, say) mustn't lose it.
		$records = $this->ignoreRecords();
		$recordsChanged = false;
		$changedSinceIgnored = [];
		$active = [];
		$ignored = [];
		$userIgnored = [];

		foreach ( $findings as $finding ) {
			$identity = $finding->identityKey();
			$record = $records[ $identity ] ?? null;
			if ( $this->isShieldMuLoader( $finding ) ) {
				// Shield's own loader is never ignored by hand, only while it's exactly what Shield wrote.
				if ( $record !== null ) {
					unset( $records[ $identity ] );
					$recordsChanged = true;
				}
				if ( $this->isAutoIgnored( $finding ) ) {
					$ignored[] = $finding;
				}
				else {
					$active[] = $finding;
				}
			}
			elseif ( $record !== null && $this->isIgnoreStillApplicable( $record, $finding ) ) {
				$ignored[] = $finding;
				$userIgnored[] = $finding;
			}
			else {
				if ( $record !== null ) {
					// Ignored until it changed, and it has: it's flagged, and alerted, as though found anew.
					unset( $records[ $identity ] );
					$recordsChanged = true;
					$changedSinceIgnored[] = $identity;
				}
				$active[] = $finding;
			}
		}

		if ( $recordsChanged ) {
			$this->storeIgnoreRecords( $records );
		}

		return [
			'all'        => \array_values( $findings ),
			'active'     => $active,
			'ignored'    => $ignored,
			'new_active' => $this->rememberNewCandidates(
				\array_merge( $active, $userIgnored ),
				$active,
				$changedSinceIgnored
			),
		];
	}

	/**
	 * @param list<CloakedPluginFinding> $trackedFindings
	 * @param list<CloakedPluginFinding> $candidateFindings
	 * @param list<string> $forgetIdentities plugins whose earlier alerts no longer count, so they're alerted again
	 * @return list<CloakedPluginFinding>
	 */
	private function rememberNewCandidates( array $trackedFindings, array $candidateFindings, array $forgetIdentities ) :array {
		$forget = \array_fill_keys( $forgetIdentities, true );
		$stored = [];
		$storedByIdentity = [];
		foreach ( $this->loadNotificationState() as $storedFingerprint => $record ) {
			$identity = $this->normalizeIdentity( (string)( $record[ 'identity' ] ?? '' ) );
			if ( isset( $forget[ $identity ] ) ) {
				continue;
			}
			$stored[ $storedFingerprint ] = $record;
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

	/**
	 * Ignores one of the current findings. Ignoring it until it changes records the contents the user reviewed,
	 * which must still be what's on disk, so a file changed since the page was drawn isn't ignored unseen.
	 * @param list<CloakedPluginFinding> $currentFindings
	 */
	public function ignoreIdentity(
		string $identity,
		array $currentFindings,
		string $mode,
		string $reviewedContentsHash = ''
	) :bool {
		$finding = $this->currentFinding( $identity, $currentFindings );
		if ( $finding === null || $this->isShieldMuLoader( $finding ) || !\in_array( $mode, self::IGNORE_MODES, true ) ) {
			return false;
		}

		$contentsHash = '';
		if ( $mode === self::IGNORE_MODE_CONTENTS ) {
			$contentsHash = $finding->contentsHash();
			if ( $contentsHash === '' || !\hash_equals( $contentsHash, $reviewedContentsHash ) ) {
				return false;
			}
		}

		$records = $this->ignoreRecords();
		$records[ $finding->identityKey() ] = $this->ignoreRecord( $mode, $contentsHash, \time() );
		$this->storeIgnoreRecords( $records );
		return true;
	}

	/**
	 * @param list<CloakedPluginFinding> $currentFindings
	 */
	public function unignoreIdentity( string $identity, array $currentFindings ) :bool {
		$finding = $this->currentFinding( $identity, $currentFindings );
		if ( $finding === null ) {
			return false;
		}

		$records = $this->ignoreRecords();
		$identity = $finding->identityKey();
		if ( !isset( $records[ $identity ] ) ) {
			// Nothing to stop. That's fine for a plugin the user could ignore, but Shield's own loader never can be.
			return !$this->isShieldMuLoader( $finding );
		}

		unset( $records[ $identity ] );
		$this->storeIgnoreRecords( $records );
		return true;
	}

	/**
	 * Plugins WordPress has deleted take their ignores with them, so one installed later at the same place is
	 * reviewed afresh.
	 * @param list<string> $identities as CloakedPluginFinding::IdentityFor() gives them
	 */
	public function forgetIgnores( array $identities ) :void {
		$records = $this->ignoreRecords();
		$remaining = \array_diff_key( $records, \array_flip( $identities ) );
		if ( $remaining !== $records ) {
			$this->storeIgnoreRecords( $remaining );
		}
	}

	/**
	 * Each ignored plugin's record, by identity.
	 *
	 * What's ignored is the plain list of identities that every version of Shield has kept, so a site that goes back
	 * to an earlier version still has its ignores. How each was ignored is kept beside it. An identity with nothing
	 * beside it was ignored before there was a choice, when every ignore held wherever the plugin's file was, whatever
	 * it contained, so it carries on as exactly that and nothing anyone ignored comes back.
	 * @return array<string,CloakedPluginIgnoreRecord>
	 */
	public function ignoreRecords() :array {
		$list = self::con()->opts->optGet( self::IGNORE_OPT_KEY );
		$list = \is_array( $list ) ? $list : [];
		$modes = self::con()->opts->optGet( self::IGNORE_MODES_OPT_KEY );
		$modes = \is_array( $modes ) ? $modes : [];

		$records = [];
		foreach ( $list as $identity ) {
			$identity = \is_string( $identity ) ? $this->normalizeIdentity( $identity ) : '';
			if ( $identity !== '' && !isset( $records[ $identity ] ) ) {
				$records[ $identity ] = $this->normalizeIgnoreRecord( $modes[ $identity ] ?? null )
										?? $this->ignoreRecord( self::IGNORE_MODE_PATH, '', 0 );
			}
		}

		if ( \array_keys( $records ) !== $list || $this->modesToStore( $records ) !== $modes ) {
			$this->storeIgnoreRecords( $records );
		}

		return $records;
	}

	private function isAutoIgnored( CloakedPluginFinding $finding ) :bool {
		return ( new ShieldGeneratedMuPlugin() )->isGeneratedShieldMuLoaderFinding( $finding );
	}

	public function isShieldMuLoader( CloakedPluginFinding $finding ) :bool {
		return ( new ShieldGeneratedMuPlugin() )->isShieldMuLoaderFinding( $finding );
	}

	/**
	 * @param list<CloakedPluginFinding> $currentFindings
	 */
	private function currentFinding( string $identity, array $currentFindings ) :?CloakedPluginFinding {
		$identity = $this->normalizeIdentity( $identity );
		foreach ( $currentFindings as $finding ) {
			if ( $identity !== '' && $finding->identityKey() === $identity ) {
				return $finding;
			}
		}
		return null;
	}

	/**
	 * @param CloakedPluginIgnoreRecord $record
	 */
	private function isIgnoreStillApplicable( array $record, CloakedPluginFinding $finding ) :bool {
		if ( $record[ 'mode' ] === self::IGNORE_MODE_PATH ) {
			return true;
		}

		// A file that can't be read just now hasn't been shown to have changed, so its ignore stands until it can be.
		$current = $finding->contentsHash();
		return $current === '' || \hash_equals( $record[ 'contents_hash' ], $current );
	}

	/**
	 * @param mixed $record
	 * @return CloakedPluginIgnoreRecord|null
	 */
	private function normalizeIgnoreRecord( $record ) :?array {
		if ( !\is_array( $record ) ) {
			return null;
		}

		$mode = $record[ 'mode' ] ?? null;
		$hash = $record[ 'contents_hash' ] ?? null;
		$ignoredAt = $record[ 'ignored_at' ] ?? null;
		if ( !\is_string( $hash ) || !\is_int( $ignoredAt ) || $ignoredAt < 0 ) {
			return null;
		}

		if ( $mode === self::IGNORE_MODE_PATH ) {
			return $this->ignoreRecord( self::IGNORE_MODE_PATH, '', $ignoredAt );
		}
		if ( $mode === self::IGNORE_MODE_CONTENTS && \preg_match( '/^[a-f0-9]{64}$/D', $hash ) === 1 ) {
			return $this->ignoreRecord( self::IGNORE_MODE_CONTENTS, $hash, $ignoredAt );
		}
		return null;
	}

	/**
	 * @phpstan-param value-of<self::IGNORE_MODES> $mode
	 * @return CloakedPluginIgnoreRecord
	 */
	private function ignoreRecord( string $mode, string $contentsHash, int $ignoredAt ) :array {
		return [
			'mode'          => $mode,
			'contents_hash' => $contentsHash,
			'ignored_at'    => $ignoredAt,
		];
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

	/**
	 * @param array<string,CloakedPluginIgnoreRecord> $records
	 */
	private function storeIgnoreRecords( array $records ) :void {
		self::con()->opts
			->optSet( self::IGNORE_OPT_KEY, \array_keys( $records ) )
			->optSet( self::IGNORE_MODES_OPT_KEY, $this->modesToStore( $records ) )
			->store();
	}

	/**
	 * Only what differs from an identity with nothing beside it.
	 * @param array<string,CloakedPluginIgnoreRecord> $records
	 * @return array<string,CloakedPluginIgnoreRecord>
	 */
	private function modesToStore( array $records ) :array {
		$carriedOver = $this->ignoreRecord( self::IGNORE_MODE_PATH, '', 0 );
		$modes = \array_filter( $records, static fn( array $record ) :bool => $record !== $carriedOver );
		// OptsHandler sorts stored array keys. Compare and write the same order so a read does not rewrite the modes.
		\ksort( $modes );
		return $modes;
	}

	private function normalizeIdentity( string $identity ) :string {
		$identity = \strtolower( \trim( $identity ) );
		return \preg_match( '/^[a-f0-9]{40}$/', $identity ) === 1 ? $identity : '';
	}
}
