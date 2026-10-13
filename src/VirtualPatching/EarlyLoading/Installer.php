<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * Installs and removes the early loader.
 *
 * Install writes the stub first (checked by requesting a copy of it), records the planned edits, then writes
 * Shield's blocks in .htaccess and/or .user.ini and checks the site with loopback requests before and after.
 * If the site stops responding, the files are put back. Where PHP caches its configuration, the site is
 * checked again once the cache has expired, and the change is undone then if it broke the site.
 *
 * A file is only put back exactly while nobody else has changed it since; otherwise only Shield's block is
 * taken out. The stub is only deleted once nothing can still point PHP at it: Shield's blocks are gone,
 * PHP's configuration cache has expired, and a request confirms the stub no longer runs.
 */
class Installer {

	/**
	 * An install still marked as in progress after this long was interrupted.
	 */
	public const INTERRUPTED_AFTER = 300;

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
			$this->removeBlocks( $this->knownConfigFiles( $state ) );
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
		$state->settled = false;
		$state->edits = [];
		$state->error = '';
		$state->errorFile = '';
		$this->store->save( $state );
		return $state;
	}

	/**
	 * Second step of removal: delete the stub once nothing can still prepend it. That needs a web request,
	 * which sees the PHP configuration visitors get, and a request confirming the stub no longer runs; a
	 * failed loopback proves nothing, so the stub then stays.
	 */
	public function finishRemoval() :State {
		$state = $this->store->load();
		if ( $state->status !== State::REMOVING || !$this->env->isWebRequest ) {
			return $state;
		}

		$state->checkedAt = $this->env->now;
		$settled = $state->mechanism === Mechanism::MANUAL || $this->env->now >= $state->settledAt();
		if ( $settled && !$this->anyBlockPresent( $this->knownConfigFiles( $state ) ) && !$this->thisRequestPrependsStub()
			 && $this->probe->verify()->loaded === false
			 && ( new StubFile( $this->paths->stub ) )->delete() ) {
			$state = new State();
		}

		$this->store->save( $state );
		return $state;
	}

	/**
	 * Keeps an installed stub current, confirms whether it runs, and once PHP's configuration cache has
	 * expired, checks the site again and undoes the install if it broke the site.
	 * @throws InstallException
	 */
	public function refresh() :State {
		$state = $this->store->load();
		if ( !\in_array( $state->status, [ State::INSTALLED, State::MANUAL ], true ) ) {
			return $state;
		}

		$state->checkedAt = $this->env->now;
		try {
			$this->writeStub( $this->stubContent( $state->hostPrepend ) );
		}
		catch ( InstallException $e ) {
			$state->error = $e->reason();
			$state->errorFile = $e->configFile();
			$this->store->save( $state );
			throw $e;
		}

		$verify = $this->probe->verify();
		if ( $state->status === State::INSTALLED && !$state->settled && $this->env->now >= $state->settledAt() ) {
			$home = $this->probe->home();
			if ( $verify->reachable && $home->reachable ) {
				if ( !$verify->isVerificationResponse() || !$home->isVerificationResponse() ) {
					$this->rollBack( $state, InstallException::SITE_FAILED_REVERTED );
					return $this->store->load();
				}
				$state->settled = true;
				$state->edits = [];
			}
		}

		if ( $verify->isVerificationResponse() ) {
			$state->confirmed = (bool)$verify->loaded;
		}
		$state->error = '';
		$state->errorFile = '';
		$this->store->save( $state );
		return $state;
	}

	/**
	 * Puts back an install that a request started but never finished, for example because PHP stopped it.
	 */
	public function recoverInterrupted() :State {
		$state = $this->store->load();
		if ( $state->status === State::INSTALLING && $this->env->now - $state->since >= self::INTERRUPTED_AFTER ) {
			$this->rollBack( $state, InstallException::INSTALL_INTERRUPTED );
			$state = $this->store->load();
		}
		return $state;
	}

	public function stubContent( ?string $hostPrepend ) :string {
		return StubBuilder::build( $this->paths->bootstrap, $this->paths->ruleset, $hostPrepend, $this->paths->scope );
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

		$state = new State();
		$state->mechanism = $mechanism->key();
		$state->hostPrepend = $hostPrepend;
		$state->since = $this->env->now;
		$state->cacheSeconds = $mechanism->cacheSeconds( $this->env->userIniCacheTtl );
		$state->checkedAt = $this->env->now;
		$state->files = $previous->files;

		if ( !$mechanism->isAutomatic() ) {
			$this->writeStub( $this->stubContent( $hostPrepend ) );
			$this->removeBlocksOutside( [], $state );
			$state->status = State::MANUAL;
			return $state;
		}

		$planned = $this->plannedEdits( $mechanism );

		$this->probe->prepare();
		if ( !$this->siteResponds() ) {
			throw new InstallException( InstallException::LOOPBACK_UNAVAILABLE );
		}

		$this->writeStub( $this->stubContent( $hostPrepend ) );

		// Recorded before any file changes, so a request that dies part way through can be undone later.
		$state->status = State::INSTALLING;
		$state->files = \array_values( \array_unique( \array_merge( $state->files, \array_keys( $planned ) ) ) );
		foreach ( $planned as $path => [ $block, $body ] ) {
			$before = $this->readConfig( $path );
			$state->edits[ $path ] = [ 'before' => $before, 'after' => $block->apply( $before ?? '', $body ) ];
		}
		$this->store->save( $state );

		$path = '';
		try {
			foreach ( $planned as $path => [ $block, $body ] ) {
				[ $before, $after ] = ( new ConfigFile( $path ) )->modify(
					fn( ?string $content ) => $block->apply( $content ?? '', $body )
				);
				$state->edits[ $path ] = [ 'before' => $before, 'after' => (string)$after ];
			}
			$this->store->save( $state );
		}
		catch ( ConfigFileException|MalformedBlockException $e ) {
			$this->rollBack( $state, InstallException::CONFIG_WRITE_FAILED );
			throw new InstallException( InstallException::CONFIG_WRITE_FAILED, $path );
		}

		$verify = $this->probe->verify();
		if ( !$verify->isVerificationResponse() || !$this->probe->home()->isVerificationResponse() ) {
			$this->rollBack( $state, InstallException::SITE_FAILED_REVERTED );
			throw new InstallException( InstallException::SITE_FAILED_REVERTED );
		}

		$this->removeBlocksOutside( \array_keys( $planned ), $state );

		$state->status = State::INSTALLED;
		$state->confirmed = $verify->loaded === true;
		// Where PHP reads the change at once, this check was the real one; elsewhere refresh() checks again.
		$state->settled = $state->cacheSeconds === 0;
		if ( $state->settled ) {
			$state->edits = [];
		}
		return $state;
	}

	/**
	 * Writes a copy of the stub first and requests it, so a stub the server can't run never replaces one PHP
	 * may already be including. A request that can't be made (blocked, for example) doesn't stop the write.
	 * @throws InstallException
	 */
	private function writeStub( string $content ) :void {
		$stub = new StubFile( $this->paths->stub );
		if ( $stub->matches( $content ) ) {
			return;
		}
		$candidate = $stub->writeCandidate( $content );
		$smoke = $this->probe->smoke( \dirname( $this->paths->stubUrl ).'/'.\basename( $candidate ) );
		if ( $smoke->smokeFailed() ) {
			$stub->discard( $candidate );
			throw new InstallException( InstallException::STUB_SMOKE_FAILED, $this->paths->stub );
		}
		$stub->promote( $candidate );
	}

	/**
	 * Takes Shield's block out of files the current mechanism no longer uses, such as .htaccess after a
	 * site moves from mod_php to PHP-FPM.
	 * @param string[] $keep
	 */
	private function removeBlocksOutside( array $keep, State $state ) :void {
		$stale = \array_diff( $this->knownConfigFiles( $state ), $keep );
		foreach ( $stale as $path ) {
			try {
				$this->removeBlock( $path );
			}
			catch ( InstallException $e ) {
			}
		}
		$state->files = \array_values( \array_diff( $state->files, $stale ) );
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
				MarkedBlock::forFile( $this->paths->htaccess ),
				DirectiveBlocks::htaccess( $this->paths->stub, $mechanism, $this->env->userIniFilename ),
			];
		}
		if ( $mechanism->writesUserIni() && $this->paths->userIni !== null ) {
			$edits[ $this->paths->userIni ] = [
				MarkedBlock::forFile( $this->paths->userIni ),
				DirectiveBlocks::userIni( $this->paths->stub ),
			];
		}

		foreach ( $edits as $path => [ $block ] ) {
			$honoursPrepend = $path !== $this->paths->htaccess || $mechanism->writesHtaccessPrepend();
			$content = $this->readConfig( $path ) ?? '';
			try {
				if ( $honoursPrepend && $block->hasForeignPrepend( $content ) ) {
					throw new InstallException( InstallException::CONFLICT, $path );
				}
				$block->has( $content );
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

	/**
	 * Shield answers its verification request both through admin-ajax.php and through the home page. If it
	 * can't before the change, the change can't be checked, so it isn't made.
	 */
	private function siteResponds() :bool {
		return $this->probe->verify()->isVerificationResponse() && $this->probe->home()->isVerificationResponse();
	}

	/**
	 * Undoes the recorded edits. The stub stays, because PHP may already have read the new configuration
	 * and will keep using it until its cache expires.
	 */
	private function rollBack( State $state, string $reason ) :void {
		foreach ( \array_reverse( $state->edits, true ) as $path => $edit ) {
			try {
				( new ConfigFile( $path ) )->revert( $edit[ 'before' ], $edit[ 'after' ], MarkedBlock::forFile( $path ) );
			}
			catch ( ConfigFileException|MalformedBlockException $e ) {
			}
		}

		$state->status = State::REMOVING;
		$state->since = $this->env->now;
		$state->cacheSeconds = Mechanism::cacheSecondsFor( $state->mechanism, $this->env->userIniCacheTtl );
		$state->confirmed = false;
		$state->settled = false;
		$state->edits = [];
		$state->error = $reason;
		$state->errorFile = '';
		$this->store->save( $state );
	}

	/**
	 * Every configuration file Shield's block may be in: those recorded when it was written, and those this
	 * request would use now.
	 * @return string[]
	 */
	private function knownConfigFiles( State $state ) :array {
		return \array_values( \array_unique( \array_merge( $state->files, $this->paths->configFiles() ) ) );
	}

	/**
	 * @param string[] $paths
	 * @throws InstallException
	 */
	private function removeBlocks( array $paths ) :void {
		foreach ( $paths as $path ) {
			$this->removeBlock( $path );
		}
	}

	/**
	 * @throws InstallException
	 */
	private function removeBlock( string $path ) :void {
		$block = MarkedBlock::forFile( $path );
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

	/**
	 * @throws InstallException
	 */
	private function readConfig( string $path ) :?string {
		try {
			return ( new ConfigFile( $path ) )->read();
		}
		catch ( ConfigFileException $e ) {
			throw new InstallException( InstallException::CONFIG_WRITE_FAILED, $path );
		}
	}

	/**
	 * @param string[] $paths
	 */
	private function anyBlockPresent( array $paths ) :bool {
		foreach ( $paths as $path ) {
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
		return $path !== '' && VerifyRequest::isOwnStub( $path, $this->paths->stub );
	}

	private function recordError( InstallException $e ) :void {
		$state = $this->store->load();
		$state->error = $e->reason();
		$state->errorFile = $e->configFile();
		$this->store->save( $state );
	}
}
