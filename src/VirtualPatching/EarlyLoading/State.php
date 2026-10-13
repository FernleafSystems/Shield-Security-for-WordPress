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

	public int $checkedAt = 0;

	public string $error = '';

	public string $errorFile = '';

	public static function fromArray( array $data ) :self {
		$state = new self();
		$state->status = \in_array( $data[ 'status' ] ?? '', [ self::MANUAL, self::INSTALLED, self::REMOVING ], true )
			? $data[ 'status' ] : self::OFF;
		$state->mechanism = (string)( $data[ 'mechanism' ] ?? '' );
		$state->hostPrepend = isset( $data[ 'host_prepend' ] ) && \is_string( $data[ 'host_prepend' ] ) ? $data[ 'host_prepend' ] : null;
		$state->since = (int)( $data[ 'since' ] ?? 0 );
		$state->cacheSeconds = (int)( $data[ 'cache_seconds' ] ?? 0 );
		$state->confirmed = (bool)( $data[ 'confirmed' ] ?? false );
		$state->checkedAt = (int)( $data[ 'checked_at' ] ?? 0 );
		$state->error = (string)( $data[ 'error' ] ?? '' );
		$state->errorFile = (string)( $data[ 'error_file' ] ?? '' );
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
			'checked_at'    => $this->checkedAt,
			'error'         => $this->error,
			'error_file'    => $this->errorFile,
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
