<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
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
	public const REVIEW_QUERY_KEY = 'network_invite';
	private const MAX_PENDING = 5;

	/**
	 * @return PendingNetworkInviteWithReviewUrl|null
	 */
	public function receive( string $masterUrl ) :?array {
		if ( !( new ImportExportController() )->isSyncEnabled() ) {
			return null;
		}

		try {
			$masterUrl = ( new SyncSiteUrlValidator() )->validatePublicOutbound( $masterUrl );
		}
		catch ( \Throwable $e ) {
			return null;
		}

		$invites = $this->allById();
		$id = $this->idForUrl( $masterUrl );
		$now = Services::Request()->ts();

		if ( isset( $invites[ $id ] ) ) {
			$invites[ $id ][ 'updated_at' ] = $now;
		}
		elseif ( \count( $invites ) >= self::MAX_PENDING ) {
			return null;
		}
		else {
			$invites[ $id ] = [
				'id'         => $id,
				'master_url' => $masterUrl,
				'created_at' => $now,
				'updated_at' => $now,
			];
		}

		$this->store( $invites );
		return $this->withReviewUrl( $invites[ $id ] );
	}

	/**
	 * @return list<PendingNetworkInviteWithReviewUrl>
	 */
	public function pending() :array {
		$invites = \array_map(
			fn( array $invite ) :array => $this->withReviewUrl( $invite ),
			\array_values( $this->allById() )
		);

		\usort( $invites, static fn( array $a, array $b ) :int => $a[ 'created_at' ] <=> $b[ 'created_at' ] );
		return $invites;
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
		$invite = $this->allById()[ $id ] ?? null;
		return \is_array( $invite ) ? $this->withReviewUrl( $invite ) : null;
	}

	public function clear( string $id ) :bool {
		$id = sanitize_key( $id );
		$invites = $this->allById();
		if ( !isset( $invites[ $id ] ) ) {
			return false;
		}

		unset( $invites[ $id ] );
		$this->store( $invites );
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
			if ( !empty( $normalised ) ) {
				$invites[ $normalised[ 'id' ] ] = $normalised;
			}
		}
		return $invites;
	}

	/**
	 * @param array<string,mixed> $invite
	 * @return PendingNetworkInvite|array{}
	 */
	private function normaliseInvite( string $key, array $invite ) :array {
		$masterUrl = (string)( $invite[ 'master_url' ] ?? '' );
		try {
			$masterUrl = ( new SyncSiteUrlValidator() )->validate( $masterUrl );
		}
		catch ( \Throwable $e ) {
			return [];
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
