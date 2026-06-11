<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\LoadFileLocks;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class BuildSecurityIssues {

	use PluginControllerConsumer;

	private const SOURCE = 'shield_plugin';
	private const CATEGORY_SCANS = 'scans';
	private const CATEGORY_MAINTENANCE = 'maintenance';
	private const SEVERITY_CRITICAL = 'critical';
	private const SEVERITY_WARNING = 'warning';

	private const STATE_KEYS = [
		'is_checksumfail',
		'is_unrecognised',
		'is_mal',
		'is_missing',
		'is_abandoned',
		'is_vulnerable',
	];

	/**
	 * @return list<array<string,mixed>>
	 */
	public function build() :array {
		$issues = [];

		foreach ( [ 'wpv', 'apc', 'afs' ] as $scanSlug ) {
			foreach ( $this->getRawScanItems( $scanSlug, self::STATE_KEYS ) as $item ) {
				foreach ( $this->scanIssuesForItem( $item ) as $issue ) {
					$issues[] = $issue;
				}
			}
		}

		foreach ( $this->fileLockerIssues() as $issue ) {
			$issues[] = $issue;
		}

		foreach ( $this->maintenanceIssues() as $issue ) {
			$issues[] = $issue;
		}

		return \array_values( $this->dedupe( $issues ) );
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	protected function getRawScanItems( string $scanSlug, array $stateMetaKeys ) :array {
		$scanCon = self::con()->comps->scans->getScanCon( $scanSlug );
		if ( $scanCon === null ) {
			return [];
		}

		$items = [];
		$resultsSet = ( new RetrieveItems() )
			->setScanController( $scanCon )
			->retrieveActiveProblemFindings( $stateMetaKeys );

		foreach ( $resultsSet->getAllItems() as $item ) {
			$states = \array_values( \array_filter(
				self::STATE_KEYS,
				static fn( string $state ) :bool => !empty( $item->{$state} )
			) );

			if ( empty( $states ) ) {
				continue;
			}

			$items[] = [
				'scan'         => $scanSlug,
				'item_id'      => (string)( $item->VO->item_id ?? '' ),
				'asset_type'   => (string)( $item->VO->asset_type ?? '' ),
				'asset_key'    => (string)( $item->VO->asset_key ?? '' ),
				'last_seen_at' => (int)( $item->VO->last_seen_at ?? 0 ),
				'states'       => $states,
			];
		}

		return $items;
	}

	/**
	 * @return list<object>
	 */
	protected function getProblemFileLocks() :array {
		return \array_values( ( new LoadFileLocks() )->withProblems() );
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	protected function getMaintenanceStates() :array {
		return ( new MaintenanceIssueStateProvider() )->buildStates();
	}

	/**
	 * @param array<string,mixed> $item
	 * @return list<array<string,mixed>>
	 */
	private function scanIssuesForItem( array $item ) :array {
		$issues = [];
		$states = \is_array( $item[ 'states' ] ?? null ) ? $item[ 'states' ] : [];
		$scan = (string)( $item[ 'scan' ] ?? '' );

		if ( $scan === 'wpv' && \in_array( 'is_vulnerable', $states, true ) ) {
			$issues[] = $this->issue(
				self::CATEGORY_SCANS,
				'vulnerable_assets',
				self::SEVERITY_CRITICAL,
				$this->normalizeAssetType( $item, [ 'plugin', 'theme' ] ),
				$this->normalizeAssetKey( $item, 'site' ),
				$this->detectedAt( (int)( $item[ 'last_seen_at' ] ?? 0 ) )
			);
		}

		if ( $scan === 'apc' && \in_array( 'is_abandoned', $states, true ) ) {
			$issues[] = $this->issue(
				self::CATEGORY_SCANS,
				'abandoned',
				self::SEVERITY_CRITICAL,
				$this->normalizeAssetType( $item, [ 'plugin', 'theme' ] ),
				$this->normalizeAssetKey( $item, 'site' ),
				$this->detectedAt( (int)( $item[ 'last_seen_at' ] ?? 0 ) )
			);
		}

		if ( $scan === 'afs' ) {
			if ( \in_array( 'is_mal', $states, true ) ) {
				$issues[] = $this->issue(
					self::CATEGORY_SCANS,
					'malware',
					self::SEVERITY_CRITICAL,
					$this->normalizeAssetType( $item, [ 'core', 'plugin', 'theme' ] ),
					$this->normalizeAssetKey( $item, 'site' ),
					$this->detectedAt( (int)( $item[ 'last_seen_at' ] ?? 0 ) )
				);
			}

			if ( \count( \array_intersect( [ 'is_checksumfail', 'is_missing', 'is_unrecognised' ], $states ) ) > 0 ) {
				$assetType = $this->normalizeAssetType( $item, [ 'core', 'plugin', 'theme' ] );
				$issues[] = $this->issue(
					self::CATEGORY_SCANS,
					$this->fileIssueKeyForAssetType( $assetType ),
					self::SEVERITY_CRITICAL,
					$assetType,
					$this->normalizeAssetKey( $item, $assetType === 'core' ? 'wordpress_core' : 'site' ),
					$this->detectedAt( (int)( $item[ 'last_seen_at' ] ?? 0 ) )
				);
			}
		}

		return $issues;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function fileLockerIssues() :array {
		$locks = $this->getProblemFileLocks();
		if ( empty( $locks ) ) {
			return [];
		}

		$latestDetectedAt = 0;
		foreach ( $locks as $lock ) {
			$latestDetectedAt = \max( $latestDetectedAt, (int)( $lock->detected_at ?? 0 ) );
		}

		return [
			$this->issue(
				self::CATEGORY_SCANS,
				'file_locker',
				self::SEVERITY_WARNING,
				'site',
				'site',
				$this->detectedAt( $latestDetectedAt )
			),
		];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function maintenanceIssues() :array {
		$issues = [];
		foreach ( $this->getMaintenanceStates() as $state ) {
			$key = (string)( $state[ 'key' ] ?? '' );
			$issueKey = $this->maintenanceIssueKey( $key );
			if ( $issueKey === '' ) {
				continue;
			}

			$identifiers = \is_array( $state[ 'active_identifiers' ] ?? null ) ? $state[ 'active_identifiers' ] : [];

			foreach ( $identifiers as $identifier ) {
				$asset = $this->maintenanceAsset( $key, (string)$identifier );
				$issues[] = $this->issue(
					self::CATEGORY_MAINTENANCE,
					$issueKey,
					self::SEVERITY_WARNING,
					$asset[ 'type' ],
					$asset[ 'key' ],
					null
				);
			}
		}

		return $issues;
	}

	/**
	 * @param list<array<string,mixed>> $issues
	 * @return array<string,array<string,mixed>>
	 */
	private function dedupe( array $issues ) :array {
		$deduped = [];
		foreach ( $issues as $issue ) {
			$identity = \implode( "\0", [
				$issue[ 'source' ],
				$issue[ 'category' ],
				$issue[ 'issue_key' ],
				$issue[ 'asset_type' ],
				$issue[ 'asset_key' ],
			] );

			if ( !isset( $deduped[ $identity ] ) ) {
				$deduped[ $identity ] = $issue;
				continue;
			}

			if ( $deduped[ $identity ][ 'severity' ] !== self::SEVERITY_CRITICAL
				 && $issue[ 'severity' ] === self::SEVERITY_CRITICAL ) {
				$deduped[ $identity ][ 'severity' ] = self::SEVERITY_CRITICAL;
			}

			if ( !empty( $issue[ 'detected_at' ] )
				 && ( empty( $deduped[ $identity ][ 'detected_at' ] )
					  || $issue[ 'detected_at' ] > $deduped[ $identity ][ 'detected_at' ] ) ) {
				$deduped[ $identity ][ 'detected_at' ] = $issue[ 'detected_at' ];
			}
		}

		return $deduped;
	}

	private function fileIssueKeyForAssetType( string $assetType ) :string {
		switch ( $assetType ) {
			case 'plugin':
				return 'plugin_files';
			case 'theme':
				return 'theme_files';
			case 'core':
			default:
				return 'wp_files';
		}
	}

	private function maintenanceIssueKey( string $key ) :string {
		return \in_array( $key, [
			'default_admin_user',
			'wp_updates',
			'wp_plugins_updates',
			'wp_themes_updates',
			'wp_plugins_inactive',
			'wp_themes_inactive',
			'system_ssl_certificate',
			'system_php_version',
			'wp_db_password',
			'system_lib_openssl',
		], true ) ? $key : '';
	}

	/**
	 * @return array{type:string,key:string}
	 */
	private function maintenanceAsset( string $key, string $identifier ) :array {
		switch ( $key ) {
			case 'wp_plugins_updates':
			case 'wp_plugins_inactive':
				return [
					'type' => 'plugin',
					'key'  => $identifier,
				];
			case 'wp_themes_updates':
			case 'wp_themes_inactive':
				return [
					'type' => 'theme',
					'key'  => $identifier,
				];
			case 'wp_updates':
				return [
					'type' => 'core',
					'key'  => 'wordpress_core',
				];
			default:
				return [
					'type' => 'site',
					'key'  => 'site',
				];
		}
	}

	private function normalizeAssetType( array $item, array $allowed ) :string {
		$type = (string)( $item[ 'asset_type' ] ?? '' );
		return \in_array( $type, $allowed, true ) ? $type : 'site';
	}

	private function normalizeAssetKey( array $item, string $default ) :string {
		$key = \trim( (string)( $item[ 'asset_key' ] ?? '' ) );
		if ( $key !== '' && $key !== 'core' ) {
			return $key;
		}

		$type = (string)( $item[ 'asset_type' ] ?? '' );
		if ( $type === 'core' ) {
			return 'wordpress_core';
		}

		return $default;
	}

	private function issue(
		string $category,
		string $issueKey,
		string $severity,
		string $assetType,
		string $assetKey,
		?string $detectedAt
	) :array {
		$issue = [
			'source'     => self::SOURCE,
			'category'   => $category,
			'issue_key'  => $issueKey,
			'severity'   => $severity,
			'asset_type' => $assetType,
			'asset_key'  => $assetKey,
		];

		if ( $detectedAt !== null ) {
			$issue[ 'detected_at' ] = $detectedAt;
		}

		return $issue;
	}

	private function detectedAt( int $timestamp ) :?string {
		return $timestamp > 0 ? \gmdate( 'c', $timestamp ) : null;
	}
}
