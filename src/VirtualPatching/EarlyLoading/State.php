<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * What Shield has installed for early loading.
 */
final class State {

	public InstallStatus $status = InstallStatus::Off;

	/**
	 * Null when nothing was installed, for example when a stub is found with no recorded state.
	 */
	public ?MechanismKind $mechanism = null;

	/**
	 * The auto_prepend_file configured before installation, chained from the stub.
	 */
	public ?string $hostPrepend = null;

	/**
	 * When the current status began.
	 */
	public int $since = 0;

	/**
	 * How long PHP may keep using a previous configuration.
	 */
	public int $cacheSeconds = 0;

	/**
	 * Whether the last verification found the stub running.
	 */
	public bool $confirmed = false;

	/**
	 * Whether the site was checked after PHP's configuration cache had expired.
	 */
	public bool $settled = false;

	public int $checkedAt = 0;

	public ?InstallFailure $error = null;

	public string $errorFile = '';

	/**
	 * Each configuration file Shield edited, with its content before and after, so an interrupted or
	 * failed install can be put back. Kept until the install has settled.
	 * @var array<string,array{before:?string,after:string}>
	 */
	public array $edits = [];

	/**
	 * Every configuration file Shield's block was written to, so removal finds them even when the request
	 * can't work out the paths itself (WP-CLI, for example).
	 * @var string[]
	 */
	public array $files = [];

	/**
	 * Reads the stored option, which may be missing, from an older version, or damaged.
	 */
	public static function fromArray( array $data ) :self {
		$state = new self();
		$state->status = InstallStatus::tryFrom( (string)( $data[ 'status' ] ?? '' ) ) ?? InstallStatus::Off;
		$state->mechanism = MechanismKind::tryFrom( (string)( $data[ 'mechanism' ] ?? '' ) );
		$state->hostPrepend = \is_string( $data[ 'host_prepend' ] ?? null ) ? $data[ 'host_prepend' ] : null;
		$state->since = (int)( $data[ 'since' ] ?? 0 );
		$state->cacheSeconds = (int)( $data[ 'cache_seconds' ] ?? 0 );
		$state->confirmed = (bool)( $data[ 'confirmed' ] ?? false );
		$state->settled = (bool)( $data[ 'settled' ] ?? false );
		$state->checkedAt = (int)( $data[ 'checked_at' ] ?? 0 );
		$state->error = InstallFailure::tryFrom( (string)( $data[ 'error' ] ?? '' ) );
		$state->errorFile = (string)( $data[ 'error_file' ] ?? '' );

		foreach ( \is_array( $data[ 'edits' ] ?? null ) ? $data[ 'edits' ] : [] as $path => $edit ) {
			if ( \is_string( $path ) && \is_array( $edit ) && \is_string( $edit[ 'after' ] ?? null )
				 && ( !isset( $edit[ 'before' ] ) || \is_string( $edit[ 'before' ] ) ) ) {
				$state->edits[ $path ] = [ 'before' => $edit[ 'before' ] ?? null, 'after' => $edit[ 'after' ] ];
			}
		}
		$state->files = \array_values( \array_filter(
			\is_array( $data[ 'files' ] ?? null ) ? $data[ 'files' ] : [],
			fn( $path ) => \is_string( $path ) && $path !== ''
		) );
		return $state;
	}

	public function toArray() :array {
		return [
			'status'        => $this->status->value,
			'mechanism'     => $this->mechanism->value ?? '',
			'host_prepend'  => $this->hostPrepend,
			'since'         => $this->since,
			'cache_seconds' => $this->cacheSeconds,
			'confirmed'     => $this->confirmed,
			'settled'       => $this->settled,
			'checked_at'    => $this->checkedAt,
			'error'         => $this->error->value ?? '',
			'error_file'    => $this->errorFile,
			'edits'         => $this->edits,
			'files'         => $this->files,
		];
	}

	/**
	 * Installed, not yet seen running, and still inside the time PHP may take to pick up the change.
	 */
	public function isPending( int $now ) :bool {
		return $this->status === InstallStatus::Installed && !$this->confirmed && $now < $this->settledAt();
	}

	/**
	 * When PHP can no longer be using the configuration from before the current status began.
	 */
	public function settledAt() :int {
		return $this->since + $this->cacheSeconds + 60;
	}

	/**
	 * Shield took its blocks out because of a failure: the install broke the site or was interrupted, or a
	 * removal couldn't finish. Such an install isn't put back automatically.
	 */
	public function wasUndone() :bool {
		return $this->status === InstallStatus::Removing && $this->error !== null;
	}

	/**
	 * Shield's blocks have been, or are about to be, taken out; the stub stays until PHP has stopped using it.
	 * A removal already under way keeps its start time, so its cache window isn't extended.
	 */
	public function startRemoving( int $now, int $userIniCacheTtl ) :void {
		if ( $this->status !== InstallStatus::Removing ) {
			$this->status = InstallStatus::Removing;
			$this->since = $now;
			$this->cacheSeconds = ( $this->mechanism ?? MechanismKind::Manual )->cacheSeconds( $userIniCacheTtl );
		}
		$this->confirmed = false;
		$this->settled = false;
		$this->edits = [];
	}

	public function recordError( InstallException $e ) :void {
		$this->error = $e->reason();
		$this->errorFile = $e->configFile();
	}

	public function clearError() :void {
		$this->error = null;
		$this->errorFile = '';
	}
}
