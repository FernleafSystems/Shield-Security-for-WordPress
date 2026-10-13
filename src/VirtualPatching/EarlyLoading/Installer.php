<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * Installs and removes the early loader.
 *
 * Install writes the stub first, then Shield's blocks in .htaccess and/or .user.ini, and checks the site
 * with loopback requests before and after. If the site stops loading, both files are put back exactly as
 * they were. The stub is only deleted once no configuration can still point PHP at it: Shield's blocks are
 * gone and PHP's configuration cache has had time to expire.
 */
class Installer {

	private Paths $paths;

	private Environment $env;

	private LoopbackProbe $probe;

	private StateStore $store;

	public function __construct( Paths $paths, Environment $env, LoopbackProbe $probe, StateStore $store ) {
		$this->paths = $paths;
		$this->env = $env;
		$this->probe = $probe;
		$this->store = $store;
	}

	/**
	 * @throws InstallException
	 */
	public function install() :State {
		$previous = $this->store->load();
		try {
			$state = $this->runInstall( $previous );
		}
		catch ( InstallException $e ) {
			$this->recordError( $e );
			throw $e;
		}
		$this->store->save( $state );
		return $state;
	}

	/**
	 * First step of removal: take Shield's blocks out of the configuration files. The stub stays.
	 * @throws InstallException
	 */
	public function removeDirectives() :State {
		$state = $this->store->load();
		try {
			$this->removeBlocks();
		}
		catch ( InstallException $e ) {
			$this->recordError( $e );
			throw $e;
		}

		if ( $state->status === State::OFF && !( new StubFile( $this->paths->stub ) )->exists() ) {
			return $state;
		}

		if ( $state->status !== State::REMOVING ) {
			$state->status = State::REMOVING;
			$state->since = $this->env->now;
			$state->cacheSeconds = Mechanism::cacheSecondsFor( $state->mechanism, $this->env->userIniCacheTtl );
		}
		$state->confirmed = false;
		$state->error = '';
		$state->errorFile = '';
		$this->store->save( $state );
		return $state;
	}

	/**
	 * Second step of removal: delete the stub once nothing can still prepend it.
	 */
	public function finishRemoval() :State {
		$state = $this->store->load();
		if ( $state->status !== State::REMOVING || $this->anyBlockPresent() || $this->thisRequestPrependsStub() ) {
			return $state;
		}

		$verify = $this->probe->verify();
		$state->checkedAt = $this->env->now;
		if ( $verify->loaded === true ) {
			$this->store->save( $state );
			return $state;
		}

		if ( $state->mechanism === Mechanism::MANUAL ) {
			// Only the administrator can remove a manual setting, so wait for evidence that they have.
			$safe = $verify->loaded === false;
		}
		else {
			$safe = $this->env->now >= $state->settledAt();
		}

		if ( $safe && ( new StubFile( $this->paths->stub ) )->delete() ) {
			$state = new State();
		}
		$this->store->save( $state );
		return $state;
	}

	/**
	 * Confirms whether an installed stub is running and keeps the stub's content current.
	 * @throws InstallException
	 */
	public function refresh() :State {
		$state = $this->store->load();
		if ( !\in_array( $state->status, [ State::INSTALLED, State::MANUAL ], true ) ) {
			return $state;
		}

		( new StubFile( $this->paths->stub ) )->write( $this->stubContent( $state->hostPrepend ) );

		$verify = $this->probe->verify();
		if ( $verify->isVerificationResponse() ) {
			$state->confirmed = (bool)$verify->loaded;
			$state->checkedAt = $this->env->now;
			$this->store->save( $state );
		}
		return $state;
	}

	public function stubContent( ?string $hostPrepend ) :string {
		return StubBuilder::build( $this->paths->bootstrap, $this->paths->ruleset, $hostPrepend );
	}

	/**
	 * @throws InstallException
	 */
	private function runInstall( State $previous ) :State {
		if ( $this->env->isMultisite ) {
			throw new InstallException( InstallException::MULTISITE );
		}
		if ( !$this->env->isDirectFilesystem ) {
			throw new InstallException( InstallException::FILESYSTEM_NOT_DIRECT );
		}
		if ( !DirectiveBlocks::isSafePath( $this->paths->stub ) ) {
			throw new InstallException( InstallException::UNSAFE_PATH, $this->paths->stub );
		}

		$mechanism = $this->env->mechanism();
		$hostPrepend = $this->hostPrepend( $previous );
		$stub = new StubFile( $this->paths->stub );

		$state = new State();
		$state->mechanism = $mechanism->key();
		$state->hostPrepend = $hostPrepend;
		$state->since = $this->env->now;
		$state->cacheSeconds = $mechanism->cacheSeconds( $this->env->userIniCacheTtl );
		$state->checkedAt = $this->env->now;

		if ( !$mechanism->isAutomatic() ) {
			$stub->write( $this->stubContent( $hostPrepend ) );
			$state->status = State::MANUAL;
			return $state;
		}

		$edits = $this->plannedEdits( $mechanism );

		$baseline = $this->probe->verify();
		if ( !$baseline->isVerificationResponse() ) {
			throw new InstallException( InstallException::LOOPBACK_UNAVAILABLE );
		}
		$baselineHome = $this->probe->home();

		$stub->write( $this->stubContent( $hostPrepend ) );
		if ( $this->probe->smoke()->isServerError() ) {
			if ( !$previous->stubMayBeInUse() ) {
				$stub->delete();
			}
			throw new InstallException( InstallException::STUB_SMOKE_FAILED, $this->paths->stub );
		}

		$snapshots = [];
		try {
			foreach ( $edits as $path => [ $block, $body ] ) {
				[ $before ] = ( new ConfigFile( $path ) )->modify(
					fn( ?string $content ) => $block->apply( $content ?? '', $body )
				);
				$snapshots[ $path ] = $before;
			}
		}
		catch ( ConfigFileException|MalformedBlockException $e ) {
			$this->rollBack( $snapshots, $previous );
			throw new InstallException( InstallException::CONFIG_WRITE_FAILED, (string)\array_key_last( $snapshots ) );
		}

		$verify = $this->probe->verify();
		if ( $this->siteFailed( $verify, $baselineHome, $this->probe->home() ) ) {
			$this->rollBack( $snapshots, $previous );
			throw new InstallException( InstallException::SITE_FAILED_REVERTED );
		}

		$this->removeBlocksOutside( \array_keys( $edits ) );

		$state->status = State::INSTALLED;
		$state->confirmed = $verify->loaded === true;
		return $state;
	}

	/**
	 * Takes Shield's block out of files the current mechanism no longer uses, such as .htaccess after a
	 * site moves from mod_php to PHP-FPM.
	 * @param string[] $keep
	 */
	private function removeBlocksOutside( array $keep ) :void {
		foreach ( $this->paths->configFiles() as $path => $block ) {
			if ( !\in_array( $path, $keep, true ) ) {
				try {
					( new ConfigFile( $path ) )->modify(
						fn( ?string $content ) => $content !== null && $block->has( $content ) ? $block->remove( $content ) : null
					);
				}
				catch ( ConfigFileException|MalformedBlockException $e ) {
				}
			}
		}
	}

	/**
	 * The blocks to write, after checking no other auto_prepend_file is set in a file PHP will read.
	 * @return array<string,array{0:MarkedBlock,1:string}>
	 * @throws InstallException
	 */
	private function plannedEdits( Mechanism $mechanism ) :array {
		$edits = [];
		if ( $mechanism->writesHtaccess() ) {
			$edits[ $this->paths->htaccess ] = [
				MarkedBlock::htaccess(),
				DirectiveBlocks::htaccess( $this->paths->stub, $mechanism, $this->env->userIniFilename ),
			];
		}
		if ( $mechanism->writesUserIni() && $this->paths->userIni !== null ) {
			$edits[ $this->paths->userIni ] = [ MarkedBlock::userIni(), DirectiveBlocks::userIni( $this->paths->stub ) ];
		}

		foreach ( $edits as $path => [ $block ] ) {
			$honoursPrepend = $path !== $this->paths->htaccess || $mechanism->writesHtaccessPrepend();
			try {
				$content = ( new ConfigFile( $path ) )->read() ?? '';
				if ( $honoursPrepend && $block->hasForeignPrepend( $content ) ) {
					throw new InstallException( InstallException::CONFLICT, $path );
				}
				$block->has( $content );
			}
			catch ( ConfigFileException $e ) {
				throw new InstallException( InstallException::CONFIG_WRITE_FAILED, $path );
			}
			catch ( MalformedBlockException $e ) {
				throw new InstallException( InstallException::MALFORMED_MARKERS, $path );
			}
		}
		return $edits;
	}

	/**
	 * A prepend set outside .htaccess and .user.ini (by the host) is kept by including it from the stub.
	 * @throws InstallException
	 */
	private function hostPrepend( State $previous ) :?string {
		$current = \trim( $this->env->currentPrepend );
		if ( $current === '' || \strtolower( $current ) === 'none' ) {
			return null;
		}
		if ( $this->isStub( $current ) ) {
			// Already ours, so the host's setting (if any) was recorded when it was first installed.
			return $previous->hostPrepend;
		}
		if ( !DirectiveBlocks::isSafePath( $current ) ) {
			throw new InstallException( InstallException::HOST_PREPEND_NOT_ABSOLUTE, $current );
		}
		return $current;
	}

	private function siteFailed( ProbeResult $verify, ProbeResult $baselineHome, ProbeResult $home ) :bool {
		return !$verify->isVerificationResponse()
			   || ( $baselineHome->isLoads() && !$home->isLoads() );
	}

	/**
	 * Puts every edited file back exactly as it was. The stub stays, because PHP may already have read the
	 * new configuration and will keep using it until its cache expires.
	 * @param array<string,?string> $snapshots
	 */
	private function rollBack( array $snapshots, State $previous ) :void {
		foreach ( \array_reverse( $snapshots, true ) as $path => $content ) {
			try {
				( new ConfigFile( $path ) )->restore( $content );
			}
			catch ( ConfigFileException $e ) {
			}
		}

		$state = $previous;
		if ( $state->status !== State::REMOVING ) {
			$state->status = State::REMOVING;
			$state->mechanism = $this->env->mechanism()->key();
			$state->since = $this->env->now;
			$state->cacheSeconds = $this->env->mechanism()->cacheSeconds( $this->env->userIniCacheTtl );
		}
		$state->confirmed = false;
		$this->store->save( $state );
	}

	/**
	 * @throws InstallException
	 */
	private function removeBlocks() :void {
		foreach ( $this->paths->configFiles() as $path => $block ) {
			try {
				( new ConfigFile( $path ) )->modify(
					fn( ?string $content ) => $content !== null && $block->has( $content ) ? $block->remove( $content ) : null
				);
			}
			catch ( ConfigFileException $e ) {
				throw new InstallException( InstallException::CONFIG_WRITE_FAILED, $path );
			}
			catch ( MalformedBlockException $e ) {
				throw new InstallException( InstallException::MALFORMED_MARKERS, $path );
			}
		}
	}

	private function anyBlockPresent() :bool {
		foreach ( $this->paths->configFiles() as $path => $block ) {
			try {
				$content = ( new ConfigFile( $path ) )->read();
				if ( $content !== null && \str_contains( $content, MarkedBlock::MARKER ) ) {
					return true;
				}
			}
			catch ( ConfigFileException $e ) {
				return true;
			}
		}
		return false;
	}

	private function thisRequestPrependsStub() :bool {
		return $this->env->isWebRequest && $this->isStub( $this->env->currentPrepend );
	}

	private function isStub( string $path ) :bool {
		$real = @\realpath( $path );
		$stub = @\realpath( $this->paths->stub );
		return DirectiveBlocks::normalise( $path ) === DirectiveBlocks::normalise( $this->paths->stub )
			   || ( $real !== false && $stub !== false && $real === $stub );
	}

	private function recordError( InstallException $e ) :void {
		$state = $this->store->load();
		$state->error = $e->reason();
		$state->errorFile = $e->configFile();
		$this->store->save( $state );
	}
}
