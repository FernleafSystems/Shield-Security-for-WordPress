<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SyncSiteUrlValidator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * @phpstan-type PendingNetworkInvite array{id:string,master_url:string,created_at:int,updated_at:int}
 * @phpstan-type PendingNetworkInviteWithReviewUrl array{id:string,master_url:string,created_at:int,updated_at:int,review_url:string}
 */
class NetworkInviteRepository {

	use PluginControllerConsumer;

	public const OPTION_KEY = 'importexport_pending_network_invites';
	public const INVITE_BLOCK_UNTIL_OPTION_KEY = 'importexport_network_invite_block_until';
	public const REVIEW_QUERY_KEY = 'network_invite';
	private const REJECT_COOLDOWN = \WEEK_IN_SECONDS;

	/**
	 * @return PendingNetworkInviteWithReviewUrl|null
	 */
	public function receive( string $masterUrl ) :?array {
		if ( !$this->canReceiveInvite() ) {
			return null;
		}

		try {
			$masterUrl = ( new SyncSiteUrlValidator() )->validatePublicOutbound( $masterUrl );
		}
		catch ( \Throwable $e ) {
			return null;
		}

		$id = $this->idForUrl( $masterUrl );
		$now = Services::Request()->ts();
		$invite = [
			'id'         => $id,
			'master_url' => $masterUrl,
			'created_at' => $now,
			'updated_at' => $now,
		];

		$this->store( [ $id => $invite ] );
		return $this->withReviewUrl( $invite );
	}

	/**
	 * @return list<PendingNetworkInviteWithReviewUrl>
	 */
	public function pending() :array {
		if ( !$this->canReviewInvites() ) {
			return [];
		}

		$invite = $this->firstStoredInvite();
		return $invite === null ? [] : [ $this->withReviewUrl( $invite ) ];
	}

	/**
	 * @return PendingNetworkInviteWithReviewUrl|null
	 */
	public function first() :?array {
		$pending = $this->pending();
		return $pending[ 0 ] ?? null;
	}

	/**
	 * @return PendingNetworkInviteWithReviewUrl|null
	 */
	public function find( string $id ) :?array {
		$id = sanitize_key( $id );
		if ( !$this->canReviewInvites() ) {
			return null;
		}

		$invite = $this->firstStoredInvite();
		return $invite !== null && (string)$invite[ 'id' ] === $id ? $this->withReviewUrl( $invite ) : null;
	}

	public function hasPendingInvite() :bool {
		return $this->firstStoredInvite() !== null;
	}

	public function canReceiveInvite() :bool {
		return $this->canReviewInvites() && !$this->hasPendingInvite();
	}

	public function canReviewInvites() :bool {
		return ( new ImportExportController() )->isSyncEnabled()
			   && !$this->isCooldownActive()
			   && !$this->isConnectedToMaster()
			   && !$this->hasActiveClientSites();
	}

	public function isCooldownActive() :bool {
		return $this->blockUntil() > Services::Request()->ts();
	}

	public function reject( string $id ) :bool {
		if ( !$this->clear( $id ) ) {
			return false;
		}

		self::con()->opts
				   ->optSet( self::INVITE_BLOCK_UNTIL_OPTION_KEY, Services::Request()->ts() + self::REJECT_COOLDOWN )
				   ->store();
		return true;
	}

	public function clear( string $id ) :bool {
		$id = sanitize_key( $id );
		if ( !isset( $this->allById()[ $id ] ) ) {
			return false;
		}

		$this->store( [] );
		return true;
	}

	public function clearAll( bool $persist = true ) :bool {
		$raw = self::con()->opts->optGet( self::OPTION_KEY );
		$hadInvites = $raw !== [];

		self::con()->opts->optSet( self::OPTION_KEY, [] );
		if ( $persist ) {
			self::con()->opts->store();
		}

		return $hadInvites;
	}

	public function reviewUrl( string $id ) :string {
		return URL::Build(
			self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_IMPORT ),
			[ self::REVIEW_QUERY_KEY => sanitize_key( $id ) ]
		);
	}

	private function idForUrl( string $url ) :string {
		return \hash( 'sha256', $url );
	}

	private function blockUntil() :int {
		return \max( 0, (int)self::con()->opts->optGet( self::INVITE_BLOCK_UNTIL_OPTION_KEY ) );
	}

	private function isConnectedToMaster() :bool {
		return \trim( (string)self::con()->opts->optGet( 'importexport_masterurl' ) ) !== '';
	}

	private function hasActiveClientSites() :bool {
		( new ImportExportController() )->ensureSitesRegistryImported();
		return ( new SiteRepository() )->countActiveRows() > 0;
	}

	/**
	 * @return PendingNetworkInvite|null
	 */
	private function firstStoredInvite() :?array {
		$invites = \array_values( $this->allById() );
		\usort( $invites, static fn( array $a, array $b ) :int => $a[ 'created_at' ] <=> $b[ 'created_at' ] );
		return $invites[ 0 ] ?? null;
	}

	/**
	 * @return array<string,PendingNetworkInvite>
	 */
	private function allById() :array {
		$raw = self::con()->opts->optGet( self::OPTION_KEY );
		$raw = \is_array( $raw ) ? $raw : [];
		$invites = [];
		foreach ( $raw as $key => $invite ) {
			if ( !\is_array( $invite ) ) {
				continue;
			}
			$normalised = $this->normaliseInvite( (string)$key, $invite );
			if ( $normalised !== null ) {
				$invites[ $normalised[ 'id' ] ] = $normalised;
			}
		}
		return $invites;
	}

	/**
	 * @param array<string,mixed> $invite
	 * @return PendingNetworkInvite|null
	 */
	private function normaliseInvite( string $key, array $invite ) :?array {
		$masterUrl = (string)( $invite[ 'master_url' ] ?? '' );
		try {
			$masterUrl = ( new SyncSiteUrlValidator() )->validate( $masterUrl );
		}
		catch ( \Throwable $e ) {
			return null;
		}

		$id = sanitize_key( (string)( $invite[ 'id' ] ?? $key ) );
		if ( $id !== $this->idForUrl( $masterUrl ) ) {
			$id = $this->idForUrl( $masterUrl );
		}

		return [
			'id'         => $id,
			'master_url' => $masterUrl,
			'created_at' => \max( 0, (int)( $invite[ 'created_at' ] ?? 0 ) ),
			'updated_at' => \max( 0, (int)( $invite[ 'updated_at' ] ?? 0 ) ),
		];
	}

	/**
	 * @param PendingNetworkInvite $invite
	 * @return PendingNetworkInviteWithReviewUrl
	 */
	private function withReviewUrl( array $invite ) :array {
		$invite[ 'review_url' ] = $this->reviewUrl( (string)$invite[ 'id' ] );
		return $invite;
	}

	/**
	 * @param array<string,PendingNetworkInvite> $invites
	 */
	private function store( array $invites ) :void {
		self::con()->opts->optSet( self::OPTION_KEY, $invites )->store();
	}
}
