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
			return $this->emptyMainwpIssuesSummary();
		}

		$attention = $this->canonicalMainwpAttentionSummary();
		if ( $attention === null ) {
			return $this->emptyMainwpIssuesSummary();
		}

		return [
			'count'        => $attention[ 'total' ],
			'severity'     => $attention[ 'severity' ],
			'has_issues'   => $attention[ 'total' ] > 0,
			'button_class' => $this->buttonClassForSeverity( $attention[ 'severity' ] ),
		];
	}

	public function hasMainwpAttentionSummary() :bool {
		return $this->canonicalMainwpAttentionSummary() !== null;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function mainwpAttentionSummary() :array {
		return \is_array( $this->overview[ 'attention_summary' ] ?? null )
			? $this->overview[ 'attention_summary' ]
			: [];
	}

	/**
	 * @return MainWPIssuesSummary
	 */
	private function emptyMainwpIssuesSummary() :array {
		return [
			'count'        => 0,
			'severity'     => 'good',
			'has_issues'   => false,
			'button_class' => 'green',
		];
	}

	/**
	 * @return array{total:int,severity:'good'|'warning'|'critical'}|null
	 */
	private function canonicalMainwpAttentionSummary() :?array {
		$attention = $this->mainwpAttentionSummary();

		if ( !\array_key_exists( 'total', $attention ) || !\array_key_exists( 'severity', $attention ) ) {
			return null;
		}

		if ( !\is_numeric( $attention[ 'total' ] ) ) {
			return null;
		}

		$total = (int)$attention[ 'total' ];
		$severity = $attention[ 'severity' ];

		if ( $total < 0 || !\in_array( $severity, [ 'good', 'warning', 'critical' ], true ) ) {
			return null;
		}

		return [
			'total'    => $total,
			'severity' => $severity,
		];
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
