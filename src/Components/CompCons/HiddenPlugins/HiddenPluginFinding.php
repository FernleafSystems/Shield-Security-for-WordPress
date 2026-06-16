<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins;

/**
 * @phpstan-type HiddenPluginAlertData array{
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
readonly class HiddenPluginFinding {

	/**
	 * @param list<HiddenReason> $hiddenReasons
	 */
	public function __construct(
		public PluginEntry $entry,
		public array $hiddenReasons,
		public bool $active,
		public bool $networkActive,
		public int $detectedAt
	) {
	}

	public function fingerprint() :string {
		return \sha1( \json_encode( [
			'type'           => $this->entry->type->value,
			'file'           => $this->entry->file,
			'name'           => $this->entry->name,
			'version'        => $this->entry->version,
			'hidden_by'      => $this->hiddenReasonValues(),
			'active'         => $this->active,
			'network_active' => $this->networkActive,
		] ) ?: $this->entry->type->value.'|'.$this->entry->file );
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
	 * @return HiddenPluginAlertData
	 */
	public function toAlertData() :array {
		return [
			'type'             => $this->entry->type->value,
			'type_label'       => $this->entry->type->label(),
			'file'             => $this->entry->file,
			'name'             => $this->entry->name,
			'version'          => $this->entry->version,
			'location'         => $this->relativeLocation(),
			'status'           => $this->status(),
			'hidden_by'        => $this->hiddenReasonValues(),
			'hidden_by_labels' => \array_map(
				static fn( HiddenReason $reason ) :string => $reason->label(),
				$this->hiddenReasons
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
			'type'      => $this->entry->type->value,
			'hidden_by' => \implode( ', ', $this->hiddenReasonValues() ),
			'status'    => $this->status(),
			'name'      => $this->entry->name,
			'version'   => $this->entry->version,
		];
	}

	/**
	 * @return list<string>
	 */
	private function hiddenReasonValues() :array {
		return \array_values( \array_map(
			static fn( HiddenReason $reason ) :string => $reason->value,
			$this->hiddenReasons
		) );
	}

	private function relativeLocation() :string {
		return \sprintf(
			'%s/%s',
			$this->entry->type === PluginType::MustUse ? 'mu-plugins' : 'plugins',
			\ltrim( $this->entry->file, '/\\' )
		);
	}
}
