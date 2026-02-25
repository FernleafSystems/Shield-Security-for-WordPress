<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

trait InvestigateAssetOptionsBuilder {

	protected function buildAssetOptions( array $assets, string $valueField ) :array {
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
			$options[ $value ] = [
				'value' => $value,
				'label' => empty( $version ) ? $name : \sprintf( '%s (%s)', $name, $version ),
			];
		}

		\uasort( $options, static fn( array $a, array $b ) :int => \strnatcasecmp( $a[ 'label' ], $b[ 'label' ] ) );
		return \array_values( $options );
	}
}
