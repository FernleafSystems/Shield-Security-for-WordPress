<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

class OperationalIssuesProvider {

	/**
	 * @return list<array{
	 *   key:string,
	 *   zone:string,
	 *   label:string,
	 *   count:int,
	 *   severity:string,
	 *   text:string,
	 *   href:string,
	 *   action:string,
	 *   target:string
	 * }>
	 */
	public function buildQueueItems() :array {
		return \array_values( \array_filter( \array_map(
			fn( array $state ) :?array => $this->buildItemFromState( $state ),
			\array_values( $this->buildMaintenanceIssueStateProvider()->buildStates() )
		) ) );
	}

	/**
	 * @return array{
	 *   key:string,
	 *   zone:string,
	 *   label:string,
	 *   count:int,
	 *   severity:string,
	 *   text:string,
	 *   href:string,
	 *   action:string,
	 *   target:string
	 * }|null
	 */
	protected function buildItemFromState( array $state ) :?array {
		if ( $state[ 'count' ] < 1 ) {
			return null;
		}

		return $this->buildItem(
			$state[ 'key' ],
			$state[ 'label' ],
			$state[ 'count' ],
			$state[ 'severity' ],
			$state[ 'description' ],
			$state[ 'href' ],
			$state[ 'action' ],
			$state[ 'target' ]
		);
	}

	protected function buildMaintenanceIssueStateProvider() :MaintenanceIssueStateProvider {
		return new MaintenanceIssueStateProvider();
	}

	/**
	 * @return array{
	 *   key:string,
	 *   zone:string,
	 *   label:string,
	 *   count:int,
	 *   severity:string,
	 *   text:string,
	 *   href:string,
	 *   action:string,
	 *   target:string
	 * }|null
	 */
	private function buildItem(
		string $key,
		string $label,
		int $count,
		string $severity,
		string $text,
		string $href,
		string $action,
		string $target
	) :?array {
		return ( $count > 0 && $label !== '' && $text !== '' )
			? [
				'key'      => $key,
				'zone'     => 'maintenance',
				'label'    => $label,
				'count'    => $count,
				'severity' => $severity,
				'text'     => $text,
				'href'     => $href,
				'action'   => $action,
				'target'   => $target,
			]
			: null;
	}
}
