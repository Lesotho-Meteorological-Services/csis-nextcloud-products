import { addNewFileMenuEntry, getNewFileMenu, Permission, removeNewFileMenuEntry } from '@nextcloud/files'
import axios from '@nextcloud/axios'
import { generateFilePath, generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { getDialogBuilder, showError } from '@nextcloud/dialogs'
import { fetchStructuredProductDefinition, generateStructuredProduct } from './productApi'
import { showProductFormModal } from './productFormModal'
import '@nextcloud/dialogs/style.css'
import './styles.css'

const WEATHER_ENTRY_ID = 'csis-weather-product'
const AGROMET_ENTRY_ID = 'csis-agromet-product'
const CLIMATE_ENTRY_ID = 'csis-climate-product'
const CUSTOM_ENTRY_IDS = new Set([WEATHER_ENTRY_ID, AGROMET_ENTRY_ID, CLIMATE_ENTRY_ID])
const entryContexts = new Map()
const menuSubmenuConfigs = {
	[AGROMET_ENTRY_ID]: {
		displayName: 'New agromet product',
		items: [
			{ label: 'Dekadal', type: 'agromet_dekadal', errorMessage: 'Failed to create dekadal agromet product' },
			{ label: 'Monthly', type: 'agromet_monthly', errorMessage: 'Failed to create monthly agromet product' },
		],
	},
	[CLIMATE_ENTRY_ID]: {
		displayName: 'New climate product',
		items: [
			{ label: 'Seasonal', type: 'climate_seasonal', errorMessage: 'Failed to create climate seasonal forecast' },
			{ label: 'NCOF Report', type: 'climate_ncof_report', errorMessage: 'Failed to create climate NCOF report' },
		],
	},
	[WEATHER_ENTRY_ID]: {
		displayName: 'New weather product',
		items: [
			{ label: 'Morning', type: 'morning', errorMessage: 'Failed to create morning forecast' },
			{ label: 'Two day', type: 'two_day', errorMessage: 'Failed to create two day forecast' },
			{ label: 'Weekly', type: 'weekly', errorMessage: 'Failed to create weekly forecast' },
		],
	},
}
let productSubmenuObserver = null
let activeSubmenuState = null
let submenuHideTimeout = null
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

async function createStructuredProduct(dir, type) {
	const definitionResponse = await fetchStructuredProductDefinition(type)
	const { values } = await showProductFormModal(definitionResponse.data)
	const { data } = await generateStructuredProduct({ dir, type, values })

	const url = generateUrl('/apps/onlyoffice/{fileId}', { fileId: data.fileId })
		+ '?filePath=' + encodeURIComponent(data.filePath)

	window.location.href = url
}

async function createProduct(dir, type) {
	try {
		await createStructuredProduct(dir, type)
	} catch (error) {
		if (error?.message === 'cancelled') {
			return
		}

		if (error?.response?.status === 404) {
			await createForecast(dir, type)
			return
		}

		if (error?.response?.status === 422) {
			const validationErrors = Object.values(error?.response?.data?.errors || {})
			const message = validationErrors[0] || error?.response?.data?.message || t('csis_products', 'Validation failed.')
			showError(String(message))
			return
		}

		throw error
	}
}

function buildSubmenuItem(item, dir, anchor) {
	const listItem = document.createElement('li')
	listItem.className = 'csis-products-submenu__entry'
	listItem.setAttribute('role', 'none')

	const itemNode = document.createElement('div')
	itemNode.className = 'csis-products-submenu__item'
	itemNode.textContent = t('csis_products', item.label)
	itemNode.setAttribute('role', 'menuitem')
	itemNode.tabIndex = -1

	itemNode.addEventListener('click', (event) => {
		event.preventDefault()
		event.stopPropagation()
		hideProductSubmenu()
		createProduct(dir, item.type).catch(() => showError(t('csis_products', item.errorMessage)))
	})
	itemNode.addEventListener('keydown', (event) => {
		if (!activeSubmenuState) {
			return
		}

		const items = activeSubmenuState.items
		const currentIndex = items.indexOf(itemNode)
		if (event.key === 'ArrowDown') {
			event.preventDefault()
			items[(currentIndex + 1) % items.length]?.focus()
		} else if (event.key === 'ArrowUp') {
			event.preventDefault()
			items[(currentIndex - 1 + items.length) % items.length]?.focus()
		} else if (event.key === 'ArrowLeft' || event.key === 'Escape') {
			event.preventDefault()
			hideProductSubmenu()
			anchor.focus()
		} else if (event.key === 'Enter' || event.key === ' ') {
			event.preventDefault()
			itemNode.click()
		}
	})

	listItem.appendChild(itemNode)

	return { listItem, itemNode }
}

function clearSubmenuHideTimeout() {
	if (submenuHideTimeout !== null) {
		window.clearTimeout(submenuHideTimeout)
		submenuHideTimeout = null
	}
}

function scheduleSubmenuHide() {
	clearSubmenuHideTimeout()
	submenuHideTimeout = window.setTimeout(() => {
		hideProductSubmenu()
	}, 140)
}

function hideProductSubmenu() {
	clearSubmenuHideTimeout()
	if (!activeSubmenuState) {
		return
	}

	activeSubmenuState.anchor.classList.remove('csis-products-menu-item--submenu-open')
	activeSubmenuState.anchor.setAttribute('aria-expanded', 'false')
	activeSubmenuState.menu.remove()
	activeSubmenuState = null
}

function positionProductSubmenu(anchor, menu) {
	const anchorRect = anchor.getBoundingClientRect()
	const menuRect = menu.getBoundingClientRect()
	const viewportWidth = window.innerWidth
	const viewportHeight = window.innerHeight
	const gap = 8

	let left = anchorRect.right + gap
	let top = anchorRect.top - 6

	if (left + menuRect.width > viewportWidth - 8) {
		left = Math.max(8, anchorRect.left - menuRect.width - gap)
	}

	if (top + menuRect.height > viewportHeight - 8) {
		top = Math.max(8, viewportHeight - menuRect.height - 8)
	}

	menu.style.left = `${Math.max(8, left)}px`
	menu.style.top = `${Math.max(8, top)}px`
}

function showProductSubmenu(anchor, container, entryId) {
	const config = menuSubmenuConfigs[entryId]
	if (!config) {
		return
	}

	const context = entryContexts.get(entryId) || {}
	const dir = context.path || '/'
	clearSubmenuHideTimeout()

	if (activeSubmenuState?.anchor === anchor) {
		return
	}

	hideProductSubmenu()

	const menu = document.createElement('ul')
	menu.className = 'csis-products-submenu'
	menu.setAttribute('role', 'menu')
	menu.dataset.csisProductsSubmenu = entryId

	const items = []
	for (const item of config.items) {
		const { listItem, itemNode } = buildSubmenuItem(item, dir, anchor)
		menu.appendChild(listItem)
		items.push(itemNode)
	}

	menu.addEventListener('mouseenter', () => {
		clearSubmenuHideTimeout()
	})
	menu.addEventListener('mouseleave', () => {
		scheduleSubmenuHide()
	})

	document.body.appendChild(menu)
	positionProductSubmenu(anchor, menu)

	anchor.classList.add('csis-products-menu-item--submenu-open')
	anchor.setAttribute('aria-expanded', 'true')

	activeSubmenuState = { anchor, container, menu, items, entryId }
}

function findEntryInteractiveElement(entryLabel) {
	const candidates = Array.from(document.querySelectorAll('button, a, li, div'))
	return candidates.find((node) => {
		if (!(node instanceof HTMLElement)) {
			return false
		}

		if (node.closest('.csis-products-submenu')) {
			return false
		}

		if ((node.textContent || '').trim() !== entryLabel) {
			return false
		}

		return true
	}) || null
}

function decorateSubmenuEntry(entryId, config) {
	const element = findEntryInteractiveElement(t('csis_products', config.displayName))
	if (!(element instanceof HTMLElement) || element.dataset.csisProductsSubmenuBound === 'true') {
		return
	}

	const container = element.closest('li, .action-item, .files-new-entry') || element
	element.dataset.csisProductsSubmenuBound = 'true'
	container.classList.add('csis-products-menu-item--has-submenu')
	element.setAttribute('aria-haspopup', 'menu')
	element.setAttribute('aria-expanded', 'false')

	const open = () => showProductSubmenu(element, container, entryId)

	container.addEventListener('mouseenter', open)
	container.addEventListener('mouseleave', () => {
		scheduleSubmenuHide()
	})
	element.addEventListener('click', (event) => {
		event.preventDefault()
		event.stopPropagation()
		open()
	}, true)
	element.addEventListener('keydown', (event) => {
		if (event.key === 'ArrowRight' || event.key === 'Enter' || event.key === ' ') {
			event.preventDefault()
			event.stopPropagation()
			open()
			activeSubmenuState?.items[0]?.focus()
		}

		if (event.key === 'Escape') {
			hideProductSubmenu()
		}
	})
}

function decorateProductMenuEntries() {
	for (const [entryId, config] of Object.entries(menuSubmenuConfigs)) {
		decorateSubmenuEntry(entryId, config)
	}

	if (activeSubmenuState && !document.body.contains(activeSubmenuState.anchor)) {
		hideProductSubmenu()
	}
}

function ensureProductSubmenuObserver() {
	if (productSubmenuObserver !== null) {
		return
	}

	productSubmenuObserver = new MutationObserver(() => {
		decorateProductMenuEntries()
	})
	productSubmenuObserver.observe(document.body, { childList: true, subtree: true })

	document.addEventListener('click', (event) => {
		if (!(event.target instanceof Node)) {
			hideProductSubmenu()
			return
		}

		if (activeSubmenuState?.menu.contains(event.target) || activeSubmenuState?.container.contains(event.target)) {
			return
		}

		hideProductSubmenu()
	})

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') {
			hideProductSubmenu()
		}
	})
}

function buildEntry({
	id,
	displayName,
	dialogTitle,
	dialogText,
	iconSvgInline,
	buttons,
	selectionMode = 'dialog',
}) {
	return {
		id,
		displayName: t('csis_products', displayName),
		iconSvgInline,
		order: -1,

		// Show only where user can create files.
		enabled: (context) => {
			entryContexts.set(id, context || {})
			return ((context?.permissions ?? 0) & Permission.CREATE) !== 0
		},

		async handler(context) {
			if (selectionMode === 'submenu') {
				entryContexts.set(id, context || {})
				return
			}

			const dir = context?.path || '/'

			const dialog = getDialogBuilder(t('csis_products', dialogTitle))
				.setSeverity('info')
				.setText(t('csis_products', dialogText))
				.setButtons([
					{ label: t('csis_products', 'Cancel'), type: 'secondary', callback: () => {} },
					...buttons.map(({ label, type, errorMessage }) => ({
						label: t('csis_products', label),
						type: 'primary',
						callback: () => createProduct(dir, type).catch(() => showError(t('csis_products', errorMessage))),
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
			selectionMode: 'submenu',
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
			selectionMode: 'submenu',
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
			selectionMode: 'submenu',
			buttons: [
				{ label: 'Seasonal', type: 'climate_seasonal', errorMessage: 'Failed to create climate seasonal forecast' },
				{ label: 'NCOF Report', type: 'climate_ncof_report', errorMessage: 'Failed to create climate NCOF report' },
			],
		}))
		window.__csisProductsRegistered = true
		decorateProductMenuEntries()
		console.info('[csis_products] New menu entries registered')
	} catch (error) {
		window.__csisProductsRegistered = false
		console.error('[csis_products] Failed to register New menu entries', error)
	}
}

// Register multiple times to survive Files init ordering in NC33.
ensureProductSubmenuObserver()
registerEntries()
setTimeout(registerEntries, 250)
setTimeout(registerEntries, 1500)
