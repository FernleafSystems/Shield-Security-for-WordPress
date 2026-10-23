<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Delivery;

/**
 * What the site remembers about rule delivery, apart from the files themselves, in its own option so an export
 * never rewrites it: for each plugin or theme, when it was first and last asked for, when it last refreshed
 * successfully, the SHA-256 of the file it verified and stored, and its last problem; whether the API has retired
 * this Shield's rule schema; and the problems already reported. A file's ordering is never kept here; it is read
 * from the file.
 */
class FeedState {

	public const OPTION = 'icwp_wpsf_virtual_patching_feed';

	/**
	 * A plugin or theme with no successful refresh for this long is reported.
	 */
	public const STALE_AFTER = 3*\DAY_IN_SECONDS;

	/**
	 * @var array<string, array{first_at: int, fetched_at: int, refreshed_at: ?int, sha256: ?string, error: ?string}> by "kind/slug"
	 */
	public array $assets = [];

	public ?int $schemaRetiredAt = null;

	/**
	 * When the API stopped answering at all, such as behind an outbound firewall; cleared by any answer.
	 */
	public ?int $unreachableSince = null;

	/**
	 * @var array<string, string> each reported problem's reason, by problem key
	 */
	public array $reported = [];

	public static function load() :self {
		$state = new self();
		$stored = get_option( self::OPTION );
		if ( !\is_array( $stored ) ) {
			return $state;
		}
		foreach ( \is_array( $stored[ 'assets' ] ?? null ) ? $stored[ 'assets' ] : [] as $key => $asset ) {
			if ( \is_string( $key ) && \is_array( $asset ) ) {
				$state->assets[ $key ] = [
					'first_at'     => (int)( $asset[ 'first_at' ] ?? $asset[ 'fetched_at' ] ?? 0 ),
					'fetched_at'   => (int)( $asset[ 'fetched_at' ] ?? 0 ),
					'refreshed_at' => \is_int( $asset[ 'refreshed_at' ] ?? null ) ? $asset[ 'refreshed_at' ] : null,
					'sha256'       => \is_string( $asset[ 'sha256' ] ?? null ) ? $asset[ 'sha256' ] : null,
					'error'        => \is_string( $asset[ 'error' ] ?? null ) ? $asset[ 'error' ] : null,
				];
			}
		}
		$state->schemaRetiredAt = \is_int( $stored[ 'schema_retired_at' ] ?? null ) ? $stored[ 'schema_retired_at' ] : null;
		$state->unreachableSince = \is_int( $stored[ 'unreachable_since' ] ?? null ) ? $stored[ 'unreachable_since' ] : null;
		$state->reported = \is_array( $stored[ 'reported' ] ?? null ) ? \array_map( 'strval', $stored[ 'reported' ] ) : [];
		return $state;
	}

	public function save() :void {
		update_option( self::OPTION, [
			'assets'            => $this->assets,
			'schema_retired_at' => $this->schemaRetiredAt,
			'unreachable_since' => $this->unreachableSince,
			'reported'          => $this->reported,
		], false );
	}

	/**
	 * @return array{first_at: int, fetched_at: int, refreshed_at: ?int, sha256: ?string, error: ?string}|null
	 */
	public function asset( string $kind, string $slug ) :?array {
		return $this->assets[ $kind.'/'.$slug ] ?? null;
	}

	public function record( string $kind, string $slug, int $now, bool $refreshed, ?string $sha256, ?string $error ) :void {
		$previous = $this->asset( $kind, $slug );
		$this->assets[ $kind.'/'.$slug ] = [
			'first_at'     => $previous[ 'first_at' ] ?? $now,
			'fetched_at'   => $now,
			'refreshed_at' => $refreshed ? $now : ( $previous[ 'refreshed_at' ] ?? null ),
			'sha256'       => $sha256,
			'error'        => $error,
		];
	}

	public function forget( string $kind, string $slug ) :void {
		unset( $this->assets[ $kind.'/'.$slug ] );
	}

	/**
	 * The problems to report now: a refused or unsaved file, no successful refresh for STALE_AFTER since the first
	 * attempt, and a retired schema or an API that hasn't answered for STALE_AFTER, either of which alone is
	 * reported while it lasts, since no file can refresh meanwhile.
	 *
	 * @return array<string, string> reason by problem key
	 */
	public function problems( int $now ) :array {
		if ( $this->schemaRetiredAt !== null ) {
			return [ 'schema' => 'this version of Shield no longer receives virtual patches; update Shield to keep receiving them' ];
		}
		if ( $this->unreachableSince !== null && $now - $this->unreachableSince > self::STALE_AFTER ) {
			return [ 'service' => 'the virtual patching service could not be reached for 3 days' ];
		}
		// During an outage each asset is measured to its start, so the outage is reported once, not per asset.
		$until = $this->unreachableSince ?? $now;
		$problems = [];
		foreach ( $this->assets as $key => $asset ) {
			if ( $asset[ 'error' ] !== null ) {
				$problems[ $key ] = $asset[ 'error' ];
			}
			elseif ( $until - ( $asset[ 'refreshed_at' ] ?? $asset[ 'first_at' ] ) > self::STALE_AFTER ) {
				$problems[ $key ] = 'no successful update for 3 days';
			}
		}
		return $problems;
	}
}
