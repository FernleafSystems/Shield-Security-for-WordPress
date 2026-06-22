<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

/**
 * @phpstan-type CloakedPluginAlertData array{
 *   type:string,
 *   type_label:string,
 *   file:string,
 *   name:string,
 *   version:string,
 *   location:string,
 *   status:string,
 *   hidden_by:list<string>,
 *   hidden_by_labels:list<string>,
 *   detected_at:int
 * }
 */
class CloakedPluginFinding {

	public PluginEntry $entry;

	/**
	 * @phpstan-var list<value-of<CloakReason::ALL>>
	 */
	public array $cloakReasons;

	public bool $active;

	public bool $networkActive;

	public int $detectedAt;

	/**
	 * @phpstan-param list<value-of<CloakReason::ALL>> $cloakReasons
	 */
	public function __construct(
		PluginEntry $entry,
		array $cloakReasons,
		bool $active,
		bool $networkActive,
		int $detectedAt
	) {
		foreach ( $cloakReasons as $reason ) {
			CloakReason::assertValid( $reason );
		}

		$this->entry = $entry;
		$this->cloakReasons = $cloakReasons;
		$this->active = $active;
		$this->networkActive = $networkActive;
		$this->detectedAt = $detectedAt;
	}

	public function fingerprint() :string {
		return \sha1( \json_encode( [
			'type'           => $this->entry->type,
			'file'           => $this->entry->file,
			'name'           => $this->entry->name,
			'version'        => $this->entry->version,
			'hidden_by'      => $this->cloakReasonValues(),
			'active'         => $this->active,
			'network_active' => $this->networkActive,
		] ) ?: $this->entry->type.'|'.$this->entry->file );
	}

	public function identityKey() :string {
		return \sha1( \json_encode( [
			'type' => $this->entry->type,
			'file' => $this->entry->file,
		] ) ?: $this->entry->type.'|'.$this->entry->file );
	}

	public function status() :string {
		if ( $this->entry->type === PluginType::MustUse ) {
			return 'must-use';
		}
		if ( $this->networkActive ) {
			return 'network-active';
		}
		return $this->active ? 'active' : 'inactive';
	}

	/**
	 * @return CloakedPluginAlertData
	 */
	public function toAlertData() :array {
		return [
			'type'             => $this->entry->type,
			'type_label'       => PluginType::label( $this->entry->type ),
			'file'             => $this->entry->file,
			'name'             => $this->entry->name,
			'version'          => $this->entry->version,
			'location'         => $this->relativeLocation(),
			'status'           => $this->status(),
			'hidden_by'        => $this->cloakReasonValues(),
			'hidden_by_labels' => \array_map(
				static fn( string $reason ) :string => CloakReason::label( $reason ),
				$this->cloakReasons
			),
			'detected_at'      => $this->detectedAt,
		];
	}

	/**
	 * @return array{plugin:string,type:string,hidden_by:string,status:string,name:string,version:string}
	 */
	public function toAuditParams() :array {
		return [
			'plugin'    => $this->entry->file,
			'type'      => $this->entry->type,
			'hidden_by' => \implode( ', ', $this->cloakReasonValues() ),
			'status'    => $this->status(),
			'name'      => $this->entry->name,
			'version'   => $this->entry->version,
		];
	}

	/**
	 * @phpstan-return list<value-of<CloakReason::ALL>>
	 */
	private function cloakReasonValues() :array {
		return \array_values( $this->cloakReasons );
	}

	private function relativeLocation() :string {
		return \sprintf(
			'%s/%s',
			$this->entry->type === PluginType::MustUse ? 'mu-plugins' : 'plugins',
			\ltrim( $this->entry->file, '/\\' )
		);
	}
}
