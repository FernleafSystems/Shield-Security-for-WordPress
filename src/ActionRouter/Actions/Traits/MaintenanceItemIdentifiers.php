<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits;

trait MaintenanceItemIdentifiers {

	/**
	 * The items a request is for: one "identifier", or, from "Ignore all" and "Stop ignoring all", every item of one
	 * kind together as a list of "identifiers".
	 * @return list<string>
	 */
	protected function requestedIdentifiers() :array {
		return \is_array( $this->action_data[ 'identifiers' ] ?? null )
			? \array_values( \array_unique( \array_filter( \array_map(
				fn( $identifier ) :string => \is_scalar( $identifier ) ? \trim( (string)$identifier ) : '',
				$this->action_data[ 'identifiers' ]
			) ) ) )
			: [ \trim( (string)( $this->action_data[ 'identifier' ] ?? '' ) ) ];
	}
}
