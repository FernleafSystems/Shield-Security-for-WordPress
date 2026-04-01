const { test, expect } = require( '@playwright/test' );
const {
	openShieldRoute,
	withActionsQueueFixture,
} = require( './support/shield-browser' );
const { ActionsQueuePage } = require( './support/actions-queue-page' );

async function waitForInvestigationTableRows( table ) {
	await expect( table ).toBeVisible();
	await expect.poll( async () => {
		if ( await table.locator( 'tbody td.dataTables_empty' ).count() > 0 ) {
			return 0;
		}
		return await table.locator( 'tbody tr' ).count();
	}, { timeout: 20_000 } ).toBeGreaterThan( 0 );
	await expect( table.locator( 'tbody td.dataTables_empty' ) ).toHaveCount( 0 );
}

test( 'actions queue drills into groups and back out, opening details when available', async ( { page } ) => {
	await withActionsQueueFixture( 'direct_table', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		const bucket = await actionsQueuePage.waitForBucket( fixture.bucket_key );
		await expect( bucket ).toBeVisible();

		const healthyToggle = page.locator( '[data-actions-landing="1"] [data-healthy-disclosure-toggle="1"]' ).first();
		const healthyBody = page.locator( '[data-actions-landing="1"] [data-healthy-disclosure-body="1"]' ).first();
		if ( await healthyToggle.count() > 0 ) {
			await expect( healthyToggle ).toBeVisible();
			await expect( healthyBody ).not.toHaveClass( /is-open/ );
			await expect( healthyToggle ).toHaveAttribute( 'aria-expanded', 'false' );
			await expect( healthyBody ).toHaveAttribute( 'aria-hidden', 'true' );

			await healthyToggle.click();
			await expect( healthyToggle ).toHaveClass( /is-open/ );
			await expect( healthyBody ).toHaveClass( /is-open/ );
			await expect( healthyToggle ).toHaveAttribute( 'aria-expanded', 'true' );
			await expect( healthyBody ).toHaveAttribute( 'aria-hidden', 'false' );

			await healthyToggle.click();
			await expect( healthyToggle ).not.toHaveClass( /is-open/ );
			await expect( healthyBody ).not.toHaveClass( /is-open/ );
			await expect( healthyToggle ).toHaveAttribute( 'aria-expanded', 'false' );
			await expect( healthyBody ).toHaveAttribute( 'aria-hidden', 'true' );
		}

		await actionsQueuePage.clickElement( bucket );
		await expect( page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();
		await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );
		const actionTabs = page.locator( '[data-operator-step-tab="1"]' );
		await expect( actionTabs ).toHaveCount( 3 );
		await expect( actionTabs.first() ).toHaveAttribute( 'data-color-key', 'home' );
		await expect( page.locator( '[data-step-tab-drill-index="0"]' ) ).toHaveText( /Actions Queue/i );
		await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).not.toHaveText( '' );

		const group = await actionsQueuePage.waitForGroupWithRetry( bucket, fixture.group_key );
		if ( group === null ) {
			throw new Error( `Unable to locate Actions Queue group "${fixture.group_key}" in the groups layer.` );
		}
		await actionsQueuePage.clickElement( group );
		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		await expect( page.locator( '[data-drill-layer="1"]' ) ).toHaveClass( /drill-layer--compact/ );
		await waitForInvestigationTableRows( page.locator( '[data-investigation-table="1"]' ).first() );

		await page.locator( '[data-step-tab-drill-index="1"]' ).click();
		await expect( page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();
		await expect( page.locator( '[data-drill-layer="2"]' ) ).toHaveClass( /drill-layer--hidden/ );

		await page.locator( '[data-step-tab-drill-index="0"]' ).click();
		await expect( page.locator( '[data-actions-landing="1"] [data-drill-target="groups"]' ).first() ).toBeVisible();
	} );
} );

test( 'configure toggles healthy zones, drills into diagnosis, and drills back out', async ( { page } ) => {
	await openShieldRoute( page, {
		nav: 'zones',
		nav_sub: 'overview',
	} );

	const healthyToggle = page.locator( '[data-healthy-disclosure-toggle="1"]' ).first();
	const healthyBody = page.locator( '[data-healthy-disclosure-body="1"]' ).first();
	const healthyZone = page.locator( '[data-healthy-disclosure-body="1"] [data-drill-target="diagnosis"]' ).first();
	await expect( healthyToggle ).toBeVisible();
	await expect( healthyToggle ).toHaveAttribute( 'aria-expanded', 'false' );
	await expect( healthyBody ).toHaveAttribute( 'aria-hidden', 'true' );
	await healthyToggle.click();
	await expect( healthyToggle ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( healthyBody ).toHaveAttribute( 'aria-hidden', 'false' );
	await expect( healthyZone ).toBeVisible();

	const zone = page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' )
		.filter( { hasText: /Security Admin/i } )
		.first();
	await expect( zone ).toBeVisible();

	await zone.click();
	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();
	await expect( page.locator( '[data-drill-layer="0"]' ) ).toHaveClass( /drill-layer--compact/ );
	const configureTabs = page.locator( '[data-operator-step-tab="1"]' );
	await expect( configureTabs ).toHaveCount( 3 );
	await expect( configureTabs.first() ).toHaveAttribute( 'data-color-key', 'home' );
	await expect( page.locator( '[data-step-tab-drill-index="0"]' ) ).toHaveText( /Configure/i );
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).toHaveText( /Security Admin/i );
	await expect( page.locator( '[data-configure-diagnosis="1"] [data-drill-target="editor"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '[data-configure-diagnosis="1"] .zone-summary-header' ) ).toHaveCount( 0 );
	const expandRow = page.locator( '[data-configure-diagnosis="1"] [data-shield-expand-trigger="1"]' ).first();
	await expect( expandRow.locator( '.shield-detail-row__expand-cta' ) ).toBeVisible();
	await expandRow.click();
	await expect( page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show' ).first() ).toBeVisible();
	const visibleExpandedForms = page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show form.options_form_for' );
	await expect( visibleExpandedForms.first() ).toBeVisible();
	await page.locator( '[data-configure-diagnosis="1"] .shield-detail-expansion.show .shield-detail-expansion__btn-save' )
		.first()
		.click();
	await expect( visibleExpandedForms ).toHaveCount( 0, { timeout: 20_000 } );
	await expect( page.locator( '[data-configure-diagnosis="1"]' ) ).toBeVisible();
	await expect( configureTabs ).toHaveCount( 3 );
	await expect( page.locator( '[data-step-tab-drill-index="0"]' ) ).toHaveText( /Configure/i );
	await expect( page.locator( '[data-operator-context-rail="1"] .operator-context-rail__title' ) ).toHaveText( /Security Admin/i );
	const refreshedExpandRow = page.locator( '[data-configure-diagnosis="1"] [data-shield-expand-trigger="1"]' ).first();
	await expect( refreshedExpandRow.locator( '.shield-detail-row__expand-cta' ) ).toBeVisible();
	await refreshedExpandRow.click();
	await expect( visibleExpandedForms.first() ).toBeVisible();
	const diagnosisHealthyToggle = page.locator( '[data-configure-diagnosis="1"] [data-healthy-disclosure-toggle="1"]' );
	const diagnosisHealthyBody = page.locator( '[data-configure-diagnosis="1"] [data-healthy-disclosure-body="1"]' );
	if ( await diagnosisHealthyToggle.count() > 0 ) {
		await expect( diagnosisHealthyToggle ).toBeVisible();
		await expect( diagnosisHealthyBody ).not.toHaveClass( /is-open/ );
		await expect( diagnosisHealthyToggle ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( diagnosisHealthyBody ).toHaveAttribute( 'aria-hidden', 'true' );
		const diagnosisHealthyRows = diagnosisHealthyBody.locator( '.shield-detail-row' );
		expect( await diagnosisHealthyRows.count() ).toBeGreaterThan( 0 );

		await diagnosisHealthyToggle.click();
		await expect( diagnosisHealthyToggle ).toHaveClass( /is-open/ );
		await expect( diagnosisHealthyBody ).toHaveClass( /is-open/ );
		await expect( diagnosisHealthyToggle ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( diagnosisHealthyBody ).toHaveAttribute( 'aria-hidden', 'false' );
		await expect( diagnosisHealthyRows.first() ).toBeVisible();

		await diagnosisHealthyToggle.click();
		await expect( diagnosisHealthyToggle ).not.toHaveClass( /is-open/ );
		await expect( diagnosisHealthyBody ).not.toHaveClass( /is-open/ );
		await expect( diagnosisHealthyToggle ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( diagnosisHealthyBody ).toHaveAttribute( 'aria-hidden', 'true' );
	}

	await page.locator( '[data-step-tab-drill-index="0"]' ).click();
	await expect( page.locator( '[data-configure-landing="1"] [data-drill-target="diagnosis"]' ).first() ).toBeVisible();
} );

test( 'actions queue restores the same ignored-plugin asset panel after the shared table success event', async ( { page } ) => {
	await withActionsQueueFixture( 'ignored_plugin_asset_cards', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );
		await expect( page.locator( '[data-mode-shell="1"][data-mode="actions_queue_assets"]' ) ).toBeVisible();

		const panel = await actionsQueuePage.openAssetPanel( fixture.panel_target );
		const investigationTable = panel.locator( '[data-investigation-table="1"]' );
		await expect( investigationTable ).toBeVisible();
		await waitForInvestigationTableRows( investigationTable );
		await investigationTable.evaluate( ( table ) => {
			table.dispatchEvent( new CustomEvent( 'shield:table-action-success', {
				bubbles: true,
			} ) );
		} );

		const refreshedPanel = actionsQueuePage.assetPanel( fixture.panel_target );
		const refreshedTile = actionsQueuePage.assetTile( fixture.panel_target );
		await expect( page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
		await expect( refreshedTile ).toHaveAttribute( 'aria-expanded', 'true', { timeout: 20_000 } );
		await expect( refreshedPanel ).toHaveAttribute( 'aria-hidden', 'false', { timeout: 20_000 } );
		await expect( refreshedPanel.locator( '[data-investigation-table="1"]' ) ).toBeVisible();
	} );
} );

test( 'actions queue lazy-loads the file locker asset panel to a terminal state on demand', async ( { page } ) => {
	await withActionsQueueFixture( 'file_locker_lazy', async ( fixture ) => {
		const actionsQueuePage = new ActionsQueuePage( page );
		await openShieldRoute( page, {
			nav: 'scans',
			nav_sub: 'overview',
		} );

		await actionsQueuePage.drillToDetail( fixture );
		const panel = actionsQueuePage.assetPanel( fixture.panel_target );
		await expect( panel ).toHaveAttribute( 'data-actions-queue-asset-panel-lazy', '1' );
		await expect( panel ).toHaveAttribute( 'data-actions-queue-asset-panel-loaded', '0' );

		await actionsQueuePage.openAssetPanel( fixture.panel_target );
		await expect.poll( async () => {
			return await panel.getAttribute( 'data-actions-queue-asset-panel-loading' ) || '';
		}, { timeout: 20_000 } ).toBe( '' );

		const failureAlert = panel.locator( '.alert.alert-warning' );
		if ( await failureAlert.count() > 0 ) {
			await expect( failureAlert ).toContainText( /Unable to load these scan details/i );
		}
		else {
			await expect( panel ).toHaveAttribute( 'data-actions-queue-asset-panel-loaded', '1', { timeout: 20_000 } );
			await expect( panel.locator( 'form.filelocker_fileaction' ) ).toBeVisible();
		}
	} );
} );
