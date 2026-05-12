<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class InvestigateAssetLookupOptionsBuilder {

	/**
	 * @return array<int,array{value:string,label:string}>
	 */
	public function build( array $assets, string $valueField, string $search = '', int $limit = 0 ) :array {
		$needle = \strtolower( \trim( $search ) );
		$options = [];

		foreach ( $assets as $asset ) {
			if ( !\is_object( $asset ) ) {
				continue;
			}

			$value = \trim( (string)( $asset->{$valueField} ?? '' ) );
			if ( $value === '' ) {
				continue;
			}

			$name = \trim( (string)( $asset->Name ?? '' ) );
			if ( $name === '' ) {
				$name = $value;
			}
			$version = \trim( (string)( $asset->Version ?? '' ) );
			$label = empty( $version ) ? $name : \sprintf( '%s (%s)', $name, $version );

			if ( $needle !== '' ) {
				$haystack = \strtolower( \sprintf( '%s %s %s', $name, $value, $version ) );
				if ( \strpos( $haystack, $needle ) === false ) {
					continue;
				}
			}

			$options[ $value ] = [
				'value' => $value,
				'label' => $label,
			];
		}

		\uasort( $options, static fn( array $a, array $b ) :int => \strnatcasecmp( $a[ 'label' ], $b[ 'label' ] ) );
		$options = \array_values( $options );

		if ( $limit > 0 ) {
			$options = \array_slice( $options, 0, $limit );
		}

		return $options;
	}
}
