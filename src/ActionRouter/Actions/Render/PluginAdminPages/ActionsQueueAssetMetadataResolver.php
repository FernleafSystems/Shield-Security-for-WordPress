<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\{
	InvestigationSubjectResolver,
	InvestigationTableContract
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	InvalidInvestigationSubjectIdentifierException,
	UnsupportedInvestigationSubjectTypeException
};
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type QueueAssetMetadata array{
 *   subject_type:string,
 *   subject_id:string,
 *   title:string,
 *   icon_class:string,
 *   has_update:bool
 * }
 */
class ActionsQueueAssetMetadataResolver {

	/**
	 * @return QueueAssetMetadata|null
	 */
	public function resolve( string $assetType, string $assetKey ) :?array {
		$assetType = \strtolower( \trim( $assetType ) );
		$subjectId = $this->normalizeAssetSubjectId( $assetType, $assetKey );
		if ( $subjectId === '' ) {
			return null;
		}

		if ( $assetType === InvestigationTableContract::SUBJECT_TYPE_PLUGIN ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $subjectId, true );
			if ( !$asset instanceof WpPluginVo ) {
				return null;
			}

			return [
				'subject_type' => InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
				'subject_id'   => (string)$asset->file,
				'title'        => (string)$asset->Title,
				'icon_class'   => 'bi bi-plug-fill',
				'has_update'   => $asset->hasUpdate(),
			];
		}

		if ( $assetType !== InvestigationTableContract::SUBJECT_TYPE_THEME ) {
			return null;
		}

		$asset = Services::WpThemes()->getThemeAsVo( $subjectId, true );
		if ( !$asset instanceof WpThemeVo ) {
			return null;
		}

		return [
			'subject_type' => InvestigationTableContract::SUBJECT_TYPE_THEME,
			'subject_id'   => (string)$asset->stylesheet,
			'title'        => (string)$asset->Name,
			'icon_class'   => 'bi bi-palette-fill',
			'has_update'   => $asset->hasUpdate(),
		];
	}

	private function normalizeAssetSubjectId( string $assetType, string $assetKey ) :string {
		try {
			return ( new InvestigationSubjectResolver() )->normalizeAssetSubjectId( $assetType, $assetKey );
		}
		catch ( InvalidInvestigationSubjectIdentifierException|UnsupportedInvestigationSubjectTypeException $e ) {
			return '';
		}
	}
}
