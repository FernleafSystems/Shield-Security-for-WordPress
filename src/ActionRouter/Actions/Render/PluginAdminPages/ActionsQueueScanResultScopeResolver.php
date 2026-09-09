<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\ScanResultsScopeResolver;

class ActionsQueueScanResultScopeResolver {

	private ScanResultsScopeResolver $scopeResolver;

	public function __construct( ?ScanResultsScopeResolver $scopeResolver = null ) {
		$this->scopeResolver = $scopeResolver ?? new ScanResultsScopeResolver();
	}

	/**
	 * @param array<string,mixed> $renderActionData
	 * @return array{type:string,file:string}|array{}
	 */
	public function resolveForGroup( string $definitionKey, array $renderActionData = [] ) :array {
		switch ( $definitionKey ) {
			case 'wordpress':
				return $this->scopeResolver->normalizeActionScope(
					ScanResultsScopeResolver::SCOPE_TYPE_WORDPRESS,
					ScanResultsScopeResolver::SCOPE_FILE_WORDPRESS
				);
			case 'malware':
				return $this->scopeResolver->normalizeActionScope(
					ScanResultsScopeResolver::SCOPE_TYPE_MALWARE,
					ScanResultsScopeResolver::SCOPE_TYPE_MALWARE
				);
			case 'plugins':
				return $this->resolveSubjectScope(
					ScanResultsScopeResolver::SCOPE_TYPE_PLUGIN,
					$renderActionData
				);
			case 'themes':
				return $this->resolveSubjectScope(
					ScanResultsScopeResolver::SCOPE_TYPE_THEME,
					$renderActionData
				);
			default:
				return [];
		}
	}

	/**
	 * @param array<string,mixed> $renderActionData
	 * @return array{type:string,file:string}|array{}
	 */
	private function resolveSubjectScope( string $subjectType, array $renderActionData ) :array {
		$subjectId = \trim( (string)( $renderActionData[ 'subject_id' ] ?? '' ) );
		return $subjectId === ''
			? []
			: $this->scopeResolver->canonicalActionDataForSubject( $subjectType, $subjectId );
	}
}
