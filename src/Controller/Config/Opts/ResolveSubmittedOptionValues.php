<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class ResolveSubmittedOptionValues {

	use PluginControllerConsumer;

	/**
	 * @param array<string,mixed> $form
	 * @return array{values:array<string,mixed>,submitted_keys:string[]}
	 * @throws \Exception
	 */
	public function resolve( array $form ) :array {
		$optsCon = self::con()->opts;
		$submittedKeys = \array_values( \array_filter( \array_map( '\trim', \explode( ',', (string)( $form[ 'all_opts_keys' ] ?? '' ) ) ) ) );
		$values = [];

		foreach ( $submittedKeys as $optKey ) {

			if ( !$optsCon->optExists( $optKey ) || $optsCon->optDef( $optKey )[ 'section' ] === 'section_hidden' ) {
				continue;
			}

			$optType = $optsCon->optType( $optKey );
			if ( $optType === 'noneditable_text' ) {
				continue;
			}

			$optValue = $form[ $optKey ] ?? null;
			if ( \is_null( $optValue ) ) {

				if ( \in_array( $optType, [ 'text', 'email' ] ) ) {
					continue;
				}
				elseif ( $optType === 'checkbox' ) {
					$optValue = 'N';
				}
				elseif ( $optType === 'integer' ) {
					$optValue = 0;
				}
				elseif ( $optType === 'multiple_select' ) {
					$optValue = [];
				}
			}
			elseif ( $optType === 'password' ) {
				$tempValue = \trim( (string)$optValue );
				if ( $tempValue === '' ) {
					continue;
				}

				$confirm = $form[ $optKey.'_confirm' ] ?? null;
				if ( $tempValue !== $confirm ) {
					throw new \Exception( __( 'Password values do not match.', 'wp-simple-firewall' ) );
				}

				$optValue = \hash( 'md5', $tempValue );
			}
			elseif ( $optType === 'array' ) {
				$optValue = \array_values( \array_filter(
					\array_map( '\trim', \explode( "\n", esc_textarea( (string)$optValue ) ) ),
					static fn( string $value ) :bool => $value !== ''
				) );
			}

			$values[ $optKey ] = $optValue;
		}

		return [
			'values'         => $values,
			'submitted_keys' => $submittedKeys,
		];
	}
}
