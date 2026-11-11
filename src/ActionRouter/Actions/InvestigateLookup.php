<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\IpLookupSearch;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\InvestigateUserLookupBuilder;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * The one Investigate lookup: finds users, IP addresses, plugins, themes and WordPress core from whatever is
 * typed, grouped by kind. With a kind chosen and nothing typed, it lists that kind to browse.
 *
 * @phpstan-type LookupRow array{value:string,title:string,meta:string}
 * @phpstan-type LookupGroup array{kind:string,label:string,icon_class:string,results:list<LookupRow>}
 */
class InvestigateLookup extends BaseAction {

	public const SLUG = 'investigate_lookup';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}
	public const KINDS = [ 'user', 'ip', 'plugin', 'theme', 'core' ];
	private const LIMIT_ANY = 6;
	private const LIMIT_KIND = 60;

	protected function getDefaults() :array {
		return [
			'kind'   => 'any',
			'search' => '',
		];
	}

	protected function exec() {
		$kind = sanitize_key( (string)$this->action_data[ 'kind' ] );
		if ( !\in_array( $kind, self::KINDS, true ) ) {
			$kind = 'any';
		}
		$search = \trim( sanitize_text_field( (string)$this->action_data[ 'search' ] ) );

		$groups = [];
		foreach ( $kind === 'any' ? self::KINDS : [ $kind ] as $groupKind ) {
			// With nothing typed, only a chosen kind lists anything.
			if ( $search === '' && $kind === 'any' ) {
				break;
			}
			$results = $this->search( $groupKind, $search, $kind === 'any' ? self::LIMIT_ANY : self::LIMIT_KIND );
			if ( !empty( $results ) ) {
				$groups[] = \array_merge( $this->groupLabel( $groupKind ), [
					'kind'    => $groupKind,
					'results' => $results,
				] );
			}
		}

		$this->response()->setPayload( [
			'groups' => $groups,
		] )->setPayloadSuccess( true );
	}

	/**
	 * @return list<LookupRow>
	 */
	private function search( string $kind, string $search, int $limit ) :array {
		switch ( $kind ) {
			case 'user':
				return ( new InvestigateUserLookupBuilder() )->lookupRows( $search, $limit );
			case 'ip':
				return $this->searchIps( $search, $limit );
			case 'plugin':
				return $this->searchAssets( Services::WpPlugins()->getPluginsAsVo(), $search, $limit );
			case 'theme':
				return $this->searchAssets( Services::WpThemes()->getThemesAsVo(), $search, $limit );
			case 'core':
				return $this->searchCore( $search );
			default:
				return [];
		}
	}

	/**
	 * @return list<LookupRow>
	 */
	private function searchIps( string $search, int $limit ) :array {
		return \array_map(
			static fn( string $ip ) :array => [
				'value' => $ip,
				'title' => $ip,
				'meta'  => __( 'IP address', 'wp-simple-firewall' ),
			],
			\array_values( ( new IpLookupSearch() )->findMatchingIps( $search, $limit ) )
		);
	}

	/**
	 * @param array<WpPluginVo|WpThemeVo> $assets
	 * @return list<LookupRow>
	 */
	private function searchAssets( array $assets, string $search, int $limit ) :array {
		$needle = \strtolower( $search );
		$rows = [];
		foreach ( $assets as $asset ) {
			$name = \trim( (string)( $asset->Name ?? '' ) );
			$value = (string)$asset->unique_id;
			if ( $value === '' ) {
				continue;
			}
			if ( $needle !== '' && \strpos( \strtolower( $name.' '.$value ), $needle ) === false ) {
				continue;
			}
			$rows[] = [
				'value' => $value,
				'title' => $name === '' ? $value : $name,
				'meta'  => \implode( ' · ', \array_filter( [
					empty( $asset->Version ) ? '' : \sprintf( __( 'Version %s', 'wp-simple-firewall' ), $asset->Version ),
					$asset->active ? __( 'Active', 'wp-simple-firewall' ) : __( 'Inactive', 'wp-simple-firewall' ),
					$asset->hasUpdate() ? __( 'Update available', 'wp-simple-firewall' ) : '',
				] ) ),
			];
		}

		\usort( $rows, static fn( array $a, array $b ) :int => \strnatcasecmp( $a[ 'title' ], $b[ 'title' ] ) );
		return \array_slice( $rows, 0, $limit );
	}

	/**
	 * @return list<LookupRow>
	 */
	private function searchCore( string $search ) :array {
		$needle = \strtolower( $search );
		if ( $needle !== '' && \strpos( 'wordpress core', $needle ) === false && \strpos( $needle, 'wordpress' ) === false ) {
			return [];
		}
		return [
			[
				'value' => '',
				'title' => \sprintf( 'WordPress %s', Services::WpGeneral()->getVersion() ),
				'meta'  => __( 'Core files, updates and changes', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return array{label:string,icon_class:string}
	 */
	private function groupLabel( string $kind ) :array {
		return [
			'user'   => [ 'label' => __( 'Users', 'wp-simple-firewall' ), 'icon_class' => 'bi bi-person' ],
			'ip'     => [ 'label' => __( 'IP addresses', 'wp-simple-firewall' ), 'icon_class' => 'bi bi-globe2' ],
			'plugin' => [ 'label' => __( 'Plugins', 'wp-simple-firewall' ), 'icon_class' => 'bi bi-plug' ],
			'theme'  => [ 'label' => __( 'Themes', 'wp-simple-firewall' ), 'icon_class' => 'bi bi-brush' ],
			'core'   => [ 'label' => __( 'WordPress core', 'wp-simple-firewall' ), 'icon_class' => 'bi bi-wordpress' ],
		][ $kind ];
	}
}
