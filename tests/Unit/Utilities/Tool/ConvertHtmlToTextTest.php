<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Utilities\Tool;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\ConvertHtmlToText;

class ConvertHtmlToTextTest extends BaseUnitTest {

	public function test_converts_full_html_documents_without_losing_structure() :void {
		$html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
	<title>Ignored Title</title>
	<style>.hidden { display:none; }</style>
</head>
<body>
	<p>Hello there.</p>
	<p>Open the <a href="https://example.com/docs">documentation</a>.</p>
</body>
</html>
HTML;

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertStringContainsString( "Hello there.\n\nOpen the documentation (https://example.com/docs).", $text );
		$this->assertStringNotContainsString( 'Ignored Title', $text );
		$this->assertCommonOutputInvariants( $text );
	}

	public function test_converts_table_cells_with_attributes_into_readable_rows() :void {
		$html = <<<'HTML'
<table role="presentation">
	<tr>
		<td style="padding: 3px 10px">IP</td>
		<td><code>198.51.100.10</code></td>
	</tr>
	<tr>
		<th>Action</th>
		<th><a href="https://example.com/report">View report</a></th>
	</tr>
</table>
HTML;

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertStringContainsString( 'IP | 198.51.100.10', $text );
		$this->assertStringContainsString( 'Action | View report (https://example.com/report)', $text );
		$this->assertCommonOutputInvariants( $text );
	}

	public function test_preserves_bullets_and_marketing_links() :void {
		$html = <<<'HTML'
<div>
	<p>Thank you for choosing Shield Security (Free).</p>
	<ul>
		<li>MainWP Integration</li>
		<li>Automatic Import/Export Sync</li>
	</ul>
	<p>&rarr; <a href="https://example.com/upgrade">Go Pro</a></p>
</div>
HTML;

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertStringContainsString( '* MainWP Integration', $text );
		$this->assertStringContainsString( '* Automatic Import/Export Sync', $text );
		$this->assertStringContainsString( 'Go Pro (https://example.com/upgrade)', $text );
		$this->assertCommonOutputInvariants( $text );
	}

	public function test_handles_report_style_summary_markup() :void {
		$html = <<<'HTML'
<table role="presentation" width="100%">
	<tr>
		<td width="25%">
			<div>Scan Issues</div>
			<div>3</div>
			<div>1 new</div>
		</td>
		<td width="25%">
			<div>Repairs</div>
			<div>0</div>
			<div>&#10003; auto-fixed</div>
		</td>
	</tr>
</table>
<p><a href="https://example.com/report">View Full Report</a></p>
HTML;

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertStringContainsString( 'Scan Issues', $text );
		$this->assertStringContainsString( '1 new', $text );
		$this->assertStringContainsString( 'Repairs', $text );
		$this->assertStringContainsString( 'View Full Report (https://example.com/report)', $text );
		$this->assertCommonOutputInvariants( $text );
	}

	public function test_handles_headings_breaks_and_inline_code_without_collapsing_content() :void {
		$html = <<<'HTML'
<section>
	<h2>Diagnostics</h2>
	<p>Run the following command:<br><code>wp shield scan run</code></p>
	<ul>
		<li>Review alerts</li>
		<li>Confirm repairs</li>
	</ul>
</section>
HTML;

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertStringContainsString( 'Diagnostics', $text );
		$this->assertStringContainsString( "Run the following command:\nwp shield scan run", $text );
		$this->assertStringContainsString( '* Review alerts', $text );
		$this->assertStringContainsString( '* Confirm repairs', $text );
		$this->assertCommonOutputInvariants( $text );
	}

	public function test_handles_nested_report_tables_without_leaking_layout_noise() :void {
		$html = <<<'HTML'
<table role="presentation" width="100%">
	<tr>
		<td>
			<table role="presentation" width="100%">
				<tr>
					<td style="padding: 10px 4px 4px; font-size: 12px; font-weight: 600;">Malware Scan</td>
				</tr>
			</table>
			<table role="presentation" width="100%" style="border-collapse: collapse;">
				<tr>
					<td style="padding: 8px 4px;">Known Malware</td>
					<td style="padding: 8px 12px; text-align: right;">4</td>
					<td style="padding: 8px 4px; text-align: right;"><a href="https://example.com/report-1">View Details</a></td>
				</tr>
				<tr>
					<td colspan="3">
						<table role="presentation" width="100%">
							<tr><td style="padding: 3px 4px 3px 12px; font-family: monospace;">/wp-content/plugins/bad.php</td></tr>
							<tr><td style="padding: 3px 4px 3px 12px; font-family: monospace;">/wp-content/uploads/payload.js</td></tr>
							<tr><td style="padding: 3px 4px 8px 12px; font-style: italic;">... and 2 more</td></tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
HTML;

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertStringContainsString( 'Malware Scan', $text );
		$this->assertStringContainsString( 'Known Malware | 4 | View Details (https://example.com/report-1)', $text );
		$this->assertStringContainsString( '/wp-content/plugins/bad.php', $text );
		$this->assertStringContainsString( '/wp-content/uploads/payload.js', $text );
		$this->assertStringContainsString( '... and 2 more', $text );
		$this->assertCommonOutputInvariants( $text );
	}

	public function test_link_conversion_avoids_duplicate_url_output() :void {
		$html = '<p><a href="https://example.com/report">https://example.com/report</a></p>';

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertSame( 'https://example.com/report', $text );
		$this->assertCommonOutputInvariants( $text );
	}

	public function test_removes_non_content_blocks_and_ms_office_comments() :void {
		$html = <<<'HTML'
<!-- regular comment -->
<!--[if mso]>
<table><tr><td>Outlook-only layout</td></tr></table>
<![endif]-->
<style>.hidden { display:none; }</style>
<script>console.log('ignore me');</script>
<p>Visible content</p>
HTML;

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertSame( 'Visible content', $text );
		$this->assertCommonOutputInvariants( $text );
	}

	public function test_decodes_entities_and_keeps_footer_copy_readable() :void {
		$html = <<<'HTML'
<p>Email sent from the Shield Security Plugin v20.0.0, on https://example.com.</p>
<p>Note: Any email delays or delivery issues are caused by website hosting and email providers.</p>
<p>Time Sent: 2026-03-11 12:00</p>
<p>&rarr; <a href="https://example.com/settings">Configure security email recipient (currently admin@example.com)</a></p>
<p>&hellip; <a href="https://example.com/pro">And So Much More</a>&#33;</p>
HTML;

		$text = ( new ConvertHtmlToText() )->run( $html );

		$this->assertStringContainsString( 'Email sent from the Shield Security Plugin v20.0.0, on https://example.com.', $text );
		$this->assertStringContainsString( 'Configure security email recipient (currently admin@example.com) (https://example.com/settings)', $text );
		$this->assertStringContainsString( 'And So Much More (https://example.com/pro)!', $text );
		$this->assertCommonOutputInvariants( $text );
	}

	private function assertCommonOutputInvariants( string $text ) :void {
		$this->assertSame( \trim( $text ), $text, 'Converted text should be trimmed.' );
		$this->assertDoesNotMatchRegularExpression( '#</?[a-z][^>]*>#i', $text );
		$this->assertDoesNotMatchRegularExpression( '/\|\\s*\|/', $text );
		$this->assertDoesNotMatchRegularExpression( "/\n{3,}/", $text );

		foreach ( \explode( "\n", $text ) as $line ) {
			$this->assertSame( \trim( $line ), $line, 'Each rendered line should be trimmed.' );
		}
	}
}
