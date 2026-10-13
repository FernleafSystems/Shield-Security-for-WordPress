<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * What Shield has installed for early loading.
 */
final class State {

	/**
	 * Nothing installed.
	 */
	public const OFF = 'off';

	/**
	 * Configuration files are being edited. Seen by a later request, the install was interrupted.
	 */
	public const INSTALLING = 'installing';

	/**
	 * Stub written; the administrator sets auto_prepend_file.
	 */
	public const MANUAL = 'manual';

	/**
	 * Stub and Shield's blocks written.
	 */
	public const INSTALLED = 'installed';

	/**
	 * Shield's blocks removed; the stub stays until PHP can no longer be using it.
	 */
	public const REMOVING = 'removing';

	public string $status = self::OFF;

	public string $mechanism = '';

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

	public string $error = '';

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

	public static function fromArray( array $data ) :self {
		$state = new self();
		$state->status = \in_array( $data[ 'status' ] ?? '', [ self::INSTALLING, self::MANUAL, self::INSTALLED, self::REMOVING ], true )
			? $data[ 'status' ] : self::OFF;
		$state->mechanism = (string)( $data[ 'mechanism' ] ?? '' );
		$state->hostPrepend = isset( $data[ 'host_prepend' ] ) && \is_string( $data[ 'host_prepend' ] ) ? $data[ 'host_prepend' ] : null;
		$state->since = (int)( $data[ 'since' ] ?? 0 );
		$state->cacheSeconds = (int)( $data[ 'cache_seconds' ] ?? 0 );
		$state->confirmed = (bool)( $data[ 'confirmed' ] ?? false );
		$state->settled = (bool)( $data[ 'settled' ] ?? false );
		$state->checkedAt = (int)( $data[ 'checked_at' ] ?? 0 );
		$state->error = (string)( $data[ 'error' ] ?? '' );
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
			'status'        => $this->status,
			'mechanism'     => $this->mechanism,
			'host_prepend'  => $this->hostPrepend,
			'since'         => $this->since,
			'cache_seconds' => $this->cacheSeconds,
			'confirmed'     => $this->confirmed,
			'settled'       => $this->settled,
			'checked_at'    => $this->checkedAt,
			'error'         => $this->error,
			'error_file'    => $this->errorFile,
			'edits'         => $this->edits,
			'files'         => $this->files,
		];
	}

	/**
	 * Whether PHP may still be configured to prepend the stub.
	 */
	public function stubMayBeInUse() :bool {
		return $this->status !== self::OFF;
	}

	/**
	 * Installed, not yet seen running, and still inside the time PHP may take to pick up the change.
	 */
	public function isPending( int $now ) :bool {
		return $this->status === self::INSTALLED && !$this->confirmed && $now < $this->settledAt();
	}

	/**
	 * When PHP can no longer be using the configuration from before the current status began.
	 */
	public function settledAt() :int {
		return $this->since + $this->cacheSeconds + 60;
	}
}
