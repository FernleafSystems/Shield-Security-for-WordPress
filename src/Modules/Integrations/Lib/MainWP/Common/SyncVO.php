<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @phpstan-type MainWPIssuesSummary array{
 *   count:int,
 *   severity:'good'|'warning'|'critical',
 *   has_issues:bool,
 *   button_class:'green'|'orange'|'red'
 * }
 * @property array[]    $modules
 * @property array[]    $options
 * @property array<string,mixed> $overview
 * @property SyncMetaVO $meta
 */
class SyncVO extends DynPropertiesClass {

	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {
			case 'meta':
				$value = ( new SyncMetaVO() )->applyFromArray( \is_array( $value ) ? $value : [] );
				break;
			case 'overview':
				$value = \is_array( $value ) ? $value : [];
				break;
			default:
				break;
		}

		return $value;
	}

	/**
	 * @return MainWPIssuesSummary
	 */
	public function mainwpIssuesSummary( bool $isActive = true ) :array {
		if ( !$isActive ) {
			return [
				'count'        => 0,
				'severity'     => 'good',
				'has_issues'   => false,
				'button_class' => 'green',
			];
		}

		$attention = \is_array( $this->overview[ 'attention_summary' ] ?? null )
			? $this->overview[ 'attention_summary' ]
			: [];
		$count = \max( 0, (int)( $attention[ 'total' ] ?? 0 ) );
		$severity = $this->normalizeIssueSeverity( (string)( $attention[ 'severity' ] ?? 'good' ) );

		return [
			'count'        => $count,
			'severity'     => $severity,
			'has_issues'   => $count > 0,
			'button_class' => $this->buttonClassForSeverity( $severity ),
		];
	}

	/**
	 * @return 'good'|'warning'|'critical'
	 */
	private function normalizeIssueSeverity( string $severity ) :string {
		return \in_array( $severity, [ 'good', 'warning', 'critical' ], true ) ? $severity : 'good';
	}

	/**
	 * @param 'good'|'warning'|'critical' $severity
	 * @return 'green'|'orange'|'red'
	 */
	private function buttonClassForSeverity( string $severity ) :string {
		switch ( $severity ) {
			case 'critical':
				return 'red';
			case 'warning':
				return 'orange';
			default:
				return 'green';
		}
	}
}
