<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Twig\{
	Environment,
	Loader\FilesystemLoader
};

class FileLockerDiffTwigTest extends BaseUnitTest {

	use PluginPathsTrait;

	private function twig() :Environment {
		return new Environment(
			new FilesystemLoader( $this->getPluginFilePath( 'templates/twig' ) ),
			[
				'cache'            => false,
				'debug'            => false,
				'strict_variables' => false,
			]
		);
	}

	private function createDomXPathFromHtml( string $html ) :\DOMXPath {
		$doc = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		try {
			$doc->loadHTML(
				'<?xml encoding="utf-8" ?>'.$html,
				\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
			);
		}
		finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors( $previous );
		}

		return new \DOMXPath( $doc );
	}

	private function buildRenderContext() :array {
		return [
			'success' => true,
			'flags'   => [
				'has_diff'              => true,
				'original_file_missing' => false,
				'current_content_empty' => false,
			],
			'strings' => [
				'reviewing_locked_file' => 'Reviewing Locked File',
				'file_content_original' => 'Original',
				'file_content_current'  => 'Current',
				'file_details'          => 'File Details',
				'relative_path'         => 'Relative Path',
				'locked'                => 'Locked',
				'file_size'             => 'File Size',
				'file_modified'         => 'File Modified',
				'download'              => 'Download',
				'modified_file'         => 'Modified File',
				'change_detected_at'    => 'Change Detected At',
				'file_restore'          => 'Restore File',
				'file_restore_checkbox' => 'Confirm restore',
				'butt_restore'          => 'Restore',
				'file_accept'           => 'Accept File',
				'file_accept_checkbox'  => 'Confirm accept',
				'butt_accept'           => 'Accept',
			],
			'vars'    => [
				'rid'                => 14,
				'full_path'          => '/srv/www/wp-config.php',
				'relative_path'      => 'wp-config.php',
				'locked_at'          => '2026-03-12 08:00:00',
				'file_size_locked'   => '2 KB',
				'file_modified_ago'  => '2 minutes ago',
				'file_modified_at'   => '2026-03-12 08:05:00',
				'change_detected_at' => '2026-03-12 08:06:00',
				'file_size_modified' => '3 KB',
			],
			'html'    => [
				'diff' => '<div>Diff</div>',
			],
			'ajax'    => [
				'original' => '/download/original',
				'current'  => '/download/current',
			],
		];
	}

	public function testFileLockerDiffTemplateUsesRecordScopedCheckboxIds() :void {
		$html = '<div>'.$this->twig()->render(
			'/wpadmin_pages/insights/scans/results/realtime/file_locker/file_diff.twig',
			$this->buildRenderContext()
		).'</div>';
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame(
			1,
			$xpath->query( '//input[@id="ConfirmFileRestore-14" and @name="ConfirmFileRestore"]' )->length,
			'File Locker diff restore confirmation should scope its checkbox ID to the record'
		);
		$this->assertSame(
			1,
			$xpath->query( '//label[@for="ConfirmFileRestore-14"]' )->length,
			'File Locker diff restore label should target the scoped restore checkbox ID'
		);
		$this->assertSame(
			1,
			$xpath->query( '//input[@id="ConfirmFileAccept-14" and @name="ConfirmFileAccept"]' )->length,
			'File Locker diff accept confirmation should scope its checkbox ID to the record'
		);
		$this->assertSame(
			1,
			$xpath->query( '//label[@for="ConfirmFileAccept-14"]' )->length,
			'File Locker diff accept label should target the scoped accept checkbox ID'
		);
		$this->assertSame(
			0,
			$xpath->query( '//input[@id="ConfirmFileRestore" or @id="ConfirmFileAccept"]' )->length,
			'File Locker diff template should no longer render global fixed confirmation checkbox IDs'
		);
	}
}
