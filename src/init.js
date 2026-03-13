import { addNewFileMenuEntry, getNewFileMenu, Permission, removeNewFileMenuEntry } from '@nextcloud/files'
import axios from '@nextcloud/axios'
import { generateFilePath, generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { getDialogBuilder, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'

const WEATHER_ENTRY_ID = 'csis-weather-product'
const AGROMET_ENTRY_ID = 'csis-agromet-product'
const CLIMATE_ENTRY_ID = 'csis-climate-product'
const CUSTOM_ENTRY_IDS = new Set([WEATHER_ENTRY_ID, AGROMET_ENTRY_ID, CLIMATE_ENTRY_ID])
const WEATHER_MENU_ICON = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 30" aria-hidden="true">
	<image href="${generateFilePath('csis_products', 'img', 'new-weather-product.svg')}" x="0" y="0" width="30" height="30" preserveAspectRatio="xMidYMid meet" />
</svg>`
const AGROMET_MENU_ICON = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 30" aria-hidden="true">
	<image href="${generateFilePath('csis_products', 'img', 'new-agromet-product.svg')}" x="0" y="0" width="30" height="30" preserveAspectRatio="xMidYMid meet" />
</svg>`
const CLIMATE_MENU_ICON = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 30" aria-hidden="true">
	<image href="${generateFilePath('csis_products', 'img', 'new-climate-product.svg')}" x="0" y="0" width="30" height="30" preserveAspectRatio="xMidYMid meet" />
</svg>`

async function createForecast(dir, type) {
	const { data } = await axios.post(generateUrl('/apps/csis_products/new'), { dir, type })

	// Open created doc in ONLYOFFICE
	const url = generateUrl('/apps/onlyoffice/{fileId}', { fileId: data.fileId })
		+ '?filePath=' + encodeURIComponent(data.filePath)

	window.location.href = url
}

function buildEntry({
	id,
	displayName,
	dialogTitle,
	dialogText,
	iconSvgInline,
	buttons,
}) {
	return {
		id,
		displayName: t('csis_products', displayName),
		iconSvgInline,
		order: -1,

		// Show only where user can create files.
		enabled: (context) => ((context?.permissions ?? 0) & Permission.CREATE) !== 0,

		async handler(context) {
			const dir = context?.path || '/'

			const dialog = getDialogBuilder(t('csis_products', dialogTitle))
				.setSeverity('info')
				.setText(t('csis_products', dialogText))
				.setButtons([
					{ label: t('csis_products', 'Cancel'), type: 'secondary', callback: () => {} },
					...buttons.map(({ label, type, errorMessage }) => ({
						label: t('csis_products', label),
						type: 'primary',
						callback: () => createForecast(dir, type).catch(() => showError(t('csis_products', errorMessage))),
					})),
				])
				.build()

			await dialog.show()
		},
	}
}

function registerEntries() {
	const newFileMenu = getNewFileMenu()

	for (const entry of [...newFileMenu.getEntries()]) {
		if (!CUSTOM_ENTRY_IDS.has(entry.id)) {
			removeNewFileMenuEntry(entry.id)
		}
	}

	try {
		removeNewFileMenuEntry(WEATHER_ENTRY_ID)
	} catch (error) {
		// Ignore if entry did not exist yet.
	}

	try {
		removeNewFileMenuEntry(AGROMET_ENTRY_ID)
	} catch (error) {
		// Ignore if entry did not exist yet.
	}

	try {
		removeNewFileMenuEntry(CLIMATE_ENTRY_ID)
	} catch (error) {
		// Ignore if entry did not exist yet.
	}

	try {
		addNewFileMenuEntry(buildEntry({
			id: WEATHER_ENTRY_ID,
			displayName: 'New weather product',
			dialogTitle: 'Create weather product',
			dialogText: 'Choose the weather product type to create.',
			iconSvgInline: WEATHER_MENU_ICON,
			buttons: [
				{ label: 'Morning', type: 'morning', errorMessage: 'Failed to create morning forecast' },
				{ label: 'Two day', type: 'two_day', errorMessage: 'Failed to create two day forecast' },
				{ label: 'Weekly', type: 'weekly', errorMessage: 'Failed to create weekly forecast' },
			],
		}))
		addNewFileMenuEntry(buildEntry({
			id: AGROMET_ENTRY_ID,
			displayName: 'New agromet product',
			dialogTitle: 'Create agromet product',
			dialogText: 'Choose the agromet product type to create.',
			iconSvgInline: AGROMET_MENU_ICON,
			buttons: [
				{ label: 'Dekadal', type: 'agromet_dekadal', errorMessage: 'Failed to create dekadal agromet product' },
				{ label: 'Monthly', type: 'agromet_monthly', errorMessage: 'Failed to create monthly agromet product' },
			],
		}))
		addNewFileMenuEntry(buildEntry({
			id: CLIMATE_ENTRY_ID,
			displayName: 'New climate product',
			dialogTitle: 'Create climate product',
			dialogText: 'Choose the climate product type to create.',
			iconSvgInline: CLIMATE_MENU_ICON,
			buttons: [
				{ label: 'Seasonal', type: 'climate_seasonal', errorMessage: 'Failed to create climate seasonal forecast' },
				{ label: 'NCOF Report', type: 'climate_ncof_report', errorMessage: 'Failed to create climate NCOF report' },
			],
		}))
		window.__csisProductsRegistered = true
		console.info('[csis_products] New menu entries registered')
	} catch (error) {
		window.__csisProductsRegistered = false
		console.error('[csis_products] Failed to register New menu entries', error)
	}
}

// Register multiple times to survive Files init ordering in NC33.
registerEntries()
setTimeout(registerEntries, 250)
setTimeout(registerEntries, 1500)
