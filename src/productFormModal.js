import { generateFilePath } from '@nextcloud/router'

const LMS_LOGO_PATH = generateFilePath('csis_products', 'img', 'LMS_Logo.jpg')

const SYNTHETIC_HIGHLIGHTS_FIELD = {
	name: 'highlights',
	label: 'Highlights',
	type: 'taglist',
	required: true,
	placeholder: 'Add a highlight and press Enter',
	helperText: 'Choose predefined highlights or add a custom one.',
	options: [
		{ label: 'Warmer Temperatures', value: 'Warmer Temperatures' },
		{ label: 'Favourable Cumulative Moisture', value: 'Favourable Cumulative Moisture' },
		{ label: 'Heavy Rainfall Risk', value: 'Heavy Rainfall Risk' },
		{ label: 'Dry Conditions', value: 'Dry Conditions' },
		{ label: 'Strong Winds', value: 'Strong Winds' },
		{ label: 'Cold Conditions', value: 'Cold Conditions' },
		{ label: 'Flood Risk', value: 'Flood Risk' },
		{ label: 'Drought Conditions', value: 'Drought Conditions' },
	],
	default: [],
}

const SYNTHETIC_CONTENTS_FIELD = {
	name: 'contents',
	label: 'Contents',
	type: 'taglist',
	required: true,
	placeholder: 'Add a contents item',
	helperText: 'Choose predefined contents or add a custom one.',
	options: [
		{ label: 'Rainfall situation', value: 'Rainfall situation' },
		{ label: 'Temperature conditions', value: 'Temperature conditions' },
		{ label: 'Dekadal rainfall outlook', value: 'Dekadal rainfall outlook' },
		{ label: 'Seasonal outlook', value: 'Seasonal outlook' },
	],
	default: [],
}

const CUSTOM_SELECT_VALUE = '__custom__'
const SEASON_REVIEW_PERIODS = {
	JFM: { startMonth: '01', endMonth: '03', startYearOffset: 0, endYearOffset: 0 },
	FMA: { startMonth: '02', endMonth: '04', startYearOffset: 0, endYearOffset: 0 },
	MAM: { startMonth: '03', endMonth: '05', startYearOffset: 0, endYearOffset: 0 },
	AMJ: { startMonth: '04', endMonth: '06', startYearOffset: 0, endYearOffset: 0 },
	MJJ: { startMonth: '05', endMonth: '07', startYearOffset: 0, endYearOffset: 0 },
	JJA: { startMonth: '06', endMonth: '08', startYearOffset: 0, endYearOffset: 0 },
	JAS: { startMonth: '07', endMonth: '09', startYearOffset: 0, endYearOffset: 0 },
	ASO: { startMonth: '08', endMonth: '10', startYearOffset: 0, endYearOffset: 0 },
	SON: { startMonth: '09', endMonth: '11', startYearOffset: 0, endYearOffset: 0 },
	OND: { startMonth: '10', endMonth: '12', startYearOffset: 0, endYearOffset: 0 },
	NDJ: { startMonth: '11', endMonth: '01', startYearOffset: 0, endYearOffset: 1 },
	DJF: { startMonth: '12', endMonth: '02', startYearOffset: 0, endYearOffset: 1 },
}

function mapSectionFields(section, fieldMap) {
	return (section.fields || []).map((field) => {
		if (typeof field === 'string') {
			return fieldMap.get(field)
		}
		return field
	}).filter(Boolean)
}

function parseForecastYearValue(value) {
	const normalized = String(value || '').trim()
	if (/^\d{4}$/.test(normalized)) {
		return {
			startYear: normalized,
			endYear: normalized,
		}
	}

	const rangeMatch = normalized.match(/^(\d{4})\s*\/\s*(\d{4})$/)
	if (!rangeMatch) {
		return null
	}

	return {
		startYear: rangeMatch[1],
		endYear: rangeMatch[2],
	}
}

function resolveSeasonAutofillRange(season, forecastYearValue) {
	const parsedYears = parseForecastYearValue(forecastYearValue)
	if (!parsedYears) {
		return null
	}

	const seasonConfig = SEASON_REVIEW_PERIODS[season]
	if (!seasonConfig) {
		return null
	}

	const baseYear = parsedYears.startYear === parsedYears.endYear
		? Number(parsedYears.startYear)
		: Number(seasonConfig.startMonth) >= 10 ? Number(parsedYears.startYear) : Number(parsedYears.endYear)

	return {
		startYear: String(baseYear + seasonConfig.startYearOffset),
		endYear: String(baseYear + seasonConfig.endYearOffset),
	}
}

function setMonthyearValue(wrapper, value) {
	if (!(wrapper instanceof HTMLElement)) {
		return
	}

	const monthSelect = wrapper.querySelector('[data-monthyear-month]')
	const yearSelect = wrapper.querySelector('[data-monthyear-year]')
	if (!(monthSelect instanceof HTMLSelectElement) || !(yearSelect instanceof HTMLSelectElement)) {
		return
	}

	if (typeof value === 'string' && /^\d{4}-\d{2}$/.test(value)) {
		const [year, month] = value.split('-')
		monthSelect.value = month
		yearSelect.value = year
		return
	}

	monthSelect.value = ''
	yearSelect.value = ''
}

function escapeHtml(value) {
	return String(value)
		.replaceAll('&', '&amp;')
		.replaceAll('<', '&lt;')
		.replaceAll('>', '&gt;')
		.replaceAll('"', '&quot;')
		.replaceAll("'", '&#39;')
}

function enhanceDefinition(definition) {
	const fields = [...(definition.fields || [])]

	if (definition.type === 'agromet_dekadal' && !fields.some((field) => field.name === 'highlights')) {
		const weatherDescriptionIndex = fields.findIndex((field) => field.name === 'weather_description')
		const insertIndex = weatherDescriptionIndex >= 0 ? weatherDescriptionIndex : fields.length
		fields.splice(insertIndex, 0, SYNTHETIC_HIGHLIGHTS_FIELD)
	}

	if (definition.type === 'agromet_dekadal' && !fields.some((field) => field.name === 'contents')) {
		const weatherDescriptionIndex = fields.findIndex((field) => field.name === 'weather_description')
		const insertIndex = weatherDescriptionIndex >= 0 ? weatherDescriptionIndex : fields.length
		fields.splice(insertIndex, 0, SYNTHETIC_CONTENTS_FIELD)
	}

	const normalizedFields = fields.map((field) => {
		if (field.name === 'weather_type') {
			return {
				...field,
				placeholder: field.placeholder || 'Select weather conditions',
			}
		}

		if (field.name === 'weather_description') {
			return {
				...field,
				placeholder: 'Description for selected weather conditions...',
				rows: Math.max(Number(field.rows || 5), 5),
			}
		}

		return field
	})

	const fieldMap = new Map(normalizedFields.map((field) => [field.name, field]))

	if (Array.isArray(definition.sections) && definition.sections.length > 0) {
		return {
			...definition,
			fields: normalizedFields,
			sections: definition.sections.map((section) => ({
				...section,
				fields: mapSectionFields(section, fieldMap),
			})),
		}
	}

	if (definition.type !== 'agromet_dekadal') {
		return {
			...definition,
			fields: normalizedFields,
			sections: [
				{
					id: 'details',
					title: 'Product details',
					description: 'Complete the required fields before generating the DOCX template.',
					layout: 'grid',
					fields: normalizedFields,
				},
			],
		}
	}

	const pickFields = (names) => names.map((name) => fieldMap.get(name)).filter(Boolean)

	return {
		...definition,
		fields: normalizedFields,
		sections: [
			{
				id: 'metadata',
				title: 'Period & metadata',
				description: 'Define the dekadal period, bulletin number, and review window.',
				layout: 'grid',
				fields: pickFields([
					'period_start',
					'period_end',
					'season',
					'issue_date',
					'bulletin_number',
					'review_period_start',
					'review_period_end',
				]),
			},
			{
				id: 'highlights',
				title: 'Highlights',
				description: 'Capture concise, high-level highlights.',
				layout: 'stack',
				fields: pickFields(['highlights']),
			},
			{
				id: 'contents',
				title: 'Contents',
				description: 'Define the ordered contents list shown in the bulletin side panel.',
				layout: 'stack',
				fields: pickFields(['contents']),
			},
			{
				id: 'weather',
				title: 'Weather conditions',
				description: 'Select the weather types that apply and provide the supporting narrative below.',
				layout: 'stack',
				fields: pickFields(['weather_type', 'weather_description']),
			},
		],
	}
}

function createFieldMarkup(field) {
	const requiredMarker = field.required ? '<span class="csis-product-form__required">*</span>' : ''
	const helpText = field.helperText ? `<p class="csis-product-form__help">${escapeHtml(field.helperText)}</p>` : ''
	const fieldClasses = ['csis-product-form__field']
	const conditionalAttributes = field.showWhen
		? `data-show-when-field="${escapeHtml(field.showWhen.name)}" data-show-when-value="${escapeHtml(field.showWhen.equals)}"`
		: ''

	if (field.type === 'textarea' || field.type === 'taglist' || field.type === 'multiselect' || field.type === 'driverlist' || field.type === 'temperaturetable' || field.type === 'twodaytemperaturetable' || field.type === 'dailyentries') {
		fieldClasses.push('csis-product-form__field--full')
	}

	if (field.type === 'textarea') {
		return `
			<label class="${fieldClasses.join(' ')}" data-field="${escapeHtml(field.name)}">
				<span class="csis-product-form__label">${escapeHtml(field.label)} ${requiredMarker}</span>
				<textarea
					name="${escapeHtml(field.name)}"
					rows="${Number(field.rows || 4)}"
					placeholder="${escapeHtml(field.placeholder || '')}"
					${field.readOnly ? 'readonly' : ''}
				></textarea>
				${helpText}
				<p class="csis-product-form__error" hidden></p>
			</label>
		`
	}

	if (field.type === 'select') {
		const options = (field.options || []).map((option) => `
			<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>
		`).join('')
		const customOption = field.allowCustom
			? `<option value="${CUSTOM_SELECT_VALUE}">${escapeHtml(field.customOptionLabel || 'Add custom value')}</option>`
			: ''
		const customInput = field.allowCustom
			? `
				<input
					type="text"
					class="csis-product-form__select-custom-input"
					data-select-custom-input
					placeholder="${escapeHtml(field.customPlaceholder || 'Add a custom value')}"
					hidden
				>
			`
			: ''

		return `
			<label class="${fieldClasses.join(' ')}" data-field="${escapeHtml(field.name)}" ${conditionalAttributes}>
				<span class="csis-product-form__label">${escapeHtml(field.label)} ${requiredMarker}</span>
				<select
					name="${escapeHtml(field.name)}"
					${field.allowCustom ? `data-select-custom-enabled="true"` : ''}
				>
					<option value="">Select an option</option>
					${options}
					${customOption}
				</select>
				${customInput}
				${helpText}
				<p class="csis-product-form__error" hidden></p>
			</label>
		`
	}

	if (field.type === 'monthyear') {
		const monthOptions = (field.monthOptions || []).map((option) => `
			<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>
		`).join('')
		const yearOptions = (field.yearOptions || []).map((option) => `
			<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>
		`).join('')

		return `
			<fieldset class="${fieldClasses.join(' ')} csis-product-form__fieldset" data-field="${escapeHtml(field.name)}" data-monthyear-field="${escapeHtml(field.name)}">
				<legend class="csis-product-form__label">${escapeHtml(field.label)} ${requiredMarker}</legend>
				<div class="csis-product-form__monthyear">
					<div class="csis-product-form__monthyear-part">
						<label class="csis-product-form__monthyear-subLabel" for="csis-monthyear-month-${escapeHtml(field.name)}">Month</label>
						<select id="csis-monthyear-month-${escapeHtml(field.name)}" data-monthyear-month>
							<option value="">Select month</option>
							${monthOptions}
						</select>
					</div>
					<div class="csis-product-form__monthyear-part">
						<label class="csis-product-form__monthyear-subLabel" for="csis-monthyear-year-${escapeHtml(field.name)}">Year</label>
						<select id="csis-monthyear-year-${escapeHtml(field.name)}" data-monthyear-year>
							<option value="">Select year</option>
							${yearOptions}
						</select>
					</div>
				</div>
				${helpText}
				<p class="csis-product-form__error" hidden></p>
			</fieldset>
		`
	}

	if (field.type === 'multiselect') {
		const options = (field.options || []).map((option) => `
			<li class="csis-product-form__option-item" data-multiselect-item>
				<button
					type="button"
					class="csis-product-form__option-button"
					data-multiselect-option
					data-value="${escapeHtml(option.value)}"
					role="option"
					aria-selected="false"
				>
					<span class="csis-product-form__option-label">${escapeHtml(option.label)}</span>
					<span class="csis-product-form__option-check" aria-hidden="true">Selected</span>
				</button>
			</li>
		`).join('')

		return `
			<fieldset
				class="${fieldClasses.join(' ')} csis-product-form__fieldset"
				data-field="${escapeHtml(field.name)}"
				data-multiselect-field="${escapeHtml(field.name)}"
				data-placeholder="${escapeHtml(field.placeholder || 'Select options')}"
			>
				<legend class="csis-product-form__label">${escapeHtml(field.label)} ${requiredMarker}</legend>
				<div class="csis-product-form__multiselect">
					<button
						type="button"
						class="csis-product-form__multiselect-trigger"
						aria-haspopup="listbox"
						aria-expanded="false"
						aria-controls="csis-product-form-options-${escapeHtml(field.name)}"
					>
						<span class="csis-product-form__multiselect-summary" data-multiselect-summary>
							<span class="csis-product-form__multiselect-placeholder">${escapeHtml(field.placeholder || 'Select options')}</span>
						</span>
						<span class="csis-product-form__multiselect-icon" aria-hidden="true">&#9662;</span>
					</button>
					<div class="csis-product-form__multiselect-menu" data-multiselect-menu hidden>
						<div class="csis-product-form__multiselect-search-wrap">
							<input
								type="text"
								class="csis-product-form__multiselect-search"
								data-multiselect-search
								placeholder="Search options"
								aria-label="${escapeHtml(field.label)} search"
							>
						</div>
						<ul
							id="csis-product-form-options-${escapeHtml(field.name)}"
							class="csis-product-form__multiselect-options"
							role="listbox"
							aria-multiselectable="true"
						>
							${options}
						</ul>
						<p class="csis-product-form__multiselect-empty" data-multiselect-empty hidden>No matching options</p>
					</div>
				</div>
				${helpText}
				<p class="csis-product-form__error" hidden></p>
			</fieldset>
		`
	}

	if (field.type === 'taglist') {
		const optionsMarkup = (field.options || []).map((option) => `
			<button
				type="button"
				class="csis-product-form__taglist-option"
				data-taglist-option
				data-value="${escapeHtml(option.value)}"
			>
				${escapeHtml(option.label)}
			</button>
		`).join('')

		return `
			<fieldset
				class="${fieldClasses.join(' ')} csis-product-form__fieldset"
				data-field="${escapeHtml(field.name)}"
				data-taglist-field="${escapeHtml(field.name)}"
				data-empty-text="${escapeHtml(field.emptyText || `No ${field.label.toLowerCase()} added yet.`)}"
			>
				<legend class="csis-product-form__label">${escapeHtml(field.label)} ${requiredMarker}</legend>
				<div class="csis-product-form__taglist">
					<div class="csis-product-form__taglist-shell">
						<div class="csis-product-form__taglist-values" data-taglist-values></div>
						<div class="csis-product-form__taglist-entry">
							<input
								type="text"
								class="csis-product-form__taglist-input"
								data-taglist-input
								placeholder="${escapeHtml(field.placeholder || 'Add item')}"
								aria-label="${escapeHtml(field.label)} input"
							>
							<button type="button" class="csis-product-form__taglist-add" data-taglist-add>
								Add
							</button>
						</div>
						${optionsMarkup !== '' ? `
							<div class="csis-product-form__taglist-options" data-taglist-options>
								${optionsMarkup}
							</div>
						` : ''}
					</div>
				</div>
				${helpText}
				<p class="csis-product-form__error" hidden></p>
			</fieldset>
		`
	}

	if (field.type === 'driverlist') {
		const driverDescriptionPlaceholder = field.driverDescriptionPlaceholder || 'Add description for selected climate driver'
		const driverDescriptionLabel = field.driverDescriptionLabel || 'Description'
		const emptyText = field.emptyText || 'No climate drivers added yet.'
		const optionsMarkup = (field.options || []).map((option) => `
			<button
				type="button"
				class="csis-product-form__driver-option"
				data-driver-option
				data-value="${escapeHtml(option.value)}"
			>
				${escapeHtml(option.label)}
			</button>
		`).join('')

		return `
			<fieldset
				class="${fieldClasses.join(' ')} csis-product-form__fieldset"
				data-field="${escapeHtml(field.name)}"
				data-driverlist-field="${escapeHtml(field.name)}"
			>
				<legend class="csis-product-form__label">${escapeHtml(field.label)} ${requiredMarker}</legend>
				<div class="csis-product-form__driverlist">
					<div class="csis-product-form__driverlist-shell">
						<div class="csis-product-form__driverlist-values" data-driverlist-values></div>
						<div class="csis-product-form__driverlist-entry">
							<input
								type="text"
								class="csis-product-form__driverlist-input"
								data-driverlist-input
								placeholder="${escapeHtml(field.placeholder || 'Add a climate driver')}"
								aria-label="${escapeHtml(field.label)} input"
							>
							<button type="button" class="csis-product-form__driverlist-add" data-driverlist-add>
								Add
							</button>
						</div>
						${optionsMarkup !== '' ? `
							<div class="csis-product-form__driverlist-options" data-driverlist-options>
								${optionsMarkup}
							</div>
						` : ''}
				</div>
				</div>
				<input type="hidden" data-driver-description-placeholder value="${escapeHtml(driverDescriptionPlaceholder)}">
				<input type="hidden" data-driver-description-label value="${escapeHtml(driverDescriptionLabel)}">
				<input type="hidden" data-driver-empty-text value="${escapeHtml(emptyText)}">
				${helpText}
				<p class="csis-product-form__error" hidden></p>
			</fieldset>
		`
	}

	if (field.type === 'temperaturetable') {
		return `
			<fieldset
				class="${fieldClasses.join(' ')} csis-product-form__fieldset"
				data-field="${escapeHtml(field.name)}"
				data-temperaturetable-field="${escapeHtml(field.name)}"
			>
				<legend class="csis-product-form__label">${escapeHtml(field.label)} ${requiredMarker}</legend>
				<div class="csis-product-form__temperaturetable">
					<div class="csis-product-form__temperaturetable-shell">
						<div class="csis-product-form__temperaturetable-header" aria-hidden="true">
							<span>Area / Station</span>
							<span>Expected Maximum Temperature (°C)</span>
						</div>
						<div class="csis-product-form__temperaturetable-values" data-temperaturetable-values></div>
						<div class="csis-product-form__temperaturetable-actions">
							<button type="button" class="csis-product-form__temperaturetable-add" data-temperaturetable-add>
								Add row
							</button>
						</div>
					</div>
				</div>
				<input type="hidden" data-temperaturetable-empty-text value="${escapeHtml(field.emptyText || 'No temperature rows added yet.')}">
				<input type="hidden" data-temperaturetable-custom-option-label value="${escapeHtml(field.customOptionLabel || 'Add custom area')}">
				<input type="hidden" data-temperaturetable-custom-placeholder value="${escapeHtml(field.customPlaceholder || 'Enter custom area name')}">
				<input type="hidden" data-temperaturetable-options value="${escapeHtml(JSON.stringify(field.areaOptions || []))}">
				${helpText}
				<p class="csis-product-form__error" hidden></p>
			</fieldset>
		`
	}

	if (field.type === 'twodaytemperaturetable') {
		return `
			<fieldset
				class="${fieldClasses.join(' ')} csis-product-form__fieldset"
				data-field="${escapeHtml(field.name)}"
				data-twodaytable-field="${escapeHtml(field.name)}"
			>
				<legend class="csis-product-form__label">${escapeHtml(field.label)} ${requiredMarker}</legend>
				<div class="csis-product-form__twodaytable">
					<div class="csis-product-form__temperaturetable-shell">
						<div class="csis-product-form__twodaytable-header" aria-hidden="true">
							<span>Area / Station</span>
							<span>Max This Afternoon (°C)</span>
							<span>Min Tonight (°C)</span>
							<span>Max Tomorrow (°C)</span>
							<span>Min Tomorrow (°C)</span>
							<span></span>
						</div>
						<div class="csis-product-form__twodaytable-values" data-twodaytable-values></div>
						<div class="csis-product-form__temperaturetable-actions">
							<button type="button" class="csis-product-form__temperaturetable-add" data-twodaytable-add>
								Add row
							</button>
						</div>
					</div>
				</div>
				<input type="hidden" data-twodaytable-empty-text value="${escapeHtml(field.emptyText || 'No temperature rows added yet.')}">
				<input type="hidden" data-twodaytable-custom-option-label value="${escapeHtml(field.customOptionLabel || 'Add custom area')}">
				<input type="hidden" data-twodaytable-custom-placeholder value="${escapeHtml(field.customPlaceholder || 'Enter custom area name')}">
				<input type="hidden" data-twodaytable-options value="${escapeHtml(JSON.stringify(field.areaOptions || []))}">
				${helpText}
				<p class="csis-product-form__error" hidden></p>
			</fieldset>
		`
	}

	if (field.type === 'dailyentries') {
		return `
			<fieldset
				class="${fieldClasses.join(' ')} csis-product-form__fieldset"
				data-field="${escapeHtml(field.name)}"
				data-dailyentries-field="${escapeHtml(field.name)}"
			>
				<legend class="csis-product-form__label">${escapeHtml(field.label)} ${requiredMarker}</legend>
				<div class="csis-product-form__dailyentries-shell">
					<div class="csis-product-form__dailyentries-values" data-dailyentries-values></div>
					<div class="csis-product-form__dailyentries-actions">
						<button type="button" class="csis-product-form__temperaturetable-add" data-dailyentries-add>
							Add day
						</button>
					</div>
				</div>
				<input type="hidden" data-dailyentries-empty-text value="${escapeHtml(field.emptyText || 'No daily forecast entries added yet.')}">
				${helpText}
				<p class="csis-product-form__error" hidden></p>
			</fieldset>
		`
	}

		return `
			<label class="${fieldClasses.join(' ')}" data-field="${escapeHtml(field.name)}" ${conditionalAttributes}>
				<span class="csis-product-form__label">${escapeHtml(field.label)} ${requiredMarker}</span>
				<input
					type="${escapeHtml(field.type || 'text')}"
				name="${escapeHtml(field.name)}"
				placeholder="${escapeHtml(field.placeholder || '')}"
				${field.readOnly ? 'readonly' : ''}
			>
			${helpText}
			<p class="csis-product-form__error" hidden></p>
		</label>
	`
}

function createSectionMarkup(section) {
	return `
		<section class="csis-product-form__section">
			<div class="csis-product-form__section-header">
				<h3 class="csis-product-form__section-title">${escapeHtml(section.title)}</h3>
				${section.description ? `<p class="csis-product-form__section-description">${escapeHtml(section.description)}</p>` : ''}
			</div>
			<div class="csis-product-form__section-fields csis-product-form__section-fields--${escapeHtml(section.layout || 'grid')}">
				${section.fields.map(createFieldMarkup).join('')}
			</div>
		</section>
	`
}

function validateField(field, value) {
	if (field.type === 'multiselect' || field.type === 'taglist') {
		if (field.required && (!Array.isArray(value) || value.length === 0)) {
			return `${field.label} is required.`
		}

		if (field.type === 'multiselect') {
			const allowedValues = new Set((field.options || []).map((option) => option.value))
			if (Array.isArray(value) && value.some((selectedValue) => !allowedValues.has(selectedValue))) {
				return `One or more ${field.label.toLowerCase()} values are invalid.`
			}
		}

		return ''
	}

	if (field.type === 'driverlist') {
		if (field.required && (!Array.isArray(value) || value.length === 0)) {
			return `${field.label} is required.`
		}

		return ''
	}

	if (field.type === 'temperaturetable') {
		if (!Array.isArray(value) || value.length === 0) {
			return field.required ? `${field.label} is required.` : ''
		}

		for (const row of value) {
			if (!row || typeof row !== 'object') {
				return `One or more ${field.label.toLowerCase()} rows are invalid.`
			}

			if (String(row.area || '').trim() === '' || String(row.temperature || '').trim() === '') {
				return `Each ${field.label.toLowerCase()} row must include an area and temperature.`
			}

			const numericTemperature = Number(row.temperature)
			if (!Number.isFinite(numericTemperature)) {
				return `Each ${field.label.toLowerCase()} row must include a numeric temperature.`
			}

			if (numericTemperature < -10 || numericTemperature > 50) {
				return `Each ${field.label.toLowerCase()} row must use a temperature between -10 and 50°C.`
			}
		}

		return ''
	}

	if (field.type === 'twodaytemperaturetable') {
		if (!Array.isArray(value) || value.length === 0) {
			return field.required ? `${field.label} is required.` : ''
		}

		const numericKeys = ['max_this_afternoon', 'min_tonight', 'max_tomorrow', 'min_tomorrow']
		let hasCompletedRow = false

		for (const row of value) {
			if (!row || typeof row !== 'object') {
				return `One or more ${field.label.toLowerCase()} rows are invalid.`
			}

			const hasAnyValue = numericKeys.some((key) => String(row[key] || '').trim() !== '')
			if (!hasAnyValue) {
				continue
			}

			hasCompletedRow = true
			if (String(row.area || '').trim() === '') {
				return `Each ${field.label.toLowerCase()} row must include an area.`
			}

			for (const key of numericKeys) {
				const numericTemperature = Number(row[key])
				if (!Number.isFinite(numericTemperature)) {
					return `Each ${field.label.toLowerCase()} row must include numeric temperatures in every column.`
				}
			}
		}

		if (!hasCompletedRow) {
			return field.required ? `${field.label} is required.` : ''
		}

		return ''
	}

	if (field.type === 'dailyentries') {
		if (!Array.isArray(value) || value.length === 0) {
			return field.required ? `${field.label} is required.` : ''
		}

		let hasCompletedEntry = false
		for (const entry of value) {
			if (!entry || typeof entry !== 'object') {
				return `One or more ${field.label.toLowerCase()} rows are invalid.`
			}

			const hasAnyValue = String(entry.date || '').trim() !== ''
				|| String(entry.daily_description || '').trim() !== ''
				|| String(entry.wind_description || '').trim() !== ''
			if (!hasAnyValue) {
				continue
			}

			hasCompletedEntry = true
			if (!/^\d{4}-\d{2}-\d{2}$/.test(String(entry.date || '').trim())) {
				return `Each ${field.label.toLowerCase()} row must include a valid date.`
			}
			if (String(entry.daily_description || '').trim() === '' || String(entry.wind_description || '').trim() === '') {
				return `Each ${field.label.toLowerCase()} row must include both forecast and wind text.`
			}
		}

		if (!hasCompletedEntry) {
			return field.required ? `${field.label} is required.` : ''
		}

		return ''
	}

	const normalized = String(value || '').trim()
	if (field.required && normalized === '') {
		return `${field.label} is required.`
	}

	if (normalized !== '' && field.type === 'date' && !/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
		return `${field.label} must be a valid date.`
	}

	if (normalized !== '' && field.type === 'time' && !/^\d{2}:\d{2}$/.test(normalized)) {
		return `${field.label} must be a valid time.`
	}

	if (field.type === 'monthyear' && !/^\d{4}-\d{2}$/.test(normalized)) {
		return `${field.label} must include both a month and year.`
	}

	if (normalized !== '' && field.type === 'month' && !/^\d{4}-\d{2}$/.test(normalized)) {
		return `${field.label} must be a valid month.`
	}

	if (field.type === 'select' && field.required && normalized === '') {
		return `${field.label} is required.`
	}

	if (field.type === 'select' && field.allowCustom) {
		const allowedValues = new Set((field.options || []).map((option) => option.value))
		if (normalized !== '' && !allowedValues.has(normalized)) {
			return ''
		}
	}

	return ''
}

function updateSelectCustomState(wrapper) {
	if (!(wrapper instanceof HTMLElement)) {
		return
	}

	const select = wrapper.querySelector('select[name]')
	const customInput = wrapper.querySelector('[data-select-custom-input]')
	if (!(select instanceof HTMLSelectElement) || !(customInput instanceof HTMLInputElement)) {
		return
	}

	const showCustomInput = select.value === CUSTOM_SELECT_VALUE
	customInput.hidden = !showCustomInput
	customInput.disabled = !showCustomInput
	if (!showCustomInput) {
		customInput.value = ''
	}
}

function initializeCustomSelectFields(overlay) {
	const wrappers = Array.from(overlay.querySelectorAll('[data-field]')).filter((wrapper) => (
		wrapper.querySelector('select[data-select-custom-enabled="true"]')
	))

	for (const wrapper of wrappers) {
		const select = wrapper.querySelector('select[data-select-custom-enabled="true"]')
		const customInput = wrapper.querySelector('[data-select-custom-input]')
		if (!(select instanceof HTMLSelectElement) || !(customInput instanceof HTMLInputElement)) {
			continue
		}

		updateSelectCustomState(wrapper)
		select.addEventListener('change', () => {
			updateSelectCustomState(wrapper)
			if (!customInput.hidden) {
				customInput.focus()
			}
		})
	}
}

function getSelectedOptions(wrapper) {
	return Array.from(wrapper.querySelectorAll('[data-multiselect-option][aria-selected="true"]'))
}

function getSelectedValues(wrapper) {
	return getSelectedOptions(wrapper).map((option) => option.dataset.value || '')
}

function renderSummaryMarkup(values, placeholder = 'Select options') {
	if (values.length === 0) {
		return `<span class="csis-product-form__multiselect-placeholder">${escapeHtml(placeholder)}</span>`
	}

	const visibleValues = values.slice(0, 2)
	const chips = visibleValues.map((value) => `
		<span class="csis-product-form__chip">${escapeHtml(value)}</span>
	`).join('')
	const remainingCount = values.length - visibleValues.length
	const moreChip = remainingCount > 0
		? `<span class="csis-product-form__chip csis-product-form__chip--muted">+${remainingCount} more</span>`
		: ''

	return `<span class="csis-product-form__chip-list">${chips}${moreChip}</span>`
}

function updateMultiselectSummary(wrapper) {
	const summary = wrapper.querySelector('[data-multiselect-summary]')
	if (!summary) {
		return
	}

	const placeholder = wrapper.dataset.placeholder || 'Select options'
	const values = getSelectedValues(wrapper)
	summary.innerHTML = renderSummaryMarkup(values, placeholder)
	wrapper.classList.toggle('has-selection', values.length > 0)
}

function filterMultiselectOptions(wrapper, query) {
	const normalizedQuery = query.trim().toLowerCase()
	let visibleCount = 0

	for (const item of wrapper.querySelectorAll('[data-multiselect-item]')) {
		const option = item.querySelector('[data-multiselect-option]')
		const label = option?.querySelector('.csis-product-form__option-label')?.textContent?.toLowerCase() ?? ''
		const visible = normalizedQuery === '' || label.includes(normalizedQuery)
		item.hidden = !visible
		if (visible) {
			visibleCount++
		}
	}

	const emptyState = wrapper.querySelector('[data-multiselect-empty]')
	if (emptyState) {
		emptyState.hidden = visibleCount > 0
	}
}

function initializeMultiselectFields(overlay) {
	const multiselectFields = Array.from(overlay.querySelectorAll('[data-multiselect-field]'))
	if (multiselectFields.length === 0) {
		return () => {}
	}

	const closeAll = ({ exceptWrapper = null, restoreFocus = false } = {}) => {
		for (const wrapper of multiselectFields) {
			if (wrapper === exceptWrapper) {
				continue
			}

			const trigger = wrapper.querySelector('.csis-product-form__multiselect-trigger')
			const menu = wrapper.querySelector('[data-multiselect-menu]')
			const search = wrapper.querySelector('[data-multiselect-search]')
			if (!trigger || !menu) {
				continue
			}

			const isOpen = trigger.getAttribute('aria-expanded') === 'true'
			if (!isOpen) {
				continue
			}

			trigger.setAttribute('aria-expanded', 'false')
			menu.hidden = true
			if (search) {
				search.value = ''
				filterMultiselectOptions(wrapper, '')
			}

			if (restoreFocus) {
				trigger.focus()
			}
		}
	}

	const openMenu = (wrapper) => {
		const trigger = wrapper.querySelector('.csis-product-form__multiselect-trigger')
		const menu = wrapper.querySelector('[data-multiselect-menu]')
		const search = wrapper.querySelector('[data-multiselect-search]')
		if (!trigger || !menu) {
			return
		}

		closeAll({ exceptWrapper: wrapper })
		trigger.setAttribute('aria-expanded', 'true')
		menu.hidden = false
		if (search) {
			search.value = ''
			filterMultiselectOptions(wrapper, '')
			search.focus()
		}
	}

	const toggleSelection = (optionButton) => {
		const currentlySelected = optionButton.getAttribute('aria-selected') === 'true'
		optionButton.setAttribute('aria-selected', String(!currentlySelected))
		optionButton.classList.toggle('is-selected', !currentlySelected)
	}

	const onDocumentClick = (event) => {
		const clickedInside = multiselectFields.some((wrapper) => wrapper.contains(event.target))
		if (!clickedInside) {
			closeAll()
		}
	}

	const onDocumentKeyDown = (event) => {
		if (event.key === 'Escape') {
			closeAll({ restoreFocus: true })
		}
	}

	document.addEventListener('click', onDocumentClick)
	document.addEventListener('keydown', onDocumentKeyDown)

	for (const wrapper of multiselectFields) {
		const trigger = wrapper.querySelector('.csis-product-form__multiselect-trigger')
		const search = wrapper.querySelector('[data-multiselect-search]')
		const options = Array.from(wrapper.querySelectorAll('[data-multiselect-option]'))

		updateMultiselectSummary(wrapper)

		trigger?.addEventListener('click', () => {
			const isExpanded = trigger.getAttribute('aria-expanded') === 'true'
			if (isExpanded) {
				closeAll({ restoreFocus: true })
				return
			}
			openMenu(wrapper)
		})

		search?.addEventListener('input', () => {
			filterMultiselectOptions(wrapper, search.value)
		})

		search?.addEventListener('keydown', (event) => {
			if (event.key === 'ArrowDown') {
				event.preventDefault()
				const firstVisibleOption = options.find((option) => !option.closest('[data-multiselect-item]')?.hidden)
				firstVisibleOption?.focus()
			}
		})

		for (const option of options) {
			option.addEventListener('click', () => {
				toggleSelection(option)
				updateMultiselectSummary(wrapper)
			})

			option.addEventListener('keydown', (event) => {
				if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
					event.preventDefault()
					const visibleOptions = options.filter((candidate) => !candidate.closest('[data-multiselect-item]')?.hidden)
					const currentIndex = visibleOptions.indexOf(option)
					const nextIndex = event.key === 'ArrowDown'
						? Math.min(currentIndex + 1, visibleOptions.length - 1)
						: Math.max(currentIndex - 1, 0)
					visibleOptions[nextIndex]?.focus()
				}

				if (event.key === 'Escape') {
					event.preventDefault()
					closeAll({ restoreFocus: true })
				}
			})
		}
	}

	return () => {
		document.removeEventListener('click', onDocumentClick)
		document.removeEventListener('keydown', onDocumentKeyDown)
	}
}

function normalizeTagValue(value) {
	return String(value || '').trim().replace(/\s+/g, ' ')
}

function getTaglistValues(wrapper) {
	return Array.from(wrapper.querySelectorAll('[data-taglist-value]'))
		.map((chip) => chip.dataset.taglistValue || '')
		.filter(Boolean)
}

function renderTaglist(wrapper, values) {
	const container = wrapper.querySelector('[data-taglist-values]')
	if (!container) {
		return
	}

	if (values.length === 0) {
		const emptyText = wrapper.dataset.emptyText || 'No items added yet.'
		container.innerHTML = `<p class="csis-product-form__taglist-empty">${escapeHtml(emptyText)}</p>`
		wrapper.classList.remove('has-selection')
		updateTaglistOptions(wrapper)
		return
	}

	container.innerHTML = values.map((value) => `
		<span class="csis-product-form__tag" data-taglist-value="${escapeHtml(value)}">
			<span class="csis-product-form__tag-text">${escapeHtml(value)}</span>
			<button type="button" class="csis-product-form__tag-remove" data-taglist-remove aria-label="Remove ${escapeHtml(value)}">
				&times;
			</button>
		</span>
	`).join('')
	wrapper.classList.add('has-selection')
	updateTaglistOptions(wrapper)
}

function updateTaglistOptions(wrapper) {
	const selected = new Set(getTaglistValues(wrapper).map((value) => value.toLowerCase()))
	const query = normalizeTagValue(wrapper.querySelector('[data-taglist-input]')?.value || '').toLowerCase()

	for (const option of wrapper.querySelectorAll('[data-taglist-option]')) {
		const value = option.dataset.value || ''
		const matchesQuery = query === '' || value.toLowerCase().includes(query)
		const isSelected = selected.has(value.toLowerCase())
		option.hidden = !matchesQuery || isSelected
	}
}

function initializeTaglistFields(overlay) {
	const taglistFields = Array.from(overlay.querySelectorAll('[data-taglist-field]'))
	if (taglistFields.length === 0) {
		return
	}

	const addValue = (wrapper) => {
		const input = wrapper.querySelector('[data-taglist-input]')
		if (!input) {
			return
		}

		const nextValue = normalizeTagValue(input.value)
		if (nextValue === '') {
			return
		}

		const values = getTaglistValues(wrapper)
		if (!values.some((value) => value.toLowerCase() === nextValue.toLowerCase())) {
			values.push(nextValue)
		}

		renderTaglist(wrapper, values)
		input.value = ''
		input.focus()
	}

	for (const wrapper of taglistFields) {
		const input = wrapper.querySelector('[data-taglist-input]')
		const addButton = wrapper.querySelector('[data-taglist-add]')
		const defaultValues = wrapper.dataset.defaultValues ? JSON.parse(wrapper.dataset.defaultValues) : []
		const defaults = Array.isArray(defaultValues) ? defaultValues : []

		renderTaglist(wrapper, defaults)
		updateTaglistOptions(wrapper)

		addButton?.addEventListener('click', () => addValue(wrapper))

		input?.addEventListener('input', () => {
			updateTaglistOptions(wrapper)
		})

		input?.addEventListener('keydown', (event) => {
			if (event.key === 'Enter') {
				event.preventDefault()
				addValue(wrapper)
			}
		})

		wrapper.addEventListener('click', (event) => {
			const optionButton = event.target.closest('[data-taglist-option]')
			if (optionButton instanceof HTMLElement) {
				const optionValue = normalizeTagValue(optionButton.dataset.value || '')
				if (optionValue !== '') {
					const values = getTaglistValues(wrapper)
					if (!values.some((value) => value.toLowerCase() === optionValue.toLowerCase())) {
						values.push(optionValue)
					}
					renderTaglist(wrapper, values)
					if (input) {
						input.value = ''
						input.focus()
					}
				}
				return
			}

			const removeButton = event.target.closest('[data-taglist-remove]')
			if (!(removeButton instanceof HTMLElement)) {
				return
			}

			const chip = removeButton.closest('[data-taglist-value]')
			const valueToRemove = chip?.dataset.taglistValue || ''
			const nextValues = getTaglistValues(wrapper).filter((value) => value !== valueToRemove)
			renderTaglist(wrapper, nextValues)
			updateTaglistOptions(wrapper)
			input?.focus()
		})
	}
}

function normalizeDriverName(value) {
	return String(value || '').trim().replace(/\s+/g, ' ')
}

function getDriverValues(wrapper) {
	return Array.from(wrapper.querySelectorAll('[data-driverlist-item]')).map((item) => ({
		name: item.dataset.driverName || '',
		description: String(item.querySelector('[data-driver-description]')?.value ?? '')
			.replaceAll('\r\n', '\n')
			.trim(),
	})).filter((driver) => driver.name !== '')
}

function updateDriverOptions(wrapper) {
	const selected = new Set(getDriverValues(wrapper).map((driver) => driver.name.toLowerCase()))
	const query = normalizeDriverName(wrapper.querySelector('[data-driverlist-input]')?.value || '').toLowerCase()

	for (const option of wrapper.querySelectorAll('[data-driver-option]')) {
		const value = option.dataset.value || ''
		const matchesQuery = query === '' || value.toLowerCase().includes(query)
		const isSelected = selected.has(value.toLowerCase())
		option.hidden = !matchesQuery || isSelected
	}
}

function renderDriverList(wrapper, drivers) {
	const container = wrapper.querySelector('[data-driverlist-values]')
	if (!container) {
		return
	}

	const driverDescriptionPlaceholder = wrapper.querySelector('[data-driver-description-placeholder]')?.getAttribute('value')
		|| 'Add description for selected climate driver'
	const driverDescriptionLabel = wrapper.querySelector('[data-driver-description-label]')?.getAttribute('value')
		|| 'Description'
	const emptyText = wrapper.querySelector('[data-driver-empty-text]')?.getAttribute('value')
		|| 'No climate drivers added yet.'

	if (drivers.length === 0) {
		container.innerHTML = `<p class="csis-product-form__driverlist-empty">${escapeHtml(emptyText)}</p>`
		updateDriverOptions(wrapper)
		return
	}

	container.innerHTML = drivers.map((driver) => `
		<div class="csis-product-form__driver-card" data-driverlist-item data-driver-name="${escapeHtml(driver.name)}">
			<div class="csis-product-form__driver-card-header">
				<span class="csis-product-form__driver-name">${escapeHtml(driver.name)}</span>
				<button type="button" class="csis-product-form__driver-remove" data-driver-remove aria-label="Remove ${escapeHtml(driver.name)}">
					&times;
				</button>
			</div>
			<label class="csis-product-form__driver-description-wrap">
				<span class="csis-product-form__driver-description-label">${escapeHtml(driverDescriptionLabel)}</span>
				<textarea
					class="csis-product-form__driver-description"
					data-driver-description
					rows="3"
					placeholder="${escapeHtml(driverDescriptionPlaceholder)}"
				>${escapeHtml(driver.description || '')}</textarea>
			</label>
		</div>
	`).join('')

	updateDriverOptions(wrapper)
}

function initializeDriverListFields(overlay) {
	const driverFields = Array.from(overlay.querySelectorAll('[data-driverlist-field]'))
	if (driverFields.length === 0) {
		return
	}

	const addDriver = (wrapper, forcedValue = '') => {
		const input = wrapper.querySelector('[data-driverlist-input]')
		const nextName = normalizeDriverName(forcedValue || input?.value || '')
		if (nextName === '') {
			return
		}

		const drivers = getDriverValues(wrapper)
		if (!drivers.some((driver) => driver.name.toLowerCase() === nextName.toLowerCase())) {
			drivers.push({ name: nextName, description: '' })
		}

		renderDriverList(wrapper, drivers)
		if (input) {
			input.value = ''
			input.focus()
		}
	}

	for (const wrapper of driverFields) {
		const input = wrapper.querySelector('[data-driverlist-input]')
		const addButton = wrapper.querySelector('[data-driverlist-add]')
		const defaultDrivers = wrapper.dataset.defaultDrivers ? JSON.parse(wrapper.dataset.defaultDrivers) : []
		const drivers = Array.isArray(defaultDrivers) ? defaultDrivers : []

		renderDriverList(wrapper, drivers)

		addButton?.addEventListener('click', () => addDriver(wrapper))

		input?.addEventListener('input', () => {
			updateDriverOptions(wrapper)
		})

		input?.addEventListener('keydown', (event) => {
			if (event.key === 'Enter') {
				event.preventDefault()
				addDriver(wrapper)
			}
		})

		wrapper.addEventListener('click', (event) => {
			const optionButton = event.target.closest('[data-driver-option]')
			if (optionButton instanceof HTMLElement) {
				addDriver(wrapper, optionButton.dataset.value || '')
				return
			}

			const removeButton = event.target.closest('[data-driver-remove]')
			if (!(removeButton instanceof HTMLElement)) {
				return
			}

			const item = removeButton.closest('[data-driverlist-item]')
			const nameToRemove = item?.dataset.driverName || ''
			const nextDrivers = getDriverValues(wrapper).filter((driver) => driver.name !== nameToRemove)
			renderDriverList(wrapper, nextDrivers)
			input?.focus()
		})

		wrapper.addEventListener('input', (event) => {
			const description = event.target.closest('[data-driver-description]')
			if (!(description instanceof HTMLTextAreaElement)) {
				return
			}

			const item = description.closest('[data-driverlist-item]')
			if (!(item instanceof HTMLElement)) {
				return
			}

			item.dataset.driverDescription = description.value
		})
	}
}

function normalizeTemperatureValue(value) {
	return String(value || '').trim()
}

function getTemperatureTableOptions(wrapper) {
	const rawValue = wrapper.querySelector('[data-temperaturetable-options]')?.getAttribute('value') || '[]'
	try {
		const parsed = JSON.parse(rawValue)
		return Array.isArray(parsed) ? parsed : []
	} catch (error) {
		return []
	}
}

function getTemperatureTableRows(wrapper) {
	return Array.from(wrapper.querySelectorAll('[data-temperaturetable-row]')).map((row) => {
		const areaSelect = row.querySelector('[data-temperaturetable-area-select]')
		const customInput = row.querySelector('[data-temperaturetable-area-custom]')
		const selectedValue = areaSelect?.value || ''
		const area = selectedValue === CUSTOM_SELECT_VALUE
			? normalizeTemperatureValue(customInput?.value || '')
			: normalizeTemperatureValue(selectedValue)
		const temperature = normalizeTemperatureValue(row.querySelector('[data-temperaturetable-temperature]')?.value || '')

		return { area, temperature }
	}).filter((row) => row.area !== '' || row.temperature !== '')
}

function renderTemperatureTable(wrapper, rows) {
	const container = wrapper.querySelector('[data-temperaturetable-values]')
	if (!container) {
		return
	}

	const emptyText = wrapper.querySelector('[data-temperaturetable-empty-text]')?.getAttribute('value')
		|| 'No temperature rows added yet.'
	const customOptionLabel = wrapper.querySelector('[data-temperaturetable-custom-option-label]')?.getAttribute('value')
		|| 'Add custom area'
	const customPlaceholder = wrapper.querySelector('[data-temperaturetable-custom-placeholder]')?.getAttribute('value')
		|| 'Enter custom area name'
	const options = getTemperatureTableOptions(wrapper)

	if (rows.length === 0) {
		container.innerHTML = `<p class="csis-product-form__temperaturetable-empty">${escapeHtml(emptyText)}</p>`
		return
	}

	container.innerHTML = rows.map((row, index) => {
		const matchesOption = options.some((option) => option.value === row.area)
		const selectedAreaValue = matchesOption ? row.area : CUSTOM_SELECT_VALUE
		const areaOptions = options.map((option) => `
			<option value="${escapeHtml(option.value)}" ${option.value === selectedAreaValue ? 'selected' : ''}>
				${escapeHtml(option.label)}
			</option>
		`).join('')

		return `
			<div class="csis-product-form__temperaturetable-row" data-temperaturetable-row data-row-index="${index}">
				<div class="csis-product-form__temperaturetable-area">
					<label class="csis-product-form__temperaturetable-label">Area / Station</label>
					<select class="csis-product-form__temperaturetable-select" data-temperaturetable-area-select>
						<option value="">Select area</option>
						${areaOptions}
						<option value="${CUSTOM_SELECT_VALUE}" ${selectedAreaValue === CUSTOM_SELECT_VALUE ? 'selected' : ''}>${escapeHtml(customOptionLabel)}</option>
					</select>
					<input
						type="text"
						class="csis-product-form__temperaturetable-custom"
						data-temperaturetable-area-custom
						placeholder="${escapeHtml(customPlaceholder)}"
						value="${escapeHtml(selectedAreaValue === CUSTOM_SELECT_VALUE ? row.area : '')}"
						${selectedAreaValue === CUSTOM_SELECT_VALUE ? '' : 'hidden'}
					>
				</div>
				<div class="csis-product-form__temperaturetable-temp">
					<label class="csis-product-form__temperaturetable-label">Expected Maximum Temperature (°C)</label>
					<input
						type="number"
						step="1"
						min="-10"
						max="50"
						inputmode="numeric"
						class="csis-product-form__temperaturetable-number"
						data-temperaturetable-temperature
						value="${escapeHtml(row.temperature || '')}"
					>
				</div>
				<div class="csis-product-form__temperaturetable-row-actions">
					<button type="button" class="csis-product-form__temperaturetable-remove" data-temperaturetable-remove aria-label="Remove row">
						&times;
					</button>
				</div>
			</div>
		`
	}).join('')
}

function initializeTemperatureTableFields(overlay) {
	const wrappers = Array.from(overlay.querySelectorAll('[data-temperaturetable-field]'))
	if (wrappers.length === 0) {
		return
	}

	for (const wrapper of wrappers) {
		const defaults = wrapper.dataset.defaultRows ? JSON.parse(wrapper.dataset.defaultRows) : []
		const rows = Array.isArray(defaults) && defaults.length > 0 ? defaults : [{ area: '', temperature: '' }]
		renderTemperatureTable(wrapper, rows)

		wrapper.addEventListener('change', (event) => {
			const select = event.target.closest('[data-temperaturetable-area-select]')
			if (!(select instanceof HTMLSelectElement)) {
				return
			}

			const row = select.closest('[data-temperaturetable-row]')
			const customInput = row?.querySelector('[data-temperaturetable-area-custom]')
			if (!(customInput instanceof HTMLInputElement)) {
				return
			}

			customInput.hidden = select.value !== CUSTOM_SELECT_VALUE
			if (customInput.hidden) {
				customInput.value = ''
			}
		})

		wrapper.addEventListener('click', (event) => {
			const addButton = event.target.closest('[data-temperaturetable-add]')
			if (addButton instanceof HTMLElement) {
				const rows = getTemperatureTableRows(wrapper)
				rows.push({ area: '', temperature: '' })
				renderTemperatureTable(wrapper, rows)
				return
			}

			const removeButton = event.target.closest('[data-temperaturetable-remove]')
			if (!(removeButton instanceof HTMLElement)) {
				return
			}

			const row = removeButton.closest('[data-temperaturetable-row]')
			if (!(row instanceof HTMLElement)) {
				return
			}

			const rowIndex = Number(row.dataset.rowIndex)
			const rows = getTemperatureTableRows(wrapper).filter((_, index) => index !== rowIndex)
			renderTemperatureTable(wrapper, rows)
		})
	}
}

function getTwoDayTableOptions(wrapper) {
	const rawValue = wrapper.querySelector('[data-twodaytable-options]')?.getAttribute('value') || '[]'
	try {
		const parsed = JSON.parse(rawValue)
		return Array.isArray(parsed) ? parsed : []
	} catch (error) {
		return []
	}
}

function getTwoDayTemperatureRows(wrapper) {
	return Array.from(wrapper.querySelectorAll('[data-twodaytable-row]')).map((row) => {
		const areaSelect = row.querySelector('[data-twodaytable-area-select]')
		const customInput = row.querySelector('[data-twodaytable-area-custom]')
		const selectedValue = areaSelect?.value || ''
		const area = selectedValue === CUSTOM_SELECT_VALUE
			? normalizeTemperatureValue(customInput?.value || '')
			: normalizeTemperatureValue(selectedValue)

		return {
			area,
			max_this_afternoon: normalizeTemperatureValue(row.querySelector('[data-twodaytable-max-this-afternoon]')?.value || ''),
			min_tonight: normalizeTemperatureValue(row.querySelector('[data-twodaytable-min-tonight]')?.value || ''),
			max_tomorrow: normalizeTemperatureValue(row.querySelector('[data-twodaytable-max-tomorrow]')?.value || ''),
			min_tomorrow: normalizeTemperatureValue(row.querySelector('[data-twodaytable-min-tomorrow]')?.value || ''),
		}
	}).filter((row) => row.area !== ''
		|| row.max_this_afternoon !== ''
		|| row.min_tonight !== ''
		|| row.max_tomorrow !== ''
		|| row.min_tomorrow !== '')
}

function renderTwoDayTemperatureTable(wrapper, rows) {
	const container = wrapper.querySelector('[data-twodaytable-values]')
	if (!container) {
		return
	}

	const emptyText = wrapper.querySelector('[data-twodaytable-empty-text]')?.getAttribute('value')
		|| 'No temperature rows added yet.'
	const customOptionLabel = wrapper.querySelector('[data-twodaytable-custom-option-label]')?.getAttribute('value')
		|| 'Add custom area'
	const customPlaceholder = wrapper.querySelector('[data-twodaytable-custom-placeholder]')?.getAttribute('value')
		|| 'Enter custom area name'
	const options = getTwoDayTableOptions(wrapper)

	if (rows.length === 0) {
		container.innerHTML = `<p class="csis-product-form__temperaturetable-empty">${escapeHtml(emptyText)}</p>`
		return
	}

	container.innerHTML = rows.map((row, index) => {
		const matchesOption = options.some((option) => option.value === row.area)
		const selectedAreaValue = matchesOption ? row.area : CUSTOM_SELECT_VALUE
		const areaOptions = options.map((option) => `
			<option value="${escapeHtml(option.value)}" ${option.value === selectedAreaValue ? 'selected' : ''}>
				${escapeHtml(option.label)}
			</option>
		`).join('')

		const renderNumericCell = (label, value, dataAttr) => `
			<div class="csis-product-form__twodaytable-temp">
				<label class="csis-product-form__temperaturetable-label">${escapeHtml(label)}</label>
				<input
					type="number"
					step="1"
					inputmode="numeric"
					class="csis-product-form__temperaturetable-number"
					${dataAttr}
					value="${escapeHtml(value || '')}"
				>
			</div>
		`

		return `
			<div class="csis-product-form__twodaytable-row" data-twodaytable-row data-row-index="${index}">
				<div class="csis-product-form__twodaytable-area">
					<label class="csis-product-form__temperaturetable-label">Area / Station</label>
					<select class="csis-product-form__temperaturetable-select" data-twodaytable-area-select>
						<option value="">Select area</option>
						${areaOptions}
						<option value="${CUSTOM_SELECT_VALUE}" ${selectedAreaValue === CUSTOM_SELECT_VALUE ? 'selected' : ''}>${escapeHtml(customOptionLabel)}</option>
					</select>
					<input
						type="text"
						class="csis-product-form__temperaturetable-custom"
						data-twodaytable-area-custom
						placeholder="${escapeHtml(customPlaceholder)}"
						value="${escapeHtml(selectedAreaValue === CUSTOM_SELECT_VALUE ? row.area : '')}"
						${selectedAreaValue === CUSTOM_SELECT_VALUE ? '' : 'hidden'}
					>
				</div>
				${renderNumericCell('Max This Afternoon (°C)', row.max_this_afternoon, 'data-twodaytable-max-this-afternoon')}
				${renderNumericCell('Min Tonight (°C)', row.min_tonight, 'data-twodaytable-min-tonight')}
				${renderNumericCell('Max Tomorrow (°C)', row.max_tomorrow, 'data-twodaytable-max-tomorrow')}
				${renderNumericCell('Min Tomorrow (°C)', row.min_tomorrow, 'data-twodaytable-min-tomorrow')}
				<div class="csis-product-form__temperaturetable-row-actions">
					<button type="button" class="csis-product-form__temperaturetable-remove" data-twodaytable-remove aria-label="Remove row">
						&times;
					</button>
				</div>
			</div>
		`
	}).join('')
}

function initializeTwoDayTemperatureTableFields(overlay) {
	const wrappers = Array.from(overlay.querySelectorAll('[data-twodaytable-field]'))
	if (wrappers.length === 0) {
		return
	}

	for (const wrapper of wrappers) {
		const defaults = wrapper.dataset.defaultRows ? JSON.parse(wrapper.dataset.defaultRows) : []
		const rows = Array.isArray(defaults) && defaults.length > 0
			? defaults
			: [{ area: '', max_this_afternoon: '', min_tonight: '', max_tomorrow: '', min_tomorrow: '' }]
		renderTwoDayTemperatureTable(wrapper, rows)

		wrapper.addEventListener('change', (event) => {
			const select = event.target.closest('[data-twodaytable-area-select]')
			if (!(select instanceof HTMLSelectElement)) {
				return
			}

			const row = select.closest('[data-twodaytable-row]')
			const customInput = row?.querySelector('[data-twodaytable-area-custom]')
			if (!(customInput instanceof HTMLInputElement)) {
				return
			}

			customInput.hidden = select.value !== CUSTOM_SELECT_VALUE
			if (customInput.hidden) {
				customInput.value = ''
			}
		})

		wrapper.addEventListener('click', (event) => {
			const addButton = event.target.closest('[data-twodaytable-add]')
			if (addButton instanceof HTMLElement) {
				const rows = getTwoDayTemperatureRows(wrapper)
				rows.push({ area: '', max_this_afternoon: '', min_tonight: '', max_tomorrow: '', min_tomorrow: '' })
				renderTwoDayTemperatureTable(wrapper, rows)
				return
			}

			const removeButton = event.target.closest('[data-twodaytable-remove]')
			if (!(removeButton instanceof HTMLElement)) {
				return
			}

			const row = removeButton.closest('[data-twodaytable-row]')
			if (!(row instanceof HTMLElement)) {
				return
			}

			const rowIndex = Number(row.dataset.rowIndex)
			const rows = getTwoDayTemperatureRows(wrapper).filter((_, index) => index !== rowIndex)
			renderTwoDayTemperatureTable(wrapper, rows)
		})
	}
}

function getDailyEntries(wrapper) {
	return Array.from(wrapper.querySelectorAll('[data-dailyentry-item]')).map((item) => ({
		date: String(item.querySelector('[data-dailyentry-date]')?.value ?? '').trim(),
		daily_description: String(item.querySelector('[data-dailyentry-description]')?.value ?? '').trim(),
		wind_description: String(item.querySelector('[data-dailyentry-wind]')?.value ?? '').trim(),
	})).filter((entry) => entry.date !== '' || entry.daily_description !== '' || entry.wind_description !== '')
}

function renderDailyEntries(wrapper, entries) {
	const container = wrapper.querySelector('[data-dailyentries-values]')
	if (!container) {
		return
	}

	const emptyText = wrapper.querySelector('[data-dailyentries-empty-text]')?.getAttribute('value')
		|| 'No daily forecast entries added yet.'
	if (entries.length === 0) {
		container.innerHTML = `<p class="csis-product-form__dailyentries-empty">${escapeHtml(emptyText)}</p>`
		return
	}

	container.innerHTML = entries.map((entry, index) => `
		<div class="csis-product-form__dailyentry-card" data-dailyentry-item data-row-index="${index}">
			<div class="csis-product-form__dailyentry-header">
				<h4 class="csis-product-form__dailyentry-title">${escapeHtml(formatWeeklyEntryHeading(entry.date))}</h4>
				<button type="button" class="csis-product-form__driver-remove" data-dailyentry-remove aria-label="Remove day ${index + 1}">
					&times;
				</button>
			</div>
			<div class="csis-product-form__dailyentry-grid">
				<label class="csis-product-form__dailyentry-field">
					<span class="csis-product-form__temperaturetable-label">Date</span>
					<input type="date" data-dailyentry-date value="${escapeHtml(entry.date || '')}">
				</label>
				<label class="csis-product-form__dailyentry-field csis-product-form__dailyentry-field--full">
					<span class="csis-product-form__temperaturetable-label">Daily description</span>
					<textarea rows="3" data-dailyentry-description placeholder="Describe the weather for this day.">${escapeHtml(entry.daily_description || '')}</textarea>
				</label>
				<label class="csis-product-form__dailyentry-field csis-product-form__dailyentry-field--full">
					<span class="csis-product-form__temperaturetable-label">Wind description</span>
					<input type="text" data-dailyentry-wind placeholder="e.g. Light to moderate north-westerly." value="${escapeHtml(entry.wind_description || '')}">
				</label>
			</div>
		</div>
	`).join('')
}

function buildWeeklyEntryDefaults(startValue, endValue) {
	if (!/^\d{4}-\d{2}-\d{2}$/.test(String(startValue || '')) || !/^\d{4}-\d{2}-\d{2}$/.test(String(endValue || ''))) {
		return null
	}

	const start = new Date(`${startValue}T00:00:00`)
	const end = new Date(`${endValue}T00:00:00`)
	if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) {
		return null
	}

	const entries = []
	const cursor = new Date(start)
	while (cursor <= end && entries.length < 14) {
		const year = cursor.getFullYear()
		const month = String(cursor.getMonth() + 1).padStart(2, '0')
		const day = String(cursor.getDate()).padStart(2, '0')
		entries.push({
			date: `${year}-${month}-${day}`,
			daily_description: '',
			wind_description: '',
		})
		cursor.setDate(cursor.getDate() + 1)
	}

	return entries
}

function formatWeeklyEntryHeading(dateValue) {
	if (!/^\d{4}-\d{2}-\d{2}$/.test(String(dateValue || ''))) {
		return 'Select date'
	}

	const date = new Date(`${dateValue}T00:00:00`)
	if (Number.isNaN(date.getTime())) {
		return 'Select date'
	}

	const day = date.getDate()
	const paddedDay = String(day).padStart(2, '0')
	const suffix = (day % 100 >= 11 && day % 100 <= 13)
		? 'th'
		: ({ 1: 'st', 2: 'nd', 3: 'rd' }[day % 10] || 'th')

	return `${date.toLocaleDateString('en-US', { weekday: 'long' })} ${paddedDay}${suffix}`
}

function initializeDailyEntriesFields(overlay) {
	const wrappers = Array.from(overlay.querySelectorAll('[data-dailyentries-field]'))
	if (wrappers.length === 0) {
		return
	}

	for (const wrapper of wrappers) {
		const defaults = wrapper.dataset.defaultRows ? JSON.parse(wrapper.dataset.defaultRows) : []
		const rows = Array.isArray(defaults) && defaults.length > 0
			? defaults
			: [{ date: '', daily_description: '', wind_description: '' }]
		renderDailyEntries(wrapper, rows)

		wrapper.addEventListener('click', (event) => {
			const addButton = event.target.closest('[data-dailyentries-add]')
			if (addButton instanceof HTMLElement) {
				const rows = getDailyEntries(wrapper)
				rows.push({ date: '', daily_description: '', wind_description: '' })
				renderDailyEntries(wrapper, rows)
				wrapper.dataset.userModified = 'true'
				return
			}

			const removeButton = event.target.closest('[data-dailyentry-remove]')
			if (!(removeButton instanceof HTMLElement)) {
				return
			}

			const row = removeButton.closest('[data-dailyentry-item]')
			if (!(row instanceof HTMLElement)) {
				return
			}

			const rowIndex = Number(row.dataset.rowIndex)
			const rows = getDailyEntries(wrapper).filter((_, index) => index !== rowIndex)
			renderDailyEntries(wrapper, rows)
			wrapper.dataset.userModified = 'true'
		})
	}
}

function updateConditionalFields(overlay) {
	for (const wrapper of overlay.querySelectorAll('[data-show-when-field]')) {
		if (!(wrapper instanceof HTMLElement)) {
			continue
		}

		const dependentField = wrapper.dataset.showWhenField || ''
		const expectedValue = wrapper.dataset.showWhenValue || ''
		const dependentInput = overlay.querySelector(`[name="${CSS.escape(dependentField)}"]`)
		const shouldShow = dependentInput instanceof HTMLInputElement || dependentInput instanceof HTMLSelectElement || dependentInput instanceof HTMLTextAreaElement
			? dependentInput.value === expectedValue
			: false

		wrapper.hidden = !shouldShow
		for (const input of wrapper.querySelectorAll('input, select, textarea')) {
			if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement) {
				input.disabled = !shouldShow
				if (!shouldShow) {
					input.value = ''
				}
			}
		}
	}
}

export function showProductFormModal(rawDefinition) {
	const definition = enhanceDefinition(rawDefinition)

	return new Promise((resolve, reject) => {
		const overlay = document.createElement('div')
		overlay.className = 'csis-product-form'
		if (definition.modalWidth) {
			overlay.style.setProperty('--csis-modal-width', `${definition.modalWidth}px`)
		}
		overlay.innerHTML = `
			<div class="csis-product-form__backdrop"></div>
			<div class="csis-product-form__dialog" role="dialog" aria-modal="true" aria-labelledby="csis-product-form-title">
				<div class="csis-product-form__header">
					<div class="csis-product-form__header-actions">
						<button type="button" class="csis-product-form__icon-button csis-product-form__back" aria-label="Go back">
							<span aria-hidden="true">&larr;</span>
						</button>
						<button type="button" class="csis-product-form__icon-button csis-product-form__close" aria-label="Close dialog">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="csis-product-form__brand">
						<img class="csis-product-form__logo" src="${escapeHtml(LMS_LOGO_PATH)}" alt="Lesotho Meteorological Services logo">
						<p class="csis-product-form__eyebrow">Lesotho Meteorological Services</p>
						<h2 id="csis-product-form-title">${escapeHtml(definition.label)}</h2>
						<p class="csis-product-form__intro">${escapeHtml(definition.subtitle || 'Complete the required fields before generating the DOCX template.')}</p>
					</div>
				</div>
				<form class="csis-product-form__form">
					<div class="csis-product-form__body">
						${definition.sections.map(createSectionMarkup).join('')}
					</div>
					<div class="csis-product-form__actions">
						<button type="button" class="button button-vue-secondary csis-product-form__cancel">Cancel</button>
						<button type="submit" class="button button-vue-primary csis-product-form__submit">Generate Report</button>
					</div>
				</form>
			</div>
		`

		document.body.appendChild(overlay)

		const form = overlay.querySelector('form')
		const submitButton = overlay.querySelector('.csis-product-form__submit')
		const cancelButton = overlay.querySelector('.csis-product-form__cancel')
		const closeButton = overlay.querySelector('.csis-product-form__close')
		const backButton = overlay.querySelector('.csis-product-form__back')
		const destroyMultiselects = initializeMultiselectFields(overlay)
		initializeTaglistFields(overlay)
		initializeDriverListFields(overlay)
		initializeTemperatureTableFields(overlay)
		initializeTwoDayTemperatureTableFields(overlay)
		initializeDailyEntriesFields(overlay)
		initializeCustomSelectFields(overlay)
		updateConditionalFields(overlay)

		const close = (result) => {
			document.removeEventListener('keydown', onKeyDown)
			destroyMultiselects()
			overlay.remove()
			if (result?.cancelled) {
				reject(new Error('cancelled'))
				return
			}
			resolve(result)
		}

		const onKeyDown = (event) => {
			if (event.key === 'Escape') {
				event.preventDefault()
				close({ cancelled: true })
			}
		}

		document.addEventListener('keydown', onKeyDown)

		const touchedFields = new Set()
		const reviewFieldNames = new Set(['review_period_start', 'review_period_end'])
		let isAutofillingReviewPeriod = false

		const setFieldError = (fieldName, message, show = true) => {
			const wrapper = overlay.querySelector(`[data-field="${CSS.escape(fieldName)}"]`)
			const errorNode = wrapper?.querySelector('.csis-product-form__error')
			if (!wrapper || !errorNode) {
				return
			}

			const hasVisibleError = show && Boolean(message)
			wrapper.classList.toggle('has-error', hasVisibleError)
			errorNode.hidden = !hasVisibleError
			errorNode.textContent = hasVisibleError ? message : ''
		}

		const collectValues = () => {
			const values = {}
			for (const field of definition.fields) {
				if (field.type === 'multiselect') {
					const wrapper = overlay.querySelector(`[data-multiselect-field="${CSS.escape(field.name)}"]`)
					values[field.name] = wrapper ? getSelectedValues(wrapper) : []
					continue
				}

				if (field.type === 'taglist') {
					const wrapper = overlay.querySelector(`[data-taglist-field="${CSS.escape(field.name)}"]`)
					values[field.name] = wrapper ? getTaglistValues(wrapper) : []
					continue
				}

				if (field.type === 'driverlist') {
					const wrapper = overlay.querySelector(`[data-driverlist-field="${CSS.escape(field.name)}"]`)
					values[field.name] = wrapper ? getDriverValues(wrapper) : []
					continue
				}

				if (field.type === 'temperaturetable') {
					const wrapper = overlay.querySelector(`[data-temperaturetable-field="${CSS.escape(field.name)}"]`)
					values[field.name] = wrapper ? getTemperatureTableRows(wrapper) : []
					continue
				}

				if (field.type === 'twodaytemperaturetable') {
					const wrapper = overlay.querySelector(`[data-twodaytable-field="${CSS.escape(field.name)}"]`)
					values[field.name] = wrapper ? getTwoDayTemperatureRows(wrapper) : []
					continue
				}

				if (field.type === 'dailyentries') {
					const wrapper = overlay.querySelector(`[data-dailyentries-field="${CSS.escape(field.name)}"]`)
					values[field.name] = wrapper ? getDailyEntries(wrapper) : []
					continue
				}

				if (field.type === 'monthyear') {
					const wrapper = overlay.querySelector(`[data-monthyear-field="${CSS.escape(field.name)}"]`)
					const month = wrapper?.querySelector('[data-monthyear-month]')?.value ?? ''
					const year = wrapper?.querySelector('[data-monthyear-year]')?.value ?? ''
					values[field.name] = month !== '' && year !== '' ? `${year}-${month}` : ''
					continue
				}

				const input = overlay.querySelector(`[name="${CSS.escape(field.name)}"]`)
				if (field.type === 'select' && field.allowCustom) {
					const wrapper = overlay.querySelector(`[data-field="${CSS.escape(field.name)}"]`)
					const customInput = wrapper?.querySelector('[data-select-custom-input]')
					const selectedValue = input?.value ?? ''
					values[field.name] = selectedValue === CUSTOM_SELECT_VALUE
						? String(customInput?.value ?? '').trim()
						: selectedValue
					continue
				}

				values[field.name] = input?.value ?? ''
			}
			return values
		}

		const autofillReviewPeriodFromForecast = () => {
			const forecastSeasonInput = overlay.querySelector('[name="forecast_season"]')
			const forecastYearInput = overlay.querySelector('[name="forecast_year"]')
			if (!(forecastSeasonInput instanceof HTMLSelectElement) || !(forecastYearInput instanceof HTMLSelectElement)) {
				return
			}

			const forecastSeason = forecastSeasonInput.value === CUSTOM_SELECT_VALUE
				? ''
				: String(forecastSeasonInput.value || '').trim()
			const seasonConfig = SEASON_REVIEW_PERIODS[forecastSeason]
			const reviewYears = resolveSeasonAutofillRange(forecastSeason, forecastYearInput.value)
			if (!seasonConfig || !reviewYears) {
				return
			}

			const nextValues = {
				review_period_start: `${reviewYears.startYear}-${seasonConfig.startMonth}`,
				review_period_end: `${reviewYears.endYear}-${seasonConfig.endMonth}`,
			}

			isAutofillingReviewPeriod = true
			for (const [fieldName, nextValue] of Object.entries(nextValues)) {
				const wrapper = overlay.querySelector(`[data-monthyear-field="${CSS.escape(fieldName)}"]`)
				if (!(wrapper instanceof HTMLElement) || wrapper.dataset.userModified === 'true') {
					continue
				}

				setMonthyearValue(wrapper, nextValue)
			}
			isAutofillingReviewPeriod = false
		}

		const autofillWeeklyDailyEntries = () => {
			const dailyWrapper = overlay.querySelector('[data-dailyentries-field="daily_entries"]')
			if (!(dailyWrapper instanceof HTMLElement) || dailyWrapper.dataset.userModified === 'true') {
				return
			}

			const startInput = overlay.querySelector('[name="weekly_period_start"]')
			const endInput = overlay.querySelector('[name="weekly_period_end"]')
			if (!(startInput instanceof HTMLInputElement) || !(endInput instanceof HTMLInputElement)) {
				return
			}

			const defaults = buildWeeklyEntryDefaults(startInput.value, endInput.value)
			if (!defaults || defaults.length === 0) {
				return
			}

			renderDailyEntries(dailyWrapper, defaults)
		}

		const validateForm = ({ forceAll = false } = {}) => {
			const values = collectValues()
			let hasErrors = false

			for (const field of definition.fields) {
				const error = validateField(field, values[field.name])
				const shouldShow = forceAll || touchedFields.has(field.name)
				setFieldError(field.name, error, shouldShow)
				if (error) {
					hasErrors = true
				}
			}

			if (
				values.review_period_start
				&& values.review_period_end
				&& values.review_period_end < values.review_period_start
			) {
				const message = 'Review period end must be on or after review period start.'
				const shouldShow = forceAll || touchedFields.has('review_period_end') || touchedFields.has('review_period_start')
				setFieldError('review_period_end', message, shouldShow)
				hasErrors = true
			}

			if (definition.type === 'morning' && values.forecast_valid_time_mode === '__custom__') {
				if (!String(values.forecast_valid_time_start || '').trim()) {
					const shouldShow = forceAll || touchedFields.has('forecast_valid_time_start')
					setFieldError('forecast_valid_time_start', 'Start time is required for a custom valid time range.', shouldShow)
					hasErrors = true
				}

				if (!String(values.forecast_valid_time_end || '').trim()) {
					const shouldShow = forceAll || touchedFields.has('forecast_valid_time_end')
					setFieldError('forecast_valid_time_end', 'End time is required for a custom valid time range.', shouldShow)
					hasErrors = true
				}
			}

			submitButton.disabled = hasErrors
			return { values, hasErrors }
		}

		for (const field of definition.fields) {
			if (field.type === 'multiselect') {
				const wrapper = overlay.querySelector(`[data-multiselect-field="${CSS.escape(field.name)}"]`)
				if (!wrapper) {
					continue
				}

				for (const option of wrapper.querySelectorAll('[data-multiselect-option]')) {
					const isSelected = Array.isArray(field.default) && field.default.includes(option.dataset.value)
					option.setAttribute('aria-selected', String(isSelected))
					option.classList.toggle('is-selected', isSelected)
				}
				updateMultiselectSummary(wrapper)
				continue
			}

			if (field.type === 'taglist') {
				const wrapper = overlay.querySelector(`[data-taglist-field="${CSS.escape(field.name)}"]`)
				if (wrapper) {
					wrapper.dataset.defaultValues = JSON.stringify(Array.isArray(field.default) ? field.default : [])
					renderTaglist(wrapper, Array.isArray(field.default) ? field.default : [])
				}
				continue
			}

			if (field.type === 'driverlist') {
				const wrapper = overlay.querySelector(`[data-driverlist-field="${CSS.escape(field.name)}"]`)
				if (wrapper) {
					wrapper.dataset.defaultDrivers = JSON.stringify(Array.isArray(field.default) ? field.default : [])
					renderDriverList(wrapper, Array.isArray(field.default) ? field.default : [])
				}
				continue
			}

			if (field.type === 'temperaturetable') {
				const wrapper = overlay.querySelector(`[data-temperaturetable-field="${CSS.escape(field.name)}"]`)
				if (wrapper) {
					wrapper.dataset.defaultRows = JSON.stringify(Array.isArray(field.default) ? field.default : [])
					const rows = Array.isArray(field.default) && field.default.length > 0 ? field.default : [{ area: '', temperature: '' }]
					renderTemperatureTable(wrapper, rows)
				}
				continue
			}

			if (field.type === 'twodaytemperaturetable') {
				const wrapper = overlay.querySelector(`[data-twodaytable-field="${CSS.escape(field.name)}"]`)
				if (wrapper) {
					wrapper.dataset.defaultRows = JSON.stringify(Array.isArray(field.default) ? field.default : [])
					const rows = Array.isArray(field.default) && field.default.length > 0
						? field.default
						: [{ area: '', max_this_afternoon: '', min_tonight: '', max_tomorrow: '', min_tomorrow: '' }]
					renderTwoDayTemperatureTable(wrapper, rows)
				}
				continue
			}

			if (field.type === 'dailyentries') {
				const wrapper = overlay.querySelector(`[data-dailyentries-field="${CSS.escape(field.name)}"]`)
				if (wrapper) {
					wrapper.dataset.defaultRows = JSON.stringify(Array.isArray(field.default) ? field.default : [])
					const rows = Array.isArray(field.default) && field.default.length > 0
						? field.default
						: [{ date: '', daily_description: '', wind_description: '' }]
					renderDailyEntries(wrapper, rows)
				}
				continue
			}

			if (field.type === 'monthyear') {
				const wrapper = overlay.querySelector(`[data-monthyear-field="${CSS.escape(field.name)}"]`)
				if (field.default && typeof field.default === 'string' && /^\d{4}-\d{2}$/.test(field.default)) {
					setMonthyearValue(wrapper, field.default)
				}
				continue
			}

			const input = overlay.querySelector(`[name="${CSS.escape(field.name)}"]`)
			if (!input) {
				continue
			}

			if (field.default !== undefined) {
				if (field.type === 'select' && field.allowCustom) {
					const allowedValues = new Set((field.options || []).map((option) => option.value))
					if (allowedValues.has(field.default)) {
						input.value = field.default
					} else {
						input.value = CUSTOM_SELECT_VALUE
						const wrapper = overlay.querySelector(`[data-field="${CSS.escape(field.name)}"]`)
						const customInput = wrapper?.querySelector('[data-select-custom-input]')
						if (customInput instanceof HTMLInputElement) {
							customInput.value = field.default
						}
						updateSelectCustomState(wrapper)
					}
				} else {
					input.value = field.default
				}
			}

			if (field.readOnly) {
				input.setAttribute('readonly', 'readonly')
				input.setAttribute('aria-readonly', 'true')
			}
		}

		updateConditionalFields(overlay)
		autofillReviewPeriodFromForecast()
		autofillWeeklyDailyEntries()

		const markTouched = (fieldName) => {
			if (fieldName) {
				touchedFields.add(fieldName)
			}
			validateForm()
		}

		overlay.addEventListener('input', (event) => {
			const fieldWrapper = event.target.closest('[data-field]')
			const fieldName = fieldWrapper?.getAttribute('data-field')
			if (fieldName === 'daily_entries') {
				const dailyWrapper = overlay.querySelector('[data-dailyentries-field="daily_entries"]')
				if (dailyWrapper instanceof HTMLElement) {
					dailyWrapper.dataset.userModified = 'true'
				}
			}
			if (fieldName) {
				markTouched(fieldName)
			}
		})

		overlay.addEventListener('change', (event) => {
			const fieldWrapper = event.target.closest('[data-field]')
			const fieldName = fieldWrapper?.getAttribute('data-field')
			if (!isAutofillingReviewPeriod && fieldName && reviewFieldNames.has(fieldName)) {
				const monthyearWrapper = overlay.querySelector(`[data-monthyear-field="${CSS.escape(fieldName)}"]`)
				if (monthyearWrapper instanceof HTMLElement) {
					monthyearWrapper.dataset.userModified = 'true'
				}
			}

			if (fieldName === 'forecast_season' || fieldName === 'forecast_year') {
				autofillReviewPeriodFromForecast()
			}
			if (fieldName === 'weekly_period_start' || fieldName === 'weekly_period_end') {
				autofillWeeklyDailyEntries()
			}

			updateConditionalFields(overlay)

			if (fieldName) {
				markTouched(fieldName)
			}
		})

		overlay.addEventListener('click', (event) => {
			const fieldWrapper = event.target.closest('[data-field]')
			const fieldName = fieldWrapper?.getAttribute('data-field')
			if (fieldName && (
				event.target.closest('[data-taglist-option]')
				|| event.target.closest('[data-taglist-add]')
				|| event.target.closest('[data-taglist-remove]')
				|| event.target.closest('[data-driver-option]')
				|| event.target.closest('[data-driverlist-add]')
				|| event.target.closest('[data-driver-remove]')
				|| event.target.closest('[data-temperaturetable-add]')
				|| event.target.closest('[data-temperaturetable-remove]')
				|| event.target.closest('[data-twodaytable-add]')
				|| event.target.closest('[data-twodaytable-remove]')
				|| event.target.closest('[data-dailyentries-add]')
				|| event.target.closest('[data-dailyentry-remove]')
				|| event.target.closest('[data-multiselect-option]')
			)) {
				requestAnimationFrame(() => markTouched(fieldName))
			}
		})

		validateForm()

		form.addEventListener('submit', (event) => {
			event.preventDefault()
			for (const field of definition.fields) {
				touchedFields.add(field.name)
			}

			const { values, hasErrors } = validateForm({ forceAll: true })
			if (hasErrors) {
				return
			}

			submitButton.disabled = true
			submitButton.textContent = 'Generating...'

			close({ values })
		})

		for (const button of [cancelButton, closeButton, backButton]) {
			button?.addEventListener('click', () => close({ cancelled: true }))
		}

		overlay.querySelector('.csis-product-form__backdrop')?.addEventListener('click', () => close({ cancelled: true }))
	})
}
