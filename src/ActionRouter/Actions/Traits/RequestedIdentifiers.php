<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

trait RequestedIdentifiers {

	/**
	 * One "identifier", or a list of "identifiers" from "Ignore all" and "Stop ignoring all".
	 * @return list<string>
	 */
	private function requestedIdentifiers() :array {
		return \is_array( $this->action_data[ 'identifiers' ] ?? null )
			? \array_values( \array_unique( \array_filter( $this->action_data[ 'identifiers' ], '\is_string' ) ) )
			: [ $this->fields()->string( 'identifier', '' ) ];
	}
}
