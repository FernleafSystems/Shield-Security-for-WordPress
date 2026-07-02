<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type CloakedPluginAlertData array{
 *   type:string,
 *   type_label:string,
 *   file:string,
 *   name:string,
 *   version:string,
 *   location:string,
 *   status:'must-use'|'network-active'|'active'|'inactive',
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

	/**
	 * @return 'must-use'|'network-active'|'active'|'inactive'
	 */
	public function status() :string {
		if ( $this->entry->type === PluginType::MustUse ) {
			return 'must-use';
		}
		if ( $this->networkActive ) {
			return 'network-active';
		}
		return $this->active ? 'active' : 'inactive';
	}

	public function relativePath() :string {
		$relativePath = Services::WpFs()->getPathRelativeToAbsPath( $this->entry->path );
		$normalizedRelative = \str_replace( '\\', '/', $relativePath );
		$normalizedOriginal = \str_replace( '\\', '/', $this->entry->path );

		if ( $normalizedRelative === ''
			 || $normalizedRelative === $normalizedOriginal
			 || \strpos( $normalizedRelative, '/' ) === 0
			 || \preg_match( '#^[a-zA-Z]:/#', $normalizedRelative ) === 1 ) {
			return $this->pluginBaseRelativePath();
		}

		return \ltrim( $normalizedRelative, '/' );
	}

	/**
	 * @return list<string>
	 */
	public function cloakReasonLabels() :array {
		if ( ( new ShieldGeneratedMuPlugin() )->isGeneratedShieldMuLoaderFinding( $this ) ) {
			return [
				__( 'Shield hides its own Must-Use loader to avoid showing Shield twice while the Must-Use option is enabled.', 'wp-simple-firewall' ),
			];
		}

		return \array_map(
			static fn( string $reason ) :string => CloakReason::label( $reason ),
			$this->cloakReasons
		);
	}

	public function cloakReasonSummary() :string {
		return \implode( ', ', $this->cloakReasonLabels() );
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
			'location'         => $this->relativePath(),
			'status'           => $this->status(),
			'hidden_by'        => $this->cloakReasonValues(),
			'hidden_by_labels' => $this->cloakReasonLabels(),
			'detected_at'      => $this->detectedAt,
		];
	}

	/**
	 * @return array{plugin:string,type:string,hidden_by:string,status:'must-use'|'network-active'|'active'|'inactive',name:string,version:string}
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

	private function pluginBaseRelativePath() :string {
		return \sprintf(
			'%s/%s',
			$this->entry->type === PluginType::MustUse ? 'wp-content/mu-plugins' : 'wp-content/plugins',
			\ltrim( $this->entry->file, '/\\' )
		);
	}
}
