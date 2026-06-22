<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsModules;

class ProfileOptionsFormViewBuilder {

	private const PROFILE_GROUP_ORDER = [
		'plugin',
		'admin_access_restriction',
		'ips',
		'hack_protect',
		'firewall',
		'login_protect',
		'user_management',
		'comments_filter',
		'integrations',
	];

	/**
	 * @param string[] $profileableKeys
	 * @param string[] $excludedKeys
	 * @return array<int,array{
	 *   slug:string,
	 *   title:string,
	 *   subtitle:string,
	 *   option_count:int,
	 *   included_count:int,
	 *   excluded_count:int,
	 *   is_sync_included:bool,
	 *   sync_next_status:string,
	 *   sync_toggle_label:string,
	 *   sync_status_label:string,
	 *   keys_csv:string,
	 *   search_text:string,
	 *   sections:array<int,array{
	 *     slug:string,
	 *     title:string,
	 *     title_short:string,
	 *     option_count:int,
	 *     search_text:string,
	 *     options:array<int,array>
	 *   }>
	 * }>
	 */
	public function build( array $sections, array $profileableKeys, array $excludedKeys ) :array {
		$profileableLookup = \array_flip( \array_map( '\strval', $profileableKeys ) );
		$excludedLookup = \array_flip( \array_map( '\strval', $excludedKeys ) );
		$emitted = [];
		$groups = [];

		foreach ( $sections as $section ) {
			$section = \is_array( $section ) ? $section : [];
			$module = (string)( $section[ 'module' ] ?? '' );
			$options = $this->buildSectionOptions( $section, $profileableLookup, $excludedLookup, $emitted );
			if ( $module === '' || empty( $options ) ) {
				continue;
			}

			if ( !isset( $groups[ $module ] ) ) {
				$groups[ $module ] = $this->buildEmptyGroup( $module );
			}
			$options = \array_map(
				fn( array $option ) :array => \array_merge( $option, [
					'search_text' => $this->searchText( [
						$option[ 'search_text' ] ?? '',
						$groups[ $module ][ 'title' ],
						$groups[ $module ][ 'subtitle' ],
					] ),
				] ),
				$options
			);

			$sectionTitle = (string)( $section[ 'title' ] ?? $section[ 'slug' ] ?? '' );
			$sectionTitleShort = (string)( $section[ 'title_short' ] ?? $sectionTitle );
			$sectionSearchText = $this->searchText( [
				$groups[ $module ][ 'title' ],
				$groups[ $module ][ 'subtitle' ],
				$sectionTitle,
				$sectionTitleShort,
				\array_map(
					static fn( array $option ) :string => (string)( $option[ 'search_text' ] ?? '' ),
					$options
				),
			] );

			$groups[ $module ][ 'sections' ][] = [
				'slug'         => (string)( $section[ 'slug' ] ?? '' ),
				'title'        => $sectionTitle,
				'title_short'  => $sectionTitleShort,
				'option_count' => \count( $options ),
				'search_text'  => $sectionSearchText,
				'options'      => $options,
			];

			foreach ( $options as $option ) {
				$groups[ $module ][ 'option_keys' ][] = (string)$option[ 'key' ];
				$groups[ $module ][ 'option_count' ]++;
				if ( (bool)$option[ 'is_sync_included' ] ) {
					$groups[ $module ][ 'included_count' ]++;
				}
			}
		}

		$groups = $this->orderGroups( $groups );

		return \array_values( \array_map(
			fn( array $group ) :array => $this->finaliseGroup( $group ),
			$groups
		) );
	}

	/**
	 * @param array<string,array> $groups
	 * @return array<string,array>
	 */
	private function orderGroups( array $groups ) :array {
		$ordered = [];

		foreach ( self::PROFILE_GROUP_ORDER as $module ) {
			if ( isset( $groups[ $module ] ) ) {
				$ordered[ $module ] = $groups[ $module ];
				unset( $groups[ $module ] );
			}
		}

		return $ordered + $groups;
	}

	/**
	 * @param array<string,int> $profileableLookup
	 * @param array<string,int> $excludedLookup
	 * @param array<string,bool> $emitted
	 * @return array<int,array>
	 */
	private function buildSectionOptions( array $section, array $profileableLookup, array $excludedLookup, array &$emitted ) :array {
		$options = [];
		$sectionTitle = (string)( $section[ 'title' ] ?? '' );
		$sectionTitleShort = (string)( $section[ 'title_short' ] ?? $sectionTitle );

		foreach ( (array)( $section[ 'options' ] ?? [] ) as $option ) {
			if ( !\is_array( $option ) ) {
				continue;
			}

			$key = (string)( $option[ 'key' ] ?? '' );
			if ( $key === '' || !isset( $profileableLookup[ $key ] ) || isset( $emitted[ $key ] ) ) {
				continue;
			}

			$isIncluded = !isset( $excludedLookup[ $key ] );
			$option[ 'is_sync_included' ] = $isIncluded;
			$option[ 'sync_next_status' ] = $isIncluded ? 'exclude' : 'include';
			$option[ 'sync_toggle_label' ] = $isIncluded
				? __( 'Click to exclude from sync', 'wp-simple-firewall' )
				: __( 'Click to include in sync', 'wp-simple-firewall' );
			$option[ 'search_text' ] = $this->searchText( [
				$key,
				$option[ 'name' ] ?? '',
				$option[ 'summary' ] ?? '',
				$sectionTitle,
				$sectionTitleShort,
			] );

			$options[] = $option;
			$emitted[ $key ] = true;
		}

		return $options;
	}

	private function buildEmptyGroup( string $module ) :array {
		$strings = ( new StringsModules() )->getFor( $module );
		return [
			'slug'           => $module,
			'title'          => (string)$strings[ 'name' ],
			'subtitle'       => (string)$strings[ 'subtitle' ],
			'option_count'   => 0,
			'included_count' => 0,
			'option_keys'    => [],
			'sections'       => [],
		];
	}

	private function finaliseGroup( array $group ) :array {
		$optionCount = (int)$group[ 'option_count' ];
		$includedCount = (int)$group[ 'included_count' ];
		$excludedCount = \max( 0, $optionCount - $includedCount );
		$isIncluded = $excludedCount === 0;

		$group[ 'excluded_count' ] = $excludedCount;
		$group[ 'is_sync_included' ] = $isIncluded;
		$group[ 'sync_next_status' ] = $isIncluded ? 'exclude' : 'include';
		$group[ 'sync_toggle_label' ] = $isIncluded
			? __( 'Click to exclude this group from sync', 'wp-simple-firewall' )
			: __( 'Click to include this group in sync', 'wp-simple-firewall' );
		$group[ 'sync_status_label' ] = sprintf(
			__( '%1$d/%2$d included', 'wp-simple-firewall' ),
			$includedCount,
			$optionCount
		);
		$group[ 'keys_csv' ] = \implode( ',', \array_map( '\strval', (array)$group[ 'option_keys' ] ) );
		$group[ 'search_text' ] = $this->searchText( [
			$group[ 'title' ],
			$group[ 'subtitle' ],
			\array_map(
				static fn( array $section ) :string => (string)( $section[ 'search_text' ] ?? '' ),
				(array)$group[ 'sections' ]
			),
		] );

		unset( $group[ 'option_keys' ] );
		return $group;
	}

	private function searchText( array $parts ) :string {
		$text = \implode( ' ', $this->flattenSearchParts( $parts ) );
		$text = \html_entity_decode( \wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		$text = \preg_replace( '#\s+#', ' ', $text );
		return \strtolower( \trim( (string)$text ) );
	}

	private function flattenSearchParts( array $parts ) :array {
		$flattened = [];
		foreach ( $parts as $part ) {
			if ( \is_array( $part ) ) {
				$flattened = \array_merge( $flattened, $this->flattenSearchParts( $part ) );
			}
			elseif ( \is_scalar( $part ) ) {
				$flattened[] = (string)$part;
			}
		}
		return $flattened;
	}
}
