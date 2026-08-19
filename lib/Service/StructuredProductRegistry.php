<?php
declare(strict_types=1);

namespace OCA\CSISProducts\Service;

class StructuredProductRegistry {
	private const CLIMATE_DRIVER_OPTIONS = [
		'Tropical Cyclones',
		'ENSO',
		'MJO',
		'Heat waves',
		'Moisture deficit',
		'Frontal systems',
		'Local convection',
	];

	private const CLIMATE_HIGHLIGHT_OPTIONS = [
		'Above-normal rainfall likelihood',
		'Warmer than average temperatures',
		'Delayed onset risk',
		'Dry spell risk',
		'Flood risk',
		'Strong convective activity',
	];

	private const OUTLOOK_OPTIONS = [
		'Below normal',
		'Normal',
		'Above normal',
		'Normal to above normal',
		'Normal to below normal',
	];

	private const CONFIDENCE_LEVEL_OPTIONS = [
		'Low',
		'Moderate',
		'High',
	];

	private const FORECAST_SEASON_OPTIONS = [
		'JFM',
		'FMA',
		'MAM',
		'AMJ',
		'MJJ',
		'JJA',
		'JAS',
		'ASO',
		'SON',
		'OND',
		'NDJ',
		'DJF',
	];

	private const TEMPERATURE_OUTLOOK_OPTIONS = [
		'Below normal',
		'Normal',
		'Above normal',
	];

	private const CONTENT_OPTIONS = [
		'Rainfall situation',
		'Temperature conditions',
		'Dekadal rainfall outlook',
		'Seasonal outlook',
	];

	private const HIGHLIGHT_OPTIONS = [
		'Warmer Temperatures',
		'Favourable Cumulative Moisture',
		'Heavy Rainfall Risk',
		'Dry Conditions',
		'Strong Winds',
		'Cold Conditions',
		'Flood Risk',
		'Drought Conditions',
	];

	private const WEATHER_TYPE_OPTIONS = [
		'Dry conditions',
		'Warm conditions',
		'Cold conditions',
		'Heavy rainfall',
		'Light rainfall',
		'Scattered thunderstorms',
		'Widespread thunderstorms',
		'Strong winds',
		'Hail risk',
		'Flood risk',
		'Drought conditions',
	];

	private const MORNING_FORECAST_VALID_TIMES = [
		'06:00 – 18:00',
		'06:00 – 12:00',
		'06:00 – 15:00',
	];

	private const MORNING_AREA_OPTIONS = [
		'Maseru',
		'Moshoeshoe I',
		'Mafeteng',
		'Mohale’s Hoek',
		'Quthing',
		'Qacha’s Nek',
		'Mokhotlong',
		'Oxbow',
		'Butha-Buthe',
		'Leribe',
		'Berea',
		'Thaba-Tseka',
		'Semonkong',
	];

	private const NCOF_SECTOR_OPTIONS = [
		'Agriculture and food security',
		'Water resources',
		'Disaster risk reduction',
		'Health',
		'Energy',
		'Transport and infrastructure',
		'Livestock',
		'Environment and ecosystems',
	];

	public function has(string $type): bool {
		return $this->get($type) !== null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get(string $type): ?array {
		$todayDate = new \DateTimeImmutable('today');
		$today = $todayDate->format('Y-m-d');
		$tomorrow = $todayDate->modify('+1 day')->format('Y-m-d');
		$fourDayEnd = $todayDate->modify('+3 days')->format('Y-m-d');
		$weekEnd = $todayDate->modify('+6 days')->format('Y-m-d');
		$fourDayEntries = [];
		for ($dayOffset = 0; $dayOffset < 4; $dayOffset++) {
			$fourDayEntries[] = [
				'date' => $todayDate->modify(sprintf('+%d days', $dayOffset))->format('Y-m-d'),
				'daily_description' => '',
				'wind_description' => '',
			];
		}
		$currentYear = (int)$todayDate->format('Y');
		$currentMonth = (int)$todayDate->format('n');
		$currentSeason = $currentMonth >= 7
			? sprintf('%d/%d', $currentYear, $currentYear + 1)
			: sprintf('%d/%d', $currentYear - 1, $currentYear);
		[$dekadStartDate, $dekadEndDate] = $this->dekadBounds($todayDate);
		[$reviewStartDate, $reviewEndDate] = $this->dekadBounds($dekadStartDate->modify('-1 day'));
		$monthOptions = $this->monthOptions();
		$yearOptions = $this->yearOptions($currentYear - 5, $currentYear + 2);
		$forecastYearOptions = $this->forecastYearOptions($currentYear - 1, $currentYear + 2);
		$forecastPeriodOptions = $this->forecastPeriodOptions($currentYear);

		$definitions = [
			'agromet_dekadal' => [
				'type' => 'agromet_dekadal',
				'category' => 'agromet',
				'label' => 'Agromet Dekadal',
				'template' => 'Agromet_Dekadal.docx',
				'placeholders' => [
					'period_start',
					'period_end',
					'season',
					'issue_date',
					'bulletin_number',
					'review_period_start',
					'review_period_end',
					'highlights_block',
					'contents_block',
					'weather_type',
					'weather_description',
				],
				'fields' => [
					[
						'name' => 'period_start',
						'label' => 'Period start',
						'type' => 'date',
						'required' => true,
						'default' => $dekadStartDate->format('Y-m-d'),
					],
					[
						'name' => 'period_end',
						'label' => 'Period end',
						'type' => 'date',
						'required' => true,
						'default' => $dekadEndDate->format('Y-m-d'),
					],
					[
						'name' => 'season',
						'label' => 'Season',
						'type' => 'select',
						'required' => true,
						'default' => $currentSeason,
						'allowCustom' => true,
						'customOptionLabel' => 'Other season',
						'customPlaceholder' => 'Enter a season, for example 2025/2026',
						'helperText' => 'Select the agricultural season or enter a custom season.',
						'options' => $forecastYearOptions,
					],
					[
						'name' => 'issue_date',
						'label' => 'Issue date',
						'type' => 'date',
						'required' => true,
						'default' => $today,
						'readOnly' => true,
					],
					[
						'name' => 'bulletin_number',
						'label' => 'Bulletin number',
						'type' => 'text',
						'required' => true,
						'placeholder' => '2/2',
					],
					[
						'name' => 'review_period_start',
						'label' => 'Review period start',
						'type' => 'date',
						'required' => true,
						'default' => $reviewStartDate->format('Y-m-d'),
					],
					[
						'name' => 'review_period_end',
						'label' => 'Review period end',
						'type' => 'date',
						'required' => true,
						'default' => $reviewEndDate->format('Y-m-d'),
					],
					[
						'name' => 'highlights',
						'label' => 'Highlights',
						'type' => 'taglist',
						'required' => true,
						'placeholder' => 'Add a highlight',
						'emptyText' => 'No highlights added yet.',
						'helperText' => 'Choose predefined highlights or add a custom one.',
						'options' => array_map(
							static fn (string $option): array => ['label' => $option, 'value' => $option],
							self::HIGHLIGHT_OPTIONS,
						),
					],
					[
						'name' => 'contents',
						'label' => 'Contents',
						'type' => 'taglist',
						'required' => true,
						'placeholder' => 'Add a contents item',
						'emptyText' => 'No contents items added yet.',
						'helperText' => 'Choose predefined contents or add a custom one.',
						'options' => array_map(
							static fn (string $option): array => ['label' => $option, 'value' => $option],
							self::CONTENT_OPTIONS,
						),
					],
					[
						'name' => 'weather_type',
						'label' => 'Weather type',
						'type' => 'multiselect',
						'required' => true,
						'options' => array_map(
							static fn (string $option): array => ['label' => $option, 'value' => $option],
							self::WEATHER_TYPE_OPTIONS,
						),
					],
					[
						'name' => 'weather_description',
						'label' => 'Weather description',
						'type' => 'textarea',
						'required' => true,
						'placeholder' => 'Describe the expected weather impacts and evolution for this dekad.',
						'rows' => 6,
					],
				],
			],
			'morning' => [
				'type' => 'morning',
				'category' => 'weather',
				'label' => 'Morning Weather Forecast',
				'subtitle' => 'Complete the required fields to generate the morning weather forecast.',
				'modalWidth' => 720,
				'template' => '722C_Morning Forecast.docx',
				'placeholders' => [
					'issue_date',
					'forecast_valid_time',
					'weather_description_en',
					'weather_description_st',
				],
				'fields' => [
					[
						'name' => 'issue_date',
						'label' => 'Issue date',
						'type' => 'date',
						'required' => true,
						'default' => $today,
						'readOnly' => true,
						'helperText' => 'Auto-filled with today\'s issue date.',
					],
					[
						'name' => 'forecast_valid_time_mode',
						'type' => 'segmented',
						'label' => 'Forecast valid time range',
						'required' => true,
						'default' => self::MORNING_FORECAST_VALID_TIMES[0],
						'helperText' => 'Choose a standard valid period or enter a custom time range.',
						'options' => [
							...$this->mapOptions(self::MORNING_FORECAST_VALID_TIMES),
							['label' => 'Custom...', 'value' => '__custom__'],
						],
					],
					[
						'name' => 'forecast_valid_time_start',
						'label' => 'Start time',
						'type' => 'time',
						'required' => false,
						'showWhen' => [
							'name' => 'forecast_valid_time_mode',
							'equals' => '__custom__',
						],
					],
					[
						'name' => 'forecast_valid_time_end',
						'label' => 'End time',
						'type' => 'time',
						'required' => false,
						'showWhen' => [
							'name' => 'forecast_valid_time_mode',
							'equals' => '__custom__',
						],
					],
					[
						'name' => 'weather_description_en',
						'label' => 'Weather description (English)',
						'type' => 'textarea',
						'required' => true,
						'rows' => 5,
						'placeholder' => 'Describe the weather conditions in English.',
					],
					[
						'name' => 'weather_description_st',
						'label' => 'Weather description (Sesotho)',
						'type' => 'textarea',
						'required' => true,
						'rows' => 5,
						'placeholder' => 'Describe the weather conditions in Sesotho.',
					],
					[
						'name' => 'temperature_table',
						'label' => 'Expected maximum temperatures by area',
						'type' => 'temperaturetable',
						'required' => true,
						'helperText' => 'Enter the expected maximum temperature for each forecast area or station.',
						'emptyText' => 'No temperature rows added yet.',
						'areaOptions' => $this->mapOptions(self::MORNING_AREA_OPTIONS),
					],
				],
				'sections' => [
					[
						'id' => 'identity',
						'title' => 'Document identity',
						'description' => 'Set the issue date and valid time for this morning forecast.',
						'layout' => 'grid',
						'fields' => ['issue_date', 'forecast_valid_time_mode', 'forecast_valid_time_start', 'forecast_valid_time_end'],
					],
					[
						'id' => 'descriptions',
						'title' => 'Forecast narrative',
						'description' => 'Provide the morning weather description in English and Sesotho.',
						'layout' => 'stack',
						'fields' => ['weather_description_en', 'weather_description_st'],
					],
					[
						'id' => 'temperatures',
						'title' => 'Expected maximum temperatures by area',
						'description' => 'Capture the expected maximum temperature for each area.',
						'layout' => 'stack',
						'fields' => ['temperature_table'],
					],
				],
			],
			'two_day' => [
				'type' => 'two_day',
				'category' => 'weather',
				'label' => 'Two Day Forecast',
				'subtitle' => 'Complete the required fields to generate the two day weather forecast.',
				'modalWidth' => 720,
				'template' => '722A_Two Day Forecast.docx',
				'placeholders' => [
					'issue_date',
					'forecast_valid_until_date',
					'forecast_valid_until_time',
					'today_date',
					'today_description_english',
					'tomorrow_date',
					'tomorrow_description_english',
					'kajeno_date',
					'kajeno_description_sesotho',
					'hosane_date',
					'hosane_description_sesotho',
				],
				'fields' => [
					[
						'name' => 'issue_date',
						'label' => 'Issue date',
						'type' => 'date',
						'required' => true,
						'default' => $today,
						'readOnly' => true,
						'helperText' => 'Auto-filled with today\'s issue date.',
					],
					[
						'name' => 'forecast_valid_until_date',
						'label' => 'Forecast valid until date',
						'type' => 'date',
						'required' => true,
						'default' => $tomorrow,
					],
					[
						'name' => 'forecast_valid_until_time',
						'label' => 'Forecast valid until time',
						'type' => 'time',
						'required' => true,
						'default' => '18:00:00',
					],
					[
						'name' => 'today_date',
						'label' => 'Today date',
						'type' => 'date',
						'required' => true,
						'default' => $today,
					],
					[
						'name' => 'today_description_english',
						'label' => 'Today description (English)',
						'type' => 'textarea',
						'required' => true,
						'rows' => 4,
						'placeholder' => 'Describe today\'s forecast in English.',
					],
					[
						'name' => 'tomorrow_date',
						'label' => 'Tomorrow date',
						'type' => 'date',
						'required' => true,
						'default' => $tomorrow,
					],
					[
						'name' => 'tomorrow_description_english',
						'label' => 'Tomorrow description (English)',
						'type' => 'textarea',
						'required' => true,
						'rows' => 4,
						'placeholder' => 'Describe tomorrow\'s forecast in English.',
					],
					[
						'name' => 'kajeno_date',
						'label' => 'Kajeno date',
						'type' => 'date',
						'required' => true,
						'default' => $today,
					],
					[
						'name' => 'kajeno_description_sesotho',
						'label' => 'Kajeno description (Sesotho)',
						'type' => 'textarea',
						'required' => true,
						'rows' => 4,
						'placeholder' => 'Hlalosa boemo ba lehodimo ba kajeno ka Sesotho.',
					],
					[
						'name' => 'hosane_date',
						'label' => 'Hosane date',
						'type' => 'date',
						'required' => true,
						'default' => $tomorrow,
					],
					[
						'name' => 'hosane_description_sesotho',
						'label' => 'Hosane description (Sesotho)',
						'type' => 'textarea',
						'required' => true,
						'rows' => 4,
						'placeholder' => 'Hlalosa boemo ba lehodimo ba hosane ka Sesotho.',
					],
					[
						'name' => 'temperature_table',
						'label' => 'Expected temperatures by area',
						'type' => 'twodaytemperaturetable',
						'required' => true,
						'helperText' => 'Enter the forecast maximum and minimum temperatures for each area or station.',
						'emptyText' => 'No temperature rows added yet.',
						'areaOptions' => $this->mapOptions(self::MORNING_AREA_OPTIONS),
						'default' => [[
							'area' => '',
							'max_this_afternoon' => '',
							'min_tonight' => '',
							'max_tomorrow' => '',
							'min_tomorrow' => '',
						]],
					],
				],
				'sections' => [
					[
						'id' => 'identity',
						'title' => 'Document identity',
						'description' => 'Set the issue date and the forecast valid-until period.',
						'layout' => 'grid',
						'fields' => ['issue_date', 'forecast_valid_until_date', 'forecast_valid_until_time'],
					],
					[
						'id' => 'english',
						'title' => 'English weather description',
						'description' => 'Capture the separate English narratives for today and tomorrow.',
						'layout' => 'grid',
						'fields' => ['today_date', 'today_description_english', 'tomorrow_date', 'tomorrow_description_english'],
					],
					[
						'id' => 'sesotho',
						'title' => 'Sesotho weather description',
						'description' => 'Capture the separate Sesotho narratives for kajeno and hosane.',
						'layout' => 'grid',
						'fields' => ['kajeno_date', 'kajeno_description_sesotho', 'hosane_date', 'hosane_description_sesotho'],
					],
					[
						'id' => 'temperatures',
						'title' => 'Expected temperatures by area',
						'description' => 'Review and enter the forecast temperatures for each station row.',
						'layout' => 'stack',
						'fields' => ['temperature_table'],
					],
				],
			],
			'four_day' => [
				'type' => 'four_day',
				'category' => 'weather',
				'label' => 'Four Day Weather Outlook',
				'subtitle' => 'Complete the required fields to generate the four day weather outlook.',
				'modalWidth' => 720,
				'template' => '722B_Four-Day Outlook.docx',
				'placeholders' => [
					'four_day_period_display',
				],
				'fields' => [
					[
						'name' => 'issue_date',
						'label' => 'Issue date',
						'type' => 'date',
						'required' => true,
						'default' => $today,
						'readOnly' => true,
						'helperText' => 'Auto-filled with today\'s issue date.',
					],
					[
						'name' => 'four_day_period_start',
						'label' => 'Outlook period start',
						'type' => 'date',
						'required' => true,
						'default' => $today,
						'helperText' => 'Choose the first day of the four day outlook.',
					],
					[
						'name' => 'four_day_period_end',
						'label' => 'Outlook period end',
						'type' => 'date',
						'required' => true,
						'default' => $fourDayEnd,
						'readOnly' => true,
						'helperText' => 'Calculated automatically as the fourth consecutive outlook day.',
					],
					[
						'name' => 'daily_entries',
						'label' => 'Four day forecast entries',
						'type' => 'dailyentries',
						'required' => true,
						'exactEntries' => 4,
						'lockRows' => true,
						'lockDates' => true,
						'helperText' => 'Complete the forecast and wind description for each of the four consecutive days.',
						'emptyText' => 'Four daily forecast entries are required.',
						'default' => $fourDayEntries,
					],
				],
				'sections' => [
					[
						'id' => 'identity',
						'title' => 'Document identity',
						'description' => 'Set the issue date and the first day of the four day outlook period.',
						'layout' => 'grid',
						'fields' => ['issue_date', 'four_day_period_start', 'four_day_period_end'],
					],
					[
						'id' => 'daily',
						'title' => 'Four day outlook',
						'description' => 'Provide the daily weather narrative and wind conditions shown in the outlook.',
						'layout' => 'stack',
						'fields' => ['daily_entries'],
					],
				],
			],
			'weekly' => [
				'type' => 'weekly',
				'category' => 'weather',
				'label' => 'Weekly Weather Forecast',
				'subtitle' => 'Complete the required fields to generate the weekly weather forecast.',
				'modalWidth' => 720,
				'template' => '723_Weekly Forecast.docx',
				'placeholders' => [
					'weekly_period_display',
					'weekly_summary',
				],
				'fields' => [
					[
						'name' => 'issue_date',
						'label' => 'Issue date',
						'type' => 'date',
						'required' => true,
						'default' => $today,
						'readOnly' => true,
						'helperText' => 'Auto-filled with today\'s issue date.',
					],
					[
						'name' => 'weekly_period_start',
						'label' => 'Weekly period start',
						'type' => 'date',
						'required' => true,
						'default' => $today,
					],
					[
						'name' => 'weekly_period_end',
						'label' => 'Weekly period end',
						'type' => 'date',
						'required' => true,
						'default' => $weekEnd,
					],
					[
						'name' => 'weekly_summary',
						'label' => 'Weekly summary',
						'type' => 'textarea',
						'required' => true,
						'rows' => 5,
						'placeholder' => 'Provide the overall weekly weather summary for the forecast period.',
						'helperText' => 'Provide the overall weekly weather summary for the forecast period.',
					],
					[
						'name' => 'daily_entries',
						'label' => 'Daily forecast entries',
						'type' => 'dailyentries',
						'required' => true,
						'emptyText' => 'No daily forecast entries added yet.',
						'default' => [[
							'date' => '',
							'daily_description' => '',
							'wind_description' => '',
						]],
					],
				],
				'sections' => [
					[
						'id' => 'identity',
						'title' => 'Document identity',
						'description' => 'Set the issue date and weekly forecast period.',
						'layout' => 'grid',
						'fields' => ['issue_date', 'weekly_period_start', 'weekly_period_end'],
					],
					[
						'id' => 'summary',
						'title' => 'Weekly summary',
						'description' => 'Capture the overall weekly weather summary paragraph shown below the title.',
						'layout' => 'stack',
						'fields' => ['weekly_summary'],
					],
					[
						'id' => 'daily',
						'title' => 'Daily forecast entries',
						'description' => 'Add one structured forecast entry per day in the forecast period.',
						'layout' => 'stack',
						'fields' => ['daily_entries'],
					],
				],
			],
			'climate_seasonal' => [
				'type' => 'climate_seasonal',
				'category' => 'climate',
				'label' => 'Seasonal Climate Outlook',
				'subtitle' => 'Complete the required fields to generate a seasonal climate outlook report.',
				'modalWidth' => 720,
				'template' => 'Climate_Seasonal_Forecast.docx',
				'placeholders' => [
					'document_title',
					'issue_date',
					'review_heading',
					'rainfall_outlook',
					'temperature_outlook',
					'confidence_level',
					'drivers_block',
					'driver_description',
					'highlights_block',
					'contents_block',
				],
				'fields' => [
					[
						'name' => 'issue_date',
						'label' => 'Issue date',
						'type' => 'date',
						'required' => true,
						'default' => $today,
						'readOnly' => true,
						'helperText' => 'Auto-filled with today\'s issue date.',
					],
					[
						'name' => 'forecast_season',
						'label' => 'Forecast season',
						'type' => 'select',
						'required' => true,
						'allowCustom' => true,
						'customOptionLabel' => 'Other',
						'customPlaceholder' => 'Add a custom season label',
						'helperText' => 'Select the target three-month seasonal outlook window, or add a custom season label if needed.',
						'options' => $this->mapOptions(self::FORECAST_SEASON_OPTIONS),
					],
					[
						'name' => 'forecast_year',
						'label' => 'Forecast year',
						'type' => 'select',
						'required' => true,
						'helperText' => 'Select the forecast year or cross-year season window.',
						'options' => $forecastYearOptions,
					],
					[
						'name' => 'review_period_start',
						'label' => 'Review period start',
						'type' => 'monthyear',
						'required' => true,
						'helperText' => 'Select month and year',
						'monthOptions' => $monthOptions,
						'yearOptions' => $yearOptions,
					],
					[
						'name' => 'review_period_end',
						'label' => 'Review period end',
						'type' => 'monthyear',
						'required' => true,
						'helperText' => 'Select month and year',
						'monthOptions' => $monthOptions,
						'yearOptions' => $yearOptions,
					],
					[
						'name' => 'rainfall_outlook',
						'label' => 'Rainfall outlook',
						'type' => 'select',
						'required' => true,
						'allowCustom' => true,
						'customOptionLabel' => 'Add custom value',
						'customPlaceholder' => 'Add a custom rainfall outlook',
						'helperText' => 'Choose the rainfall category expected for the forecast season, or add a custom rainfall outlook if needed.',
						'options' => $this->mapOptions(self::OUTLOOK_OPTIONS),
					],
					[
						'name' => 'temperature_outlook',
						'label' => 'Temperature outlook',
						'type' => 'select',
						'required' => true,
						'allowCustom' => true,
						'customOptionLabel' => 'Add custom value',
						'customPlaceholder' => 'Add a custom temperature outlook',
						'helperText' => 'Choose the temperature category expected for the forecast season, or add a custom temperature outlook if needed.',
						'options' => $this->mapOptions(self::TEMPERATURE_OUTLOOK_OPTIONS),
					],
					[
						'name' => 'confidence_level',
						'label' => 'Confidence level',
						'type' => 'segmented',
						'required' => true,
						'helperText' => 'Select the forecast confidence level.',
						'options' => $this->mapOptions(self::CONFIDENCE_LEVEL_OPTIONS),
					],
					[
						'name' => 'climate_drivers',
						'label' => 'Climate drivers',
						'type' => 'driverlist',
						'required' => false,
						'placeholder' => 'Add a custom climate driver',
						'helperText' => 'Choose climate drivers from the list or add your own custom driver. Descriptions are optional.',
						'driverDescriptionPlaceholder' => 'Add description for selected climate driver',
						'options' => $this->mapOptions(self::CLIMATE_DRIVER_OPTIONS),
					],
					[
						'name' => 'highlights',
						'label' => 'Highlights',
						'type' => 'taglist',
						'required' => false,
						'placeholder' => 'Add a custom highlight',
						'emptyText' => 'No highlights added yet.',
						'helperText' => 'Optional. Choose predefined highlights or add your own custom highlight only if the template section needs it.',
						'options' => $this->mapOptions(self::CLIMATE_HIGHLIGHT_OPTIONS),
					],
				],
				'sections' => [
					[
						'id' => 'identity',
						'title' => 'Document identity',
						'description' => 'Define the issue date, forecast season, and seasonal year for this outlook.',
						'layout' => 'grid',
						'fields' => ['issue_date', 'forecast_season', 'forecast_year'],
					],
					[
						'id' => 'review',
						'title' => 'Review period',
						'description' => 'Capture the season being reviewed in the report heading.',
						'layout' => 'grid',
						'fields' => ['review_period_start', 'review_period_end'],
					],
					[
						'id' => 'summary',
						'title' => 'Climate outlook summary',
						'description' => 'Summarize the rainfall, temperature, and confidence outlook for the target season.',
						'layout' => 'grid',
						'fields' => ['rainfall_outlook', 'temperature_outlook', 'confidence_level'],
					],
					[
						'id' => 'drivers',
						'title' => 'Climate drivers',
						'description' => 'Optionally capture the climate drivers only where the fixed template already provides a dedicated insertion area.',
						'layout' => 'stack',
						'fields' => ['climate_drivers'],
					],
					[
						'id' => 'highlights',
						'title' => 'Highlights',
						'description' => 'Optionally capture highlights only where the fixed template already provides a dedicated insertion area.',
						'layout' => 'stack',
						'fields' => ['highlights'],
					],
				],
			],
			'climate_ncof_report' => [
				'type' => 'climate_ncof_report',
				'category' => 'climate',
				'label' => 'NCOF Report',
				'subtitle' => 'Complete the required fields to generate an initial National Climate Outlook Forum report.',
				'modalWidth' => 720,
				'template' => 'Climate_Seasonal_NCOF_Report.docx',
				'placeholders' => [
					'document_title',
					'issue_date',
					'forecast_period',
					'introduction',
					'expected_conditions_summary',
					'recent_climate_review',
					'sector_impacts_block',
					'advisory_actions',
					'prepared_by',
					'reviewed_by',
					'approved_by',
				],
				'fields' => [
					[
						'name' => 'issue_date',
						'label' => 'Issue date',
						'type' => 'date',
						'required' => true,
						'default' => $today,
						'readOnly' => true,
						'helperText' => 'Auto-filled with today\'s issue date.',
					],
					[
						'name' => 'forecast_period',
						'label' => 'Forecast period',
						'type' => 'select',
						'required' => true,
						'allowCustom' => true,
						'customOptionLabel' => 'Other forecast period',
						'customPlaceholder' => 'Enter the period exactly as it should appear',
						'helperText' => 'Select a standard seasonal period or enter a custom reporting period.',
						'options' => $forecastPeriodOptions,
					],
					[
						'name' => 'introduction',
						'label' => 'Introduction',
						'type' => 'textarea',
						'required' => true,
						'rows' => 5,
						'placeholder' => 'Introduce the purpose, scope, and context of this NCOF report.',
					],
					[
						'name' => 'expected_conditions_summary',
						'label' => 'Overview / Summary of Expected Conditions',
						'type' => 'textarea',
						'required' => true,
						'rows' => 5,
						'placeholder' => 'Summarise the expected seasonal climate conditions for the forecast period.',
					],
					[
						'name' => 'recent_climate_review',
						'label' => 'Review of Recent Climate Conditions',
						'type' => 'textarea',
						'required' => true,
						'rows' => 5,
						'placeholder' => 'Describe recent observed climate conditions and anomalies relevant to the outlook.',
					],
					[
						'name' => 'sector_impacts',
						'label' => 'Sector impacts',
						'type' => 'driverlist',
						'required' => false,
						'placeholder' => 'Add a sector name',
						'emptyText' => 'No sector impacts added yet.',
						'helperText' => 'Add each sector and its narrative impact. You can add or remove sectors as needed.',
						'driverDescriptionLabel' => 'Impact description',
						'driverDescriptionPlaceholder' => 'Describe the likely implications for this sector',
						'options' => $this->mapOptions(self::NCOF_SECTOR_OPTIONS),
					],
					[
						'name' => 'advisory_actions',
						'label' => 'Advisory / Recommended Actions',
						'type' => 'textarea',
						'required' => true,
						'rows' => 5,
						'placeholder' => 'Provide recommended preparedness, response, or planning actions for stakeholders.',
					],
					[
						'name' => 'prepared_by',
						'label' => 'Prepared by',
						'type' => 'text',
						'required' => true,
						'placeholder' => 'Name / office',
					],
					[
						'name' => 'reviewed_by',
						'label' => 'Reviewed by',
						'type' => 'text',
						'required' => true,
						'placeholder' => 'Name / office',
					],
					[
						'name' => 'approved_by',
						'label' => 'Approved by',
						'type' => 'text',
						'required' => true,
						'placeholder' => 'Name / office',
					],
				],
				'sections' => [
					[
						'id' => 'identity',
						'title' => 'Document identity',
						'description' => 'Set the issue date and forecast period for this NCOF report.',
						'layout' => 'grid',
						'fields' => ['issue_date', 'forecast_period'],
					],
					[
						'id' => 'narrative',
						'title' => 'Narrative sections',
						'description' => 'Provide the main narrative content for the report introduction and climate summary sections.',
						'layout' => 'stack',
						'fields' => ['introduction', 'expected_conditions_summary', 'recent_climate_review'],
					],
					[
						'id' => 'sector-impacts',
						'title' => 'Sector impacts',
						'description' => 'Add the sectors that should appear in the implications section and describe their expected impacts.',
						'layout' => 'stack',
						'fields' => ['sector_impacts'],
					],
					[
						'id' => 'advisory',
						'title' => 'Advisory / recommended actions',
						'description' => 'Capture the recommended actions that should appear in the advisory section.',
						'layout' => 'stack',
						'fields' => ['advisory_actions'],
					],
					[
						'id' => 'approval',
						'title' => 'Approval section',
						'description' => 'Provide the names or offices for the approval lines shown at the end of the report.',
						'layout' => 'grid',
						'fields' => ['prepared_by', 'reviewed_by', 'approved_by'],
					],
				],
			],
		];

		return $definitions[$type] ?? null;
	}

	/**
	 * @param array<string, mixed> $definition
	 * @param array<string, mixed> $submittedValues
	 * @return array{values: array<string, mixed>, errors: array<string, string>}
	 */
	public function normalizeAndValidate(array $definition, array $submittedValues): array {
		$today = (new \DateTimeImmutable('today'))->format('Y-m-d');
		$values = [];
		$errors = [];

		foreach ($definition['fields'] as $field) {
			$name = $field['name'];
			$type = $field['type'];
			$required = (bool)($field['required'] ?? false);
			$readOnly = (bool)($field['readOnly'] ?? false);
			$default = $field['default'] ?? null;

			$rawValue = $submittedValues[$name] ?? $default ?? (($type === 'multiselect' || $type === 'taglist' || $type === 'driverlist' || $type === 'temperaturetable' || $type === 'twodaytemperaturetable' || $type === 'dailyentries') ? [] : '');
			if ($readOnly && $name === 'issue_date') {
				$rawValue = $today;
			}

			if ($type === 'driverlist') {
				$normalizedDrivers = $this->normalizeDriverValues($rawValue);
				if ($required && $normalizedDrivers === []) {
					$errors[$name] = sprintf('%s is required.', $field['label']);
				}

				$values[$name] = $normalizedDrivers;
				continue;
			}

			if ($type === 'temperaturetable') {
				$normalizedRows = $this->normalizeTemperatureRows($rawValue);
				if ($required && $normalizedRows === []) {
					$errors[$name] = sprintf('%s is required.', $field['label']);
				}

				foreach ($normalizedRows as $index => $row) {
					if (!is_numeric($row['temperature'])) {
						$errors[$name] = sprintf('Row %d temperature must be numeric.', $index + 1);
						break;
					}

					$temperature = (float)$row['temperature'];
					if ($temperature < -10 || $temperature > 50) {
						$errors[$name] = sprintf('Row %d temperature must be between -10 and 50°C.', $index + 1);
						break;
					}
				}

				$values[$name] = $normalizedRows;
				continue;
			}

			if ($type === 'twodaytemperaturetable') {
				$normalizedRows = $this->normalizeTwoDayTemperatureRows($rawValue);
				$completedRows = array_values(array_filter(
					$normalizedRows,
					static fn (array $row): bool => !self::isTwoDayRowCompletelyBlank($row),
				));
				if ($required && $completedRows === []) {
					$errors[$name] = sprintf('%s is required.', $field['label']);
				}

				foreach ($completedRows as $index => $row) {
					if ($row['area'] === '') {
						$errors[$name] = sprintf('Row %d area is required.', $index + 1);
						break;
					}

					foreach (['max_this_afternoon', 'min_tonight', 'max_tomorrow', 'min_tomorrow'] as $column) {
						if (!is_numeric((string)$row[$column])) {
							$errors[$name] = sprintf('Row %d contains an invalid temperature value.', $index + 1);
							break 2;
						}
					}
				}

				$values[$name] = $completedRows;
				continue;
			}

			if ($type === 'dailyentries') {
				$normalizedEntries = $this->normalizeWeeklyDailyEntries($rawValue);
				$completedEntries = array_values(array_filter(
					$normalizedEntries,
					static fn (array $entry): bool => !self::isWeeklyDailyEntryBlank($entry),
				));
				if ($required && $completedEntries === []) {
					$errors[$name] = sprintf('%s is required.', $field['label']);
				}

				foreach ($completedEntries as $index => $entry) {
					if ($entry['date'] === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $entry['date']) !== 1) {
						$errors[$name] = sprintf('Entry %d must include a valid date.', $index + 1);
						break;
					}
					if ($entry['daily_description'] === '' || $entry['wind_description'] === '') {
						$errors[$name] = sprintf('Entry %d must include both forecast and wind descriptions.', $index + 1);
						break;
					}
				}

				$exactEntries = (int)($field['exactEntries'] ?? 0);
				if ($exactEntries > 0 && count($completedEntries) !== $exactEntries) {
					$errors[$name] = sprintf('%s must contain exactly %d complete entries.', $field['label'], $exactEntries);
				}

				$values[$name] = $completedEntries;
				continue;
			}

			if ($type === 'multiselect' || $type === 'taglist') {
				$normalized = $this->normalizeListValues($rawValue);

				if ($required && $normalized === []) {
					$errors[$name] = sprintf('%s is required.', $field['label']);
				}

				if ($type === 'multiselect') {
					$allowedOptions = array_column($field['options'] ?? [], 'value');
					$invalidOptions = array_values(array_diff($normalized, $allowedOptions));
					if ($invalidOptions !== []) {
						$errors[$name] = sprintf('One or more %s values are invalid.', strtolower((string)$field['label']));
					}
				}

				$values[$name] = $normalized;
				continue;
			}

			$normalized = trim((string)$rawValue);
			if ($type === 'time') {
				$normalized = $this->normalizeTimeValue($normalized);
			}
			if ($required && $normalized === '') {
				$errors[$name] = sprintf('%s is required.', $field['label']);
			}

			if ($normalized !== '' && $type === 'date' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) !== 1) {
				$errors[$name] = sprintf('%s must be a valid date.', $field['label']);
			}

			if ($normalized !== '' && $type === 'time' && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $normalized) !== 1) {
				$errors[$name] = sprintf('%s must be a valid time in HH:mm:ss format.', $field['label']);
			}

			if ($normalized !== '' && ($type === 'month' || $type === 'monthyear') && preg_match('/^\d{4}-\d{2}$/', $normalized) !== 1) {
				$errors[$name] = sprintf('%s must be a valid month.', $field['label']);
			}

			if (($type === 'select' || $type === 'segmented') && $normalized !== '') {
				$allowedOptions = array_column($field['options'] ?? [], 'value');
				$allowCustom = (bool)($field['allowCustom'] ?? false);
				if (!$allowCustom && !in_array($normalized, $allowedOptions, true)) {
					$errors[$name] = sprintf('%s must be one of the allowed options.', $field['label']);
				}
			}

			$values[$name] = $normalized;
		}

		$periodStart = $values['period_start'] ?? null;
		$periodEnd = $values['period_end'] ?? null;
		if (is_string($periodStart) && is_string($periodEnd) && $periodStart !== '' && $periodEnd !== '' && $periodStart > $periodEnd) {
			$errors['period_end'] = 'Period end must be on or after period start.';
		}

		$reviewStart = $values['review_period_start'] ?? null;
		$reviewEnd = $values['review_period_end'] ?? null;
		if (is_string($reviewStart) && is_string($reviewEnd) && $reviewStart !== '' && $reviewEnd !== '' && $reviewStart > $reviewEnd) {
			$errors['review_period_end'] = 'Review period end must be on or after review period start.';
		}

		$weeklyStart = $values['weekly_period_start'] ?? null;
		$weeklyEnd = $values['weekly_period_end'] ?? null;
		if (is_string($weeklyStart) && is_string($weeklyEnd) && $weeklyStart !== '' && $weeklyEnd !== '' && $weeklyStart > $weeklyEnd) {
			$errors['weekly_period_end'] = 'Weekly period end must be on or after weekly period start.';
		}

		$fourDayStart = $values['four_day_period_start'] ?? null;
		$fourDayEnd = $values['four_day_period_end'] ?? null;
		if (is_string($fourDayStart) && is_string($fourDayEnd) && $fourDayStart !== '' && $fourDayEnd !== '') {
			try {
				$startDate = new \DateTimeImmutable($fourDayStart);
				$expectedEnd = $startDate->modify('+3 days')->format('Y-m-d');
				if ($fourDayEnd !== $expectedEnd) {
					$errors['four_day_period_end'] = 'Four day outlook end must be exactly three days after the start.';
				}

				$entries = is_array($values['daily_entries'] ?? null) ? $values['daily_entries'] : [];
				foreach (array_values($entries) as $index => $entry) {
					$expectedDate = $startDate->modify(sprintf('+%d days', $index))->format('Y-m-d');
					if (($entry['date'] ?? '') !== $expectedDate) {
						$errors['daily_entries'] = 'Four day forecast entries must match the four consecutive outlook dates.';
						break;
					}
				}
			} catch (\Throwable) {
				$errors['four_day_period_end'] = 'Four day outlook period must contain valid dates.';
			}
		}

		$todayDate = $values['today_date'] ?? null;
		$tomorrowDate = $values['tomorrow_date'] ?? null;
		if (is_string($todayDate) && is_string($tomorrowDate) && $todayDate !== '' && $tomorrowDate !== '' && $todayDate > $tomorrowDate) {
			$errors['tomorrow_date'] = 'Tomorrow date must be on or after today date.';
		}

		if (($definition['type'] ?? '') === 'climate_seasonal') {
			$documentTitle = $this->buildClimateDocumentTitle(
				(string)($values['forecast_season'] ?? ''),
				(string)($values['forecast_year'] ?? ''),
			);
			$reviewHeading = $this->buildClimateReviewHeading(
				(string)($values['review_period_start'] ?? ''),
				(string)($values['review_period_end'] ?? ''),
			);
			$values['document_title'] = $documentTitle;
			$values['review_heading'] = $reviewHeading;
		}

		if (($definition['type'] ?? '') === 'climate_ncof_report') {
			$values['document_title'] = 'National Climate Outlook Forum (NCOF) Report';
		}

		if (($definition['type'] ?? '') === 'morning') {
			$values['document_title'] = 'Morning Weather Forecast';
			$forecastValidTime = $this->buildMorningForecastValidTime(
				(string)($values['forecast_valid_time_mode'] ?? ''),
				(string)($values['forecast_valid_time_start'] ?? ''),
				(string)($values['forecast_valid_time_end'] ?? ''),
			);
			$values['forecast_valid_time'] = $forecastValidTime;
			if ($forecastValidTime === '') {
				$errors['forecast_valid_time_mode'] = 'Forecast valid time range is required.';
			}
		}

		if (($definition['type'] ?? '') === 'two_day') {
			$values['document_title'] = 'Two Day Forecast';
		}

		if (($definition['type'] ?? '') === 'four_day') {
			$values['document_title'] = 'FOUR-DAY OUTLOOK';
			$values['four_day_period_display'] = $this->buildWeeklyPeriodDisplay(
				(string)($values['four_day_period_start'] ?? ''),
				(string)($values['four_day_period_end'] ?? ''),
			);
			$values['daily_entries'] = $this->buildWeeklyEntryDisplays(
				is_array($values['daily_entries'] ?? null) ? $values['daily_entries'] : [],
			);
		}

		if (($definition['type'] ?? '') === 'weekly') {
			$values['document_title'] = 'WEEKLY WEATHER';
			$values['weekly_period_display'] = $this->buildWeeklyPeriodDisplay(
				(string)($values['weekly_period_start'] ?? ''),
				(string)($values['weekly_period_end'] ?? ''),
			);
			$values['daily_entries'] = $this->buildWeeklyEntryDisplays(
				is_array($values['daily_entries'] ?? null) ? $values['daily_entries'] : [],
			);
		}

		return [
			'values' => $values,
			'errors' => $errors,
		];
	}

	/**
	 * @param array<string, mixed> $values
	 * @return array<string, string>
	 */
	public function buildPlaceholders(string $type, array $values): array {
		if ($type !== 'agromet_dekadal') {
			if ($type === 'morning') {
				return [
					'issue_date' => $this->formatDate((string)($values['issue_date'] ?? '')),
					'forecast_valid_time' => (string)($values['forecast_valid_time'] ?? ''),
					'weather_description_en' => (string)($values['weather_description_en'] ?? ''),
					'weather_description_st' => (string)($values['weather_description_st'] ?? ''),
				];
			}

			if ($type === 'two_day') {
				return [
					'issue_date' => $this->formatOrdinalDate((string)($values['issue_date'] ?? '')),
					'forecast_valid_until_date' => $this->formatOrdinalDate((string)($values['forecast_valid_until_date'] ?? '')),
					'forecast_valid_until_time' => (string)($values['forecast_valid_until_time'] ?? ''),
					'today_date' => $this->formatSlashDate((string)($values['today_date'] ?? '')),
					'today_description_english' => (string)($values['today_description_english'] ?? ''),
					'tomorrow_date' => $this->formatSlashDate((string)($values['tomorrow_date'] ?? '')),
					'tomorrow_description_english' => (string)($values['tomorrow_description_english'] ?? ''),
					'kajeno_date' => $this->formatSlashDate((string)($values['kajeno_date'] ?? '')),
					'kajeno_description_sesotho' => (string)($values['kajeno_description_sesotho'] ?? ''),
					'hosane_date' => $this->formatSlashDate((string)($values['hosane_date'] ?? '')),
					'hosane_description_sesotho' => (string)($values['hosane_description_sesotho'] ?? ''),
				];
			}

			if ($type === 'four_day') {
				return [
					'four_day_period_display' => (string)($values['four_day_period_display'] ?? ''),
				];
			}

			if ($type === 'weekly') {
				return [
					'weekly_period_display' => (string)($values['weekly_period_display'] ?? ''),
					'weekly_summary' => (string)($values['weekly_summary'] ?? ''),
				];
			}

			if ($type === 'climate_seasonal') {
				/** @var array<int, array{name: string, description: string}> $drivers */
				$drivers = is_array($values['climate_drivers'] ?? null) ? $values['climate_drivers'] : [];

				return [
					'document_title' => (string)($values['document_title'] ?? ''),
					'issue_date' => $this->formatDate((string)($values['issue_date'] ?? '')),
					'review_heading' => (string)($values['review_heading'] ?? ''),
					'rainfall_outlook' => (string)($values['rainfall_outlook'] ?? ''),
					'temperature_outlook' => (string)($values['temperature_outlook'] ?? ''),
					'confidence_level' => (string)($values['confidence_level'] ?? ''),
					'drivers_block' => $this->firstClimateDriverName($drivers),
					'driver_description' => $this->firstClimateDriverDescription($drivers),
					'highlights_block' => $this->formatPlainListBlock($values['highlights'] ?? []),
					'contents_block' => '',
				];
			}

			if ($type === 'climate_ncof_report') {
				/** @var array<int, array{name: string, description: string}> $sectorImpacts */
				$sectorImpacts = is_array($values['sector_impacts'] ?? null) ? $values['sector_impacts'] : [];

				return [
					'document_title' => (string)($values['document_title'] ?? 'National Climate Outlook Forum (NCOF) Report'),
					'issue_date' => $this->formatDate((string)($values['issue_date'] ?? '')),
					'forecast_period' => (string)($values['forecast_period'] ?? ''),
					'introduction' => (string)($values['introduction'] ?? ''),
					'expected_conditions_summary' => (string)($values['expected_conditions_summary'] ?? ''),
					'recent_climate_review' => (string)($values['recent_climate_review'] ?? ''),
					'sector_impacts_block' => $this->formatClimateDriversBlock($sectorImpacts),
					'advisory_actions' => (string)($values['advisory_actions'] ?? ''),
					'prepared_by' => (string)($values['prepared_by'] ?? ''),
					'reviewed_by' => (string)($values['reviewed_by'] ?? ''),
					'approved_by' => (string)($values['approved_by'] ?? ''),
				];
			}

			return [];
		}

		$weatherTypes = $values['weather_type'] ?? [];
		$weatherTypeText = is_array($weatherTypes) ? implode(', ', $weatherTypes) : (string)$weatherTypes;

		return [
			'period_start' => $this->formatDate((string)($values['period_start'] ?? '')),
			'period_end' => $this->formatDate((string)($values['period_end'] ?? '')),
			'season' => (string)($values['season'] ?? ''),
			'issue_date' => $this->formatDate((string)($values['issue_date'] ?? '')),
			'bulletin_number' => (string)($values['bulletin_number'] ?? ''),
			'review_period_start' => $this->formatDate((string)($values['review_period_start'] ?? '')),
			'review_period_end' => $this->formatDate((string)($values['review_period_end'] ?? '')),
			'highlights_block' => $this->formatHighlightsBlock($values['highlights'] ?? []),
			'contents_block' => $this->formatContentsBlock($values['contents'] ?? []),
			'weather_type' => $weatherTypeText,
			'weather_description' => (string)($values['weather_description'] ?? ''),
		];
	}

	/**
	 * @param array<string, mixed> $values
	 */
	public function buildFilenameBase(string $type, array $values): string {
		if ($type === 'morning') {
			$issueDate = $this->slugify((string)($values['issue_date'] ?? ''));
			return trim(sprintf('Morning_Forecast_%s', $issueDate !== '' ? $issueDate : 'undated'), '_');
		}

		if ($type === 'two_day') {
			$issueDate = $this->slugify((string)($values['issue_date'] ?? ''));
			return trim(sprintf('Two_Day_Forecast_%s', $issueDate !== '' ? $issueDate : 'undated'), '_');
		}

		if ($type === 'four_day') {
			$periodStart = $this->slugify((string)($values['four_day_period_start'] ?? ''));
			$periodEnd = $this->slugify((string)($values['four_day_period_end'] ?? ''));
			return trim(sprintf('Four_Day_Outlook_%s_%s', $periodStart !== '' ? $periodStart : 'start', $periodEnd !== '' ? $periodEnd : 'end'), '_');
		}

		if ($type === 'weekly') {
			$periodStart = $this->slugify((string)($values['weekly_period_start'] ?? ''));
			$periodEnd = $this->slugify((string)($values['weekly_period_end'] ?? ''));
			return trim(sprintf('Weekly_Forecast_%s_%s', $periodStart !== '' ? $periodStart : 'start', $periodEnd !== '' ? $periodEnd : 'end'), '_');
		}

		if ($type === 'climate_seasonal') {
			$season = $this->slugify((string)($values['forecast_season'] ?? ''));
			$year = $this->slugify((string)($values['forecast_year'] ?? ''));
			return trim(sprintf('Climate_Seasonal_Outlook_%s_%s', $season !== '' ? $season : 'season', $year !== '' ? $year : 'year'), '_');
		}

		if ($type === 'climate_ncof_report') {
			$issueYear = '';
			try {
				$issueYear = (new \DateTimeImmutable((string)($values['issue_date'] ?? '')))->format('Y');
			} catch (\Throwable) {
				$issueYear = (new \DateTimeImmutable('now'))->format('Y');
			}

			$period = $this->slugify((string)($values['forecast_period'] ?? ''));
			return trim(sprintf('Climate_NCOF_Report_%s_%s', $issueYear, $period !== '' ? $period : 'forecast_period'), '_');
		}

		if ($type !== 'agromet_dekadal') {
			return 'CSIS_Product_' . (new \DateTimeImmutable('now'))->format('Y-m-d_His');
		}

		$issueDate = $this->slugify((string)($values['issue_date'] ?? ''));
		$bulletinNumber = $this->slugify((string)($values['bulletin_number'] ?? ''));

		return trim(sprintf('Agromet_Dekadal_%s_%s', $issueDate !== '' ? $issueDate : 'undated', $bulletinNumber !== '' ? $bulletinNumber : 'bulletin'), '_');
	}

	private function formatDate(string $value): string {
		if ($value === '') {
			return '';
		}

		try {
			return (new \DateTimeImmutable($value))->format('j F Y');
		} catch (\Throwable) {
			return $value;
		}
	}

	private function formatSlashDate(string $value): string {
		if ($value === '') {
			return '';
		}

		try {
			return (new \DateTimeImmutable($value))->format('d/m/Y');
		} catch (\Throwable) {
			return $value;
		}
	}

	private function formatOrdinalDate(string $value): string {
		if ($value === '') {
			return '';
		}

		try {
			$date = new \DateTimeImmutable($value);
			$day = (int)$date->format('j');
			$suffix = match (true) {
				$day % 100 >= 11 && $day % 100 <= 13 => 'th',
				$day % 10 === 1 => 'st',
				$day % 10 === 2 => 'nd',
				$day % 10 === 3 => 'rd',
				default => 'th',
			};
			return sprintf('%d%s %s', $day, $suffix, $date->format('F Y'));
		} catch (\Throwable) {
			return $value;
		}
	}

	private function formatOrdinalDay(string $value): string {
		if ($value === '') {
			return '';
		}

		try {
			$date = new \DateTimeImmutable($value);
			$day = (int)$date->format('j');
			$paddedDay = str_pad((string)$day, 2, '0', STR_PAD_LEFT);
			$suffix = match (true) {
				$day % 100 >= 11 && $day % 100 <= 13 => 'th',
				$day % 10 === 1 => 'st',
				$day % 10 === 2 => 'nd',
				$day % 10 === 3 => 'rd',
				default => 'th',
			};
			return sprintf('%s%s', $paddedDay, $suffix);
		} catch (\Throwable) {
			return $value;
		}
	}

	private function buildWeeklyPeriodDisplay(string $startValue, string $endValue): string {
		if ($startValue === '' || $endValue === '') {
			return '';
		}

		try {
			$start = new \DateTimeImmutable($startValue);
			$end = new \DateTimeImmutable($endValue);
			$startDisplay = $this->formatOrdinalDay($startValue);
			$endDisplay = $this->formatOrdinalDay($endValue);

			if ($start->format('F Y') === $end->format('F Y')) {
				return sprintf('%s – %s %s', $startDisplay, $endDisplay, $end->format('F Y'));
			}

			if ($start->format('Y') === $end->format('Y')) {
				return sprintf('%s %s – %s %s', $startDisplay, $start->format('F'), $endDisplay, $end->format('F Y'));
			}

			return sprintf('%s %s – %s %s', $startDisplay, $start->format('F Y'), $endDisplay, $end->format('F Y'));
		} catch (\Throwable) {
			return '';
		}
	}

	/**
	 * @param mixed $rawValue
	 * @return array<int, string>
	 */
	private function normalizeListValues(mixed $rawValue): array {
		if (!is_array($rawValue)) {
			return [];
		}

		$normalized = [];
		$seen = [];

		foreach ($rawValue as $value) {
			$item = preg_replace('/\s+/', ' ', trim((string)$value)) ?? '';
			if ($item === '') {
				continue;
			}

			$key = strtolower($item);
			if (isset($seen[$key])) {
				continue;
			}

			$seen[$key] = true;
			$normalized[] = $item;
		}

		return $normalized;
	}

	/**
	 * @param string|array<int, string> $highlights
	 */
	private function formatHighlightsBlock(string|array $highlights): string {
		if (!is_array($highlights) || $highlights === []) {
			return '';
		}

		$blocks = array_map(static function (string $highlight): string {
			$normalizedHighlight = trim($highlight);
			$uppercased = function_exists('mb_strtoupper')
				? mb_strtoupper($normalizedHighlight, 'UTF-8')
				: strtoupper($normalizedHighlight);
			$words = preg_split('/\s+/', $uppercased) ?: [];
			$words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));
			if ($words === []) {
				return '';
			}

			$firstLine = '• ' . array_shift($words);
			$continuationLines = array_map(static fn (string $word): string => '  ' . $word, $words);

			return implode("\n", array_merge([$firstLine], $continuationLines));
		}, $highlights);

		return implode("\n\n", array_filter($blocks, static fn (string $block): bool => $block !== ''));
	}

	/**
	 * @param string|array<int, string> $contents
	 */
	private function formatContentsBlock(string|array $contents): string {
		if (!is_array($contents) || $contents === []) {
			return '';
		}

		$blocks = [];
		foreach (array_values($contents) as $index => $item) {
			$normalizedItem = trim($item);
			if ($normalizedItem === '') {
				continue;
			}

			$words = preg_split('/\s+/', $normalizedItem) ?: [];
			$words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));
			if ($words === []) {
				continue;
			}

			$number = $index + 1;
			$firstLine = sprintf('%d. %s', $number, array_shift($words));
			$continuationLines = array_map(static fn (string $word): string => '   ' . $word, $words);
			$blocks[] = implode("\n", array_merge([$firstLine], $continuationLines));
		}

		return implode("\n\n", $blocks);
	}

	/**
	 * @param mixed $rawValue
	 * @return array<int, array{name: string, description: string}>
	 */
	private function normalizeDriverValues(mixed $rawValue): array {
		if (!is_array($rawValue)) {
			return [];
		}

		$normalized = [];
		$seen = [];

		foreach ($rawValue as $driver) {
			if (!is_array($driver)) {
				continue;
			}

			$name = preg_replace('/\s+/', ' ', trim((string)($driver['name'] ?? ''))) ?? '';
			$description = str_replace(["\r\n", "\r"], "\n", trim((string)($driver['description'] ?? '')));
			if ($name === '') {
				continue;
			}

			$key = strtolower($name);
			if (isset($seen[$key])) {
				continue;
			}

			$seen[$key] = true;
			$normalized[] = [
				'name' => $name,
				'description' => $description,
			];
		}

		return $normalized;
	}

	/**
	 * @param mixed $rawValue
	 * @return array<int, array{area: string, temperature: int|float}>
	 */
	private function normalizeTemperatureRows(mixed $rawValue): array {
		if (!is_array($rawValue)) {
			return [];
		}

		$normalized = [];
		foreach ($rawValue as $row) {
			if (!is_array($row)) {
				continue;
			}

			$area = preg_replace('/\s+/', ' ', trim((string)($row['area'] ?? ''))) ?? '';
			$temperature = trim((string)($row['temperature'] ?? ''));
			if ($area === '' && $temperature === '') {
				continue;
			}

			if ($area === '' || $temperature === '') {
				continue;
			}

			$numericTemperature = (float)$temperature;
			$normalized[] = [
				'area' => $area,
				'temperature' => floor($numericTemperature) === $numericTemperature
					? (int)$numericTemperature
					: $numericTemperature,
			];
		}

		return $normalized;
	}

	/**
	 * @param mixed $rawValue
	 * @return array<int, array{area: string, max_this_afternoon: string, min_tonight: string, max_tomorrow: string, min_tomorrow: string}>
	 */
	private function normalizeTwoDayTemperatureRows(mixed $rawValue): array {
		if (!is_array($rawValue)) {
			return [];
		}

		$normalized = [];
		foreach ($rawValue as $row) {
			if (!is_array($row)) {
				continue;
			}

			$normalized[] = [
				'area' => preg_replace('/\s+/', ' ', trim((string)($row['area'] ?? ''))) ?? '',
				'max_this_afternoon' => trim((string)($row['max_this_afternoon'] ?? '')),
				'min_tonight' => trim((string)($row['min_tonight'] ?? '')),
				'max_tomorrow' => trim((string)($row['max_tomorrow'] ?? '')),
				'min_tomorrow' => trim((string)($row['min_tomorrow'] ?? '')),
			];
		}

		return $normalized;
	}

	/**
	 * @param mixed $rawValue
	 * @return array<int, array{date: string, daily_description: string, wind_description: string, display_label?: string}>
	 */
	private function normalizeWeeklyDailyEntries(mixed $rawValue): array {
		if (!is_array($rawValue)) {
			return [];
		}

		$normalized = [];
		foreach ($rawValue as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$normalized[] = [
				'date' => trim((string)($entry['date'] ?? '')),
				'daily_description' => trim((string)($entry['daily_description'] ?? '')),
				'wind_description' => trim((string)($entry['wind_description'] ?? '')),
			];
		}

		return $normalized;
	}

	/**
	 * @param array<int, array{date: string, daily_description: string, wind_description: string}> $entries
	 * @return array<int, array{date: string, daily_description: string, wind_description: string, display_label: string}>
	 */
	private function buildWeeklyEntryDisplays(array $entries): array {
		return array_map(function (array $entry): array {
			$date = (string)($entry['date'] ?? '');
			$displayLabel = '';
			if ($date !== '') {
				try {
					$dateObject = new \DateTimeImmutable($date);
					$displayLabel = sprintf('%s %s', $dateObject->format('l'), $this->formatOrdinalDay($date));
				} catch (\Throwable) {
					$displayLabel = $date;
				}
			}

			return [
				'date' => $date,
				'daily_description' => (string)($entry['daily_description'] ?? ''),
				'wind_description' => (string)($entry['wind_description'] ?? ''),
				'display_label' => $displayLabel,
			];
		}, $entries);
	}

	/**
	 * @param array{area: string, max_this_afternoon: string, min_tonight: string, max_tomorrow: string, min_tomorrow: string} $row
	 */
	private static function isTwoDayRowCompletelyBlank(array $row): bool {
		return $row['max_this_afternoon'] === ''
			&& $row['min_tonight'] === ''
			&& $row['max_tomorrow'] === ''
			&& $row['min_tomorrow'] === '';
	}

	/**
	 * @param array{date: string, daily_description: string, wind_description: string} $entry
	 */
	private static function isWeeklyDailyEntryBlank(array $entry): bool {
		return $entry['date'] === ''
			&& $entry['daily_description'] === ''
			&& $entry['wind_description'] === '';
	}

	private function buildMorningForecastValidTime(string $mode, string $startTime, string $endTime): string {
		$normalizedMode = trim($mode);
		if ($normalizedMode === '' || $normalizedMode === '__custom__') {
			$normalizedStart = trim($startTime);
			$normalizedEnd = trim($endTime);
			if ($normalizedStart === '' || $normalizedEnd === '') {
				return '';
			}

			return sprintf('%s – %s', $normalizedStart, $normalizedEnd);
		}

		return $normalizedMode;
	}

	private function normalizeTimeValue(string $value): string {
		$normalized = trim($value);
		if (preg_match('/^\d{2}:\d{2}$/', $normalized) === 1) {
			return $normalized . ':00';
		}

		return $normalized;
	}

	/**
	 * @param array<int, array{name: string, description: string}> $drivers
	 */
	private function formatClimateDriversBlock(array $drivers): string {
		if ($drivers === []) {
			return '';
		}

		$blocks = [];
		foreach ($drivers as $driver) {
			$blocks[] = $this->formatClimateDriverPair($driver);
		}

		return implode("\n\n", array_values(array_filter($blocks, static fn (string $block): bool => $block !== '')));
	}

	/**
	 * @param array<int, array{name: string, description: string}> $drivers
	 */
	private function firstClimateDriverName(array $drivers): string {
		foreach ($drivers as $driver) {
			$name = trim($driver['name'] ?? '');
			if ($name !== '') {
				return $name;
			}
		}

		return '';
	}

	/**
	 * @param array<int, array{name: string, description: string}> $drivers
	 */
	private function firstClimateDriverDescription(array $drivers): string {
		foreach ($drivers as $driver) {
			$name = trim($driver['name'] ?? '');
			if ($name === '') {
				continue;
			}

			return trim($driver['description'] ?? '');
		}

		return '';
	}

	/**
	 * @param array{name: string, description: string} $driver
	 */
	private function formatClimateDriverPair(array $driver): string {
		$name = trim($driver['name'] ?? '');
		if ($name === '') {
			return '';
		}

		$lines = [$name];
		$description = trim($driver['description'] ?? '');
		if ($description !== '') {
			$descriptionLines = preg_split('/\R/', $description) ?: [];
			foreach ($descriptionLines as $descriptionLine) {
				$normalizedLine = trim($descriptionLine);
				if ($normalizedLine === '') {
					continue;
				}
				$lines[] = $normalizedLine;
			}
		}

		return implode("\n", $lines);
	}

	/**
	 * @param string|array<int, string> $items
	 */
	private function formatPlainListBlock(string|array $items): string {
		if (!is_array($items) || $items === []) {
			return '';
		}

		$lines = [];
		foreach ($items as $item) {
			$normalizedItem = trim($item);
			if ($normalizedItem === '') {
				continue;
			}

			$lines[] = $normalizedItem;
		}

		return implode("\n", $lines);
	}

	/**
	 * @param string|array<int, string> $items
	 */
	private function formatUppercaseBulletBlock(string|array $items): string {
		if (!is_array($items) || $items === []) {
			return '';
		}

		$blocks = array_map(function (string $item): string {
			$normalizedItem = trim($item);
			$uppercased = function_exists('mb_strtoupper')
				? mb_strtoupper($normalizedItem, 'UTF-8')
				: strtoupper($normalizedItem);
			$words = preg_split('/\s+/', $uppercased) ?: [];
			$words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));
			if ($words === []) {
				return '';
			}

			$firstLine = '• ' . array_shift($words);
			$continuationLines = array_map(static fn (string $word): string => '  ' . $word, $words);

			return implode("\n", array_merge([$firstLine], $continuationLines));
		}, $items);

		return implode("\n\n", array_filter($blocks, static fn (string $block): bool => $block !== ''));
	}

	/**
	 * @param string|array<int, string> $items
	 */
	private function formatNumberedBlock(string|array $items): string {
		if (!is_array($items) || $items === []) {
			return '';
		}

		$blocks = [];
		foreach (array_values($items) as $index => $item) {
			$words = preg_split('/\s+/', trim($item)) ?: [];
			$words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));
			if ($words === []) {
				continue;
			}

			$firstLine = sprintf('%d. %s', $index + 1, array_shift($words));
			$continuationLines = array_map(static fn (string $word): string => '   ' . $word, $words);
			$blocks[] = implode("\n", array_merge([$firstLine], $continuationLines));
		}

		return implode("\n\n", $blocks);
	}

	private function buildClimateDocumentTitle(string $season, string $year): string {
		$normalizedSeason = trim($season);
		$normalizedYear = preg_replace('/\s*\/\s*/', ' / ', trim($year)) ?? '';
		if ($normalizedSeason === '' || $normalizedYear === '') {
			return 'Seasonal Climate Outlook';
		}

		return sprintf('Seasonal Climate Outlook %s %s', $normalizedSeason, $normalizedYear);
	}

	private function buildClimateReviewHeading(string $start, string $end): string {
		$formattedStart = $this->formatMonthLabel($start, true);
		$formattedEnd = $this->formatMonthLabel($end, true);
		if ($formattedStart === '' || $formattedEnd === '') {
			return '';
		}

		return sprintf('REVIEW OF %s TO %s', $formattedStart, $formattedEnd);
	}

	private function formatMonthLabel(string $value, bool $uppercase = false): string {
		if ($value === '') {
			return '';
		}

		try {
			$label = (new \DateTimeImmutable($value . '-01'))->format('F Y');
			if ($uppercase) {
				return function_exists('mb_strtoupper') ? mb_strtoupper($label, 'UTF-8') : strtoupper($label);
			}

			return $label;
		} catch (\Throwable) {
			return $value;
		}
	}

	/**
	 * @return array<int, array{label: string, value: string}>
	 */
	private function monthOptions(): array {
		$months = [
			'01' => 'January',
			'02' => 'February',
			'03' => 'March',
			'04' => 'April',
			'05' => 'May',
			'06' => 'June',
			'07' => 'July',
			'08' => 'August',
			'09' => 'September',
			'10' => 'October',
			'11' => 'November',
			'12' => 'December',
		];

		$options = [];
		foreach ($months as $value => $label) {
			$options[] = [
				'label' => $label,
				'value' => $value,
			];
		}

		return $options;
	}

	/**
	 * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
	 */
	private function dekadBounds(\DateTimeImmutable $date): array {
		$day = (int)$date->format('j');
		if ($day <= 10) {
			return [
				$date->setDate((int)$date->format('Y'), (int)$date->format('n'), 1),
				$date->setDate((int)$date->format('Y'), (int)$date->format('n'), 10),
			];
		}

		if ($day <= 20) {
			return [
				$date->setDate((int)$date->format('Y'), (int)$date->format('n'), 11),
				$date->setDate((int)$date->format('Y'), (int)$date->format('n'), 20),
			];
		}

		return [
			$date->setDate((int)$date->format('Y'), (int)$date->format('n'), 21),
			$date->modify('last day of this month'),
		];
	}

	/**
	 * @return array<int, array{label: string, value: string}>
	 */
	private function yearOptions(int $startYear, int $endYear): array {
		$options = [];
		for ($year = $startYear; $year <= $endYear; $year++) {
			$options[] = [
				'label' => (string)$year,
				'value' => (string)$year,
			];
		}

		return $options;
	}

	/**
	 * @return array<int, array{label: string, value: string}>
	 */
	private function forecastPeriodOptions(int $currentYear): array {
		$options = [];
		foreach ([$currentYear, $currentYear + 1] as $year) {
			foreach (self::FORECAST_SEASON_OPTIONS as $season) {
				$yearLabel = in_array($season, ['NDJ', 'DJF'], true)
					? sprintf('%d/%d', $year, $year + 1)
					: (string)$year;
				$value = sprintf('%s %s', $season, $yearLabel);
				$options[] = [
					'label' => $value,
					'value' => $value,
				];
			}
		}

		return $options;
	}

	/**
	 * @return array<int, array{label: string, value: string}>
	 */
	private function forecastYearOptions(int $startYear, int $endYear): array {
		$options = [];
		for ($year = $startYear; $year <= $endYear; $year++) {
			$options[] = [
				'label' => (string)$year,
				'value' => (string)$year,
			];
			$options[] = [
				'label' => sprintf('%d/%d', $year, $year + 1),
				'value' => sprintf('%d/%d', $year, $year + 1),
			];
		}

		return $options;
	}

	/**
	 * @param array<int, string> $options
	 * @return array<int, array{label: string, value: string}>
	 */
	private function mapOptions(array $options): array {
		return array_map(
			static fn (string $option): array => ['label' => $option, 'value' => $option],
			$options,
		);
	}

	private function slugify(string $value): string {
		$value = strtolower(trim($value));
		$value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
		return trim($value, '_');
	}
}
