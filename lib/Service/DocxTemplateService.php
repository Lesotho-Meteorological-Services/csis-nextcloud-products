<?php
declare(strict_types=1);

namespace OCA\CSISProducts\Service;

class DocxTemplateService {
	/**
	 * @param array<string, string> $placeholders
	 * @param array<string, mixed> $context
	 */
	public function render(string $templatePath, array $placeholders, array $context = []): string {
		$workingTemplatePath = $templatePath;
		$tempTemplateDir = null;
		if (in_array(($context['type'] ?? null), ['morning', 'four_day', 'weekly'], true) && !$this->isZipBasedDocx($templatePath)) {
			[$workingTemplatePath, $tempTemplateDir] = $this->convertLegacyWordTemplate($templatePath);
		}

		$outputPath = tempnam(sys_get_temp_dir(), 'csis_docx_');
		if ($outputPath === false) {
			if ($tempTemplateDir !== null) {
				$this->cleanupDirectory($tempTemplateDir);
			}
			throw new \RuntimeException('Unable to allocate a temporary DOCX file.');
		}

		$sourceArchive = new \ZipArchive();
		if ($sourceArchive->open($workingTemplatePath) !== true) {
			@unlink($outputPath);
			if ($tempTemplateDir !== null) {
				$this->cleanupDirectory($tempTemplateDir);
			}
			throw new \RuntimeException('Unable to open the DOCX template archive.');
		}

		$targetArchive = new \ZipArchive();
		if ($targetArchive->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
			$sourceArchive->close();
			@unlink($outputPath);
			throw new \RuntimeException('Unable to create the rendered DOCX archive.');
		}

		try {
			$this->copyArchiveWithReplacements($sourceArchive, $targetArchive, $placeholders, $context);
		} finally {
			$sourceArchive->close();
			$targetArchive->close();
		}

		try {
			$content = file_get_contents($outputPath);
		} finally {
			@unlink($outputPath);
			if ($tempTemplateDir !== null) {
				$this->cleanupDirectory($tempTemplateDir);
			}
		}

		if ($content === false) {
			throw new \RuntimeException('Unable to read the rendered DOCX file.');
		}

		return $content;
	}

	/**
	 * @param array<string, string> $placeholders
	 * @param array<string, mixed> $context
	 */
	private function copyArchiveWithReplacements(\ZipArchive $sourceArchive, \ZipArchive $targetArchive, array $placeholders, array $context): void {
		for ($index = 0; $index < $sourceArchive->numFiles; $index++) {
			$entryName = $sourceArchive->getNameIndex($index);
			if ($entryName === false) {
				continue;
			}

			$content = $sourceArchive->getFromIndex($index);
			if ($content === false) {
				continue;
			}

			$normalizedEntryName = str_replace('\\', '/', $entryName);
			if (preg_match('#^word/(document|header[0-9]+|footer[0-9]+)\.xml$#', $normalizedEntryName) === 1) {
				$content = $this->repairMojibake($content);
				if ($normalizedEntryName === 'word/document.xml') {
					$content = $this->prepareClimateDriverSection($content, $placeholders, $context);
				}

				foreach ($placeholders as $key => $value) {
					$content = str_replace('{{' . $key . '}}', $this->escapeForWord($value), $content);
				}

				if ($normalizedEntryName === 'word/document.xml') {
					$content = $this->prepareMorningForecastDocument($content, $placeholders, $context);
					$content = $this->prepareTwoDayForecastDocument($content, $placeholders, $context);
					$content = $this->prepareFourDayForecastDocument($content, $placeholders, $context);
					$content = $this->prepareWeeklyForecastDocument($content, $placeholders, $context);
					$content = $this->prepareNcofReportDocument($content, $placeholders, $context);
				}
			}

			$targetArchive->addFromString($normalizedEntryName, $content);
		}
	}

	private function escapeForWord(string $value): string {
		$escaped = htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');

		return str_replace(
			["\r\n", "\r", "\n"],
			['</w:t><w:br/><w:t xml:space="preserve">', '</w:t><w:br/><w:t xml:space="preserve">', '</w:t><w:br/><w:t xml:space="preserve">'],
			$escaped,
		);
	}

	private function repairMojibake(string $xml): string {
		return str_replace(
			['Ã¢â‚¬Â¦', 'Ã¢â‚¬â„¢', 'Ã¢ÂÂ°'],
			['â€¦', 'â€™', 'Â°'],
			$xml,
		);
	}

	/**
	 * When the Seasonal Climate template receives a real driver description,
	 * remove the stale default paragraph that immediately follows the
	 * dedicated description placeholder paragraph.
	 *
	 * @param array<string, string> $placeholders
	 * @param array<string, mixed> $context
	 */
	private function prepareClimateDriverSection(string $xml, array $placeholders, array $context): string {
		if (($context['type'] ?? null) !== 'climate_seasonal') {
			return $xml;
		}

		$driverDescription = trim((string)($placeholders['driver_description'] ?? ''));
		if ($driverDescription === '' || strpos($xml, '{{driver_description}}') === false) {
			return $xml;
		}

		return preg_replace(
			'#(<w:p\b[^>]*>.*?\{\{driver_description\}\}.*?</w:p>)(<w:p\b[^>]*>.*?</w:p>)#s',
			'$1',
			$xml,
			1,
		) ?? $xml;
	}

	private function prepareMorningForecastDocument(string $xml, array $placeholders, array $context): string {
		if (($context['type'] ?? null) !== 'morning') {
			return $xml;
		}

		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = false;
		if (@$dom->loadXML($xml) === false) {
			return $xml;
		}

		$xpath = new \DOMXPath($dom);
		$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

		$bodyParagraphs = [];
		foreach ($xpath->query('//w:body/w:p') as $paragraphNode) {
			if ($paragraphNode instanceof \DOMElement) {
				$bodyParagraphs[] = $paragraphNode;
			}
		}

		$dateHeadingIndex = $this->findParagraphIndex($xpath, $bodyParagraphs, 'Date of Issue:');
		if ($dateHeadingIndex !== null && isset($bodyParagraphs[$dateHeadingIndex + 1])) {
			$issueLine = sprintf(
				'%s: Morning Forecast Valid: %s',
				(string)($placeholders['issue_date'] ?? ''),
				(string)($placeholders['forecast_valid_time'] ?? '')
			);
			$this->setParagraphText($dom, $xpath, $bodyParagraphs[$dateHeadingIndex + 1], $issueLine);
		}

		$this->replaceParagraphAfterHeading(
			$dom,
			$xpath,
			$bodyParagraphs,
			'DESCRIPTION OF THE WEATHER',
			(string)($placeholders['weather_description_en'] ?? '')
		);
		$this->replaceParagraphAfterHeading(
			$dom,
			$xpath,
			$bodyParagraphs,
			'TLHALOSO EA MAEMO A LEHOLIMO',
			(string)($placeholders['weather_description_st'] ?? '')
		);

		/** @var mixed $temperatureRows */
		$temperatureRows = $context['values']['temperature_table'] ?? [];
		if (is_array($temperatureRows) && $temperatureRows !== []) {
			$this->replaceMorningTemperatureTable($dom, $xpath, $temperatureRows);
		}

		return $dom->saveXML() ?: $xml;
	}

	private function prepareTwoDayForecastDocument(string $xml, array $placeholders, array $context): string {
		if (($context['type'] ?? null) !== 'two_day') {
			return $xml;
		}

		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = false;
		if (@$dom->loadXML($xml) === false) {
			return $xml;
		}

		$xpath = new \DOMXPath($dom);
		$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

		$bodyParagraphs = [];
		foreach ($xpath->query('//w:body/w:p') as $paragraphNode) {
			if ($paragraphNode instanceof \DOMElement) {
				$bodyParagraphs[] = $paragraphNode;
			}
		}

		if (isset($bodyParagraphs[0])) {
			$issueLine = sprintf(
				'Date of Issue: %s Weather Forecast Valid Until: %s at %s',
				(string)($placeholders['issue_date'] ?? ''),
				(string)($placeholders['forecast_valid_until_date'] ?? ''),
				(string)($placeholders['forecast_valid_until_time'] ?? '')
			);
			$this->setParagraphText($dom, $xpath, $bodyParagraphs[0], $issueLine);
		}

		$this->replaceTwoDayNarrativeParagraphs(
			$dom,
			$xpath,
			$bodyParagraphs,
			'DESCRIPTION OF THE WEATHER',
			[
				[
					'label' => sprintf('Today (%s):', (string)($placeholders['today_date'] ?? '')),
					'body' => (string)($placeholders['today_description_english'] ?? ''),
				],
				[
					'label' => sprintf('Tomorrow (%s):', (string)($placeholders['tomorrow_date'] ?? '')),
					'body' => (string)($placeholders['tomorrow_description_english'] ?? ''),
				],
			],
		);
		$this->replaceTwoDayNarrativeParagraphs(
			$dom,
			$xpath,
			$bodyParagraphs,
			'TLHALOSO EA MAEMO A LEHOLIMO',
			[
				[
					'label' => sprintf('Kajeno (%s):', (string)($placeholders['kajeno_date'] ?? '')),
					'body' => (string)($placeholders['kajeno_description_sesotho'] ?? ''),
				],
				[
					'label' => sprintf('Hosane (%s):', (string)($placeholders['hosane_date'] ?? '')),
					'body' => (string)($placeholders['hosane_description_sesotho'] ?? ''),
				],
			],
		);

		/** @var mixed $temperatureRows */
		$temperatureRows = $context['values']['temperature_table'] ?? [];
		if (is_array($temperatureRows) && $temperatureRows !== []) {
			$this->replaceTwoDayTemperatureTable($dom, $xpath, $temperatureRows);
		}

		return $dom->saveXML() ?: $xml;
	}

	private function prepareFourDayForecastDocument(string $xml, array $placeholders, array $context): string {
		if (($context['type'] ?? null) !== 'four_day') {
			return $xml;
		}

		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = false;
		if (@$dom->loadXML($xml) === false) {
			return $xml;
		}

		$xpath = new \DOMXPath($dom);
		$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

		$paragraphs = [];
		foreach ($xpath->query('//w:body//w:p') as $paragraphNode) {
			if ($paragraphNode instanceof \DOMElement) {
				$paragraphs[] = $paragraphNode;
			}
		}

		$titleIndex = $this->findParagraphIndex($xpath, $paragraphs, 'FOUR-DAY OUTLOOK');
		if ($titleIndex === null) {
			return $xml;
		}

		$periodIndex = $this->findNextNonEmptyParagraphIndex($xpath, $paragraphs, $titleIndex + 1);
		if ($periodIndex !== null) {
			$this->setOrdinalParagraphText($dom, $xpath, $paragraphs[$periodIndex], (string)($placeholders['four_day_period_display'] ?? ''));
		}

		/** @var array<int, array{date?: string, display_label?: string, daily_description?: string, wind_description?: string}> $entries */
		$entries = is_array($context['values']['daily_entries'] ?? null) ? $context['values']['daily_entries'] : [];
		if ($entries === [] || $periodIndex === null) {
			return $dom->saveXML() ?: $xml;
		}

		$firstDayIndex = null;
		for ($index = $periodIndex + 1; $index < count($paragraphs); $index++) {
			$text = $this->paragraphText($xpath, $paragraphs[$index]);
			if (preg_match('/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\s+\d{1,2}(st|nd|rd|th)$/', $text) === 1) {
				$firstDayIndex = $index;
				break;
			}
		}

		if ($firstDayIndex === null) {
			return $dom->saveXML() ?: $xml;
		}

		[$dailyTriplets, $referenceNode] = $this->collectWeeklyDailyTriplets($xpath, $paragraphs, $firstDayIndex);
		if ($dailyTriplets === []) {
			return $dom->saveXML() ?: $xml;
		}

		$headingTemplate = $dailyTriplets[0][0];
		$bodyTemplate = $dailyTriplets[0][1];
		$windTemplate = $dailyTriplets[0][2];
		$parent = ($referenceNode instanceof \DOMNode)
			? $referenceNode->parentNode
			: $headingTemplate->parentNode;
		if (!($parent instanceof \DOMNode)) {
			return $dom->saveXML() ?: $xml;
		}

		foreach ($entries as $index => $entry) {
			if (isset($dailyTriplets[$index])) {
				[$headingParagraph, $bodyParagraph, $windParagraph] = $dailyTriplets[$index];
			} else {
				$headingParagraph = $headingTemplate->cloneNode(true);
				$bodyParagraph = $bodyTemplate->cloneNode(true);
				$windParagraph = $windTemplate->cloneNode(true);
				if (!($headingParagraph instanceof \DOMElement) || !($bodyParagraph instanceof \DOMElement) || !($windParagraph instanceof \DOMElement)) {
					continue;
				}

				if ($referenceNode instanceof \DOMNode) {
					$parent->insertBefore($headingParagraph, $referenceNode);
					$parent->insertBefore($bodyParagraph, $referenceNode);
					$parent->insertBefore($windParagraph, $referenceNode);
				} else {
					$parent->appendChild($headingParagraph);
					$parent->appendChild($bodyParagraph);
					$parent->appendChild($windParagraph);
				}
			}

			$this->setOrdinalParagraphText($dom, $xpath, $headingParagraph, $this->resolveWeeklyHeadingLabel($entry));
			$this->setParagraphText($dom, $xpath, $bodyParagraph, (string)($entry['daily_description'] ?? ''));
			$this->setLabeledParagraphText($dom, $xpath, $windParagraph, 'Wind:', (string)($entry['wind_description'] ?? ''));
		}

		for ($index = count($entries); $index < count($dailyTriplets); $index++) {
			foreach ($dailyTriplets[$index] as $paragraph) {
				$paragraph->parentNode?->removeChild($paragraph);
			}
		}

		return $dom->saveXML() ?: $xml;
	}

	private function prepareWeeklyForecastDocument(string $xml, array $placeholders, array $context): string {
		if (($context['type'] ?? null) !== 'weekly') {
			return $xml;
		}

		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = false;
		if (@$dom->loadXML($xml) === false) {
			return $xml;
		}

		$xpath = new \DOMXPath($dom);
		$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

		$paragraphs = [];
		foreach ($xpath->query('//w:body//w:p') as $paragraphNode) {
			if ($paragraphNode instanceof \DOMElement) {
				$paragraphs[] = $paragraphNode;
			}
		}

		$titleIndex = $this->findParagraphIndex($xpath, $paragraphs, 'WEEKLY WEATHER');
		if ($titleIndex === null) {
			return $xml;
		}

		$periodIndex = $this->findNextNonEmptyParagraphIndex($xpath, $paragraphs, $titleIndex + 1);
		if ($periodIndex !== null) {
			$this->setParagraphText($dom, $xpath, $paragraphs[$periodIndex], (string)($placeholders['weekly_period_display'] ?? ''));
		}

		$summaryIndex = $periodIndex !== null ? $this->findNextNonEmptyParagraphIndex($xpath, $paragraphs, $periodIndex + 1) : null;
		if ($summaryIndex !== null) {
			$this->setParagraphText($dom, $xpath, $paragraphs[$summaryIndex], (string)($placeholders['weekly_summary'] ?? ''));
		}

		/** @var array<int, array{date?: string, display_label?: string, daily_description?: string, wind_description?: string}> $entries */
		$entries = is_array($context['values']['daily_entries'] ?? null) ? $context['values']['daily_entries'] : [];
		if ($entries === []) {
			return $dom->saveXML() ?: $xml;
		}

		$firstDayIndex = null;
		foreach ($paragraphs as $index => $paragraph) {
			$text = $this->paragraphText($xpath, $paragraph);
			if ($firstDayIndex === null && preg_match('/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\s+\d{1,2}(st|nd|rd|th)$/', $text) === 1) {
				$firstDayIndex = $index;
			}
		}

		if ($firstDayIndex === null) {
			return $dom->saveXML() ?: $xml;
		}

		[$dailyTriplets, $referenceNode] = $this->collectWeeklyDailyTriplets($xpath, $paragraphs, $firstDayIndex);

		if ($dailyTriplets === []) {
			return $dom->saveXML() ?: $xml;
		}

		$headingTemplate = $dailyTriplets[0][0];
		$bodyTemplate = $dailyTriplets[0][1];
		$windTemplate = $dailyTriplets[0][2];
		$parent = ($referenceNode instanceof \DOMNode)
			? $referenceNode->parentNode
			: $headingTemplate->parentNode;
		if (!($headingTemplate instanceof \DOMElement) || !($bodyTemplate instanceof \DOMElement) || !($windTemplate instanceof \DOMElement) || !($parent instanceof \DOMNode)) {
			return $dom->saveXML() ?: $xml;
		}

		foreach ($entries as $index => $entry) {
			if (isset($dailyTriplets[$index])) {
				[$headingParagraph, $bodyParagraph, $windParagraph] = $dailyTriplets[$index];
			} else {
				$headingParagraph = $headingTemplate->cloneNode(true);
				$bodyParagraph = $bodyTemplate->cloneNode(true);
				$windParagraph = $windTemplate->cloneNode(true);
				if (!($headingParagraph instanceof \DOMElement) || !($bodyParagraph instanceof \DOMElement) || !($windParagraph instanceof \DOMElement)) {
					continue;
				}

				if ($referenceNode instanceof \DOMNode) {
					$parent->insertBefore($headingParagraph, $referenceNode);
					$parent->insertBefore($bodyParagraph, $referenceNode);
					$parent->insertBefore($windParagraph, $referenceNode);
				} else {
					$parent->appendChild($headingParagraph);
					$parent->appendChild($bodyParagraph);
					$parent->appendChild($windParagraph);
				}
			}

			$this->setParagraphText($dom, $xpath, $headingParagraph, $this->resolveWeeklyHeadingLabel($entry));
			$this->setParagraphText($dom, $xpath, $bodyParagraph, (string)($entry['daily_description'] ?? ''));
			$this->setLabeledParagraphText($dom, $xpath, $windParagraph, 'Wind:', (string)($entry['wind_description'] ?? ''));
		}

		for ($index = count($entries); $index < count($dailyTriplets); $index++) {
			foreach ($dailyTriplets[$index] as $paragraph) {
				$paragraph->parentNode?->removeChild($paragraph);
			}
		}

		return $dom->saveXML() ?: $xml;
	}

	/**
	 * @param array<int, \DOMElement> $paragraphs
	 * @return array{0: array<int, array{0: \DOMElement, 1: \DOMElement, 2: \DOMElement}>, 1: ?\DOMNode}
	 */
	private function collectWeeklyDailyTriplets(\DOMXPath $xpath, array $paragraphs, int $firstDayIndex): array {
		$triplets = [];
		$index = $firstDayIndex;
		$paragraphCount = count($paragraphs);

		while ($index < $paragraphCount) {
			$headingParagraph = $paragraphs[$index] ?? null;
			if (!($headingParagraph instanceof \DOMElement)) {
				break;
			}

			$headingText = $this->paragraphText($xpath, $headingParagraph);
			if (preg_match('/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\s+\d{1,2}(st|nd|rd|th)$/', $headingText) !== 1) {
				break;
			}

			$bodyIndex = $this->findNextNonEmptyParagraphIndex($xpath, $paragraphs, $index + 1);
			if ($bodyIndex === null || !isset($paragraphs[$bodyIndex])) {
				break;
			}

			$windIndex = $this->findNextNonEmptyParagraphIndex($xpath, $paragraphs, $bodyIndex + 1);
			if ($windIndex === null || !isset($paragraphs[$windIndex])) {
				break;
			}

			$windParagraph = $paragraphs[$windIndex];
			if (preg_match('/^Wind:/i', $this->paragraphText($xpath, $windParagraph)) !== 1) {
				break;
			}

			$triplets[] = [
				$headingParagraph,
				$paragraphs[$bodyIndex],
				$windParagraph,
			];

			$index = $windIndex + 1;
			while ($index < $paragraphCount && $this->paragraphText($xpath, $paragraphs[$index]) === '') {
				$index++;
			}
		}

		$referenceNode = $paragraphs[$index] ?? null;

		return [$triplets, $referenceNode];
	}

	/**
	 * @param array{date?: string, display_label?: string} $entry
	 */
	private function resolveWeeklyHeadingLabel(array $entry): string {
		$dateValue = trim((string)($entry['date'] ?? ''));
		if ($dateValue !== '') {
			return $this->buildWeeklyHeadingLabel($dateValue);
		}

		return trim((string)($entry['display_label'] ?? ''));
	}

	private function buildWeeklyHeadingLabel(string $dateValue): string {
		if ($dateValue === '') {
			return '';
		}

		try {
			$date = new \DateTimeImmutable($dateValue);
			$day = (int)$date->format('j');
			$paddedDay = str_pad((string)$day, 2, '0', STR_PAD_LEFT);
			$suffix = match (true) {
				$day % 100 >= 11 && $day % 100 <= 13 => 'th',
				$day % 10 === 1 => 'st',
				$day % 10 === 2 => 'nd',
				$day % 10 === 3 => 'rd',
				default => 'th',
			};

			return sprintf('%s %s%s', $date->format('l'), $paddedDay, $suffix);
		} catch (\Throwable) {
			return $dateValue;
		}
	}

	/**
	 * @param array<string, string> $placeholders
	 * @param array<string, mixed> $context
	 */
	private function prepareNcofReportDocument(string $xml, array $placeholders, array $context): string {
		if (($context['type'] ?? null) !== 'climate_ncof_report') {
			return $xml;
		}

		$xml = $this->replaceLiteralText($xml, '[Insert forecast period]', (string)($placeholders['forecast_period'] ?? ''));
		$xml = $this->replaceLiteralText($xml, 'This document presents the initial National Climate Outlook Forum (NCOF) report template for climate outlook preparation, review, and finalisation.', (string)($placeholders['introduction'] ?? ''));
		$xml = $this->replaceLiteralText($xml, 'Provide a concise summary of the expected seasonal climate conditions, including rainfall and temperature outlook where applicable.', (string)($placeholders['expected_conditions_summary'] ?? ''));
		$xml = $this->replaceLiteralText($xml, 'Summarise notable recent climate conditions and observed anomalies relevant to the forecast period.', (string)($placeholders['recent_climate_review'] ?? ''));
		$xml = $this->replaceLiteralText($xml, 'Provide recommended preparedness, response, or planning actions for stakeholders.', (string)($placeholders['advisory_actions'] ?? ''));
		$xml = $this->replaceLiteralText($xml, 'Prepared by: [Insert name/office]', 'Prepared by: ' . (string)($placeholders['prepared_by'] ?? ''));
		$xml = $this->replaceLiteralText($xml, 'Reviewed by: [Insert name/office]', 'Reviewed by: ' . (string)($placeholders['reviewed_by'] ?? ''));
		$xml = $this->replaceLiteralText($xml, 'Approved by: [Insert name/office]', 'Approved by: ' . (string)($placeholders['approved_by'] ?? ''));

		/** @var mixed $sectorImpacts */
		$sectorImpacts = $context['values']['sector_impacts'] ?? [];
		if (is_array($sectorImpacts) && $sectorImpacts !== []) {
			$xml = $this->replaceNcofSectorParagraphs($xml, $sectorImpacts);
		}

		return $xml;
	}

	private function replaceLiteralText(string $xml, string $search, string $replacement): string {
		if ($replacement === '' || strpos($xml, $search) === false) {
			return $xml;
		}

		return str_replace(
			htmlspecialchars($search, ENT_XML1 | ENT_COMPAT, 'UTF-8'),
			$this->escapeForWord($replacement),
			$xml,
		);
	}

	/**
	 * @param array<int, \DOMElement> $paragraphs
	 */
	private function findParagraphIndex(\DOMXPath $xpath, array $paragraphs, string $text): ?int {
		foreach ($paragraphs as $index => $paragraph) {
			if ($this->paragraphText($xpath, $paragraph) === $text) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * @param array<int, \DOMElement> $paragraphs
	 */
	private function findNextNonEmptyParagraphIndex(\DOMXPath $xpath, array $paragraphs, int $startIndex): ?int {
		for ($index = $startIndex; $index < count($paragraphs); $index++) {
			if ($this->paragraphText($xpath, $paragraphs[$index]) !== '') {
				return $index;
			}
		}

		return null;
	}

	/**
	 * @param array<int, \DOMElement> $paragraphs
	 */
	private function replaceParagraphAfterHeading(\DOMDocument $dom, \DOMXPath $xpath, array $paragraphs, string $heading, string $text): void {
		if ($text === '') {
			return;
		}

		$headingIndex = $this->findParagraphIndex($xpath, $paragraphs, $heading);
		if ($headingIndex === null) {
			return;
		}

		for ($index = $headingIndex + 1; $index < count($paragraphs); $index++) {
			$paragraphText = $this->paragraphText($xpath, $paragraphs[$index]);
			if ($paragraphText === '') {
				continue;
			}

			$this->setParagraphText($dom, $xpath, $paragraphs[$index], $text);
			return;
		}
	}

	/**
	 * @param array<int, \DOMElement> $paragraphs
	 * @param array<int, string> $texts
	 */
	private function replaceParagraphsAfterHeading(\DOMDocument $dom, \DOMXPath $xpath, array $paragraphs, string $heading, array $texts): void {
		$headingIndex = $this->findParagraphIndex($xpath, $paragraphs, $heading);
		if ($headingIndex === null) {
			return;
		}

		$textIndex = 0;
		for ($index = $headingIndex + 1; $index < count($paragraphs) && $textIndex < count($texts); $index++) {
			$paragraphText = $this->paragraphText($xpath, $paragraphs[$index]);
			if ($paragraphText === '') {
				continue;
			}

			$replacement = trim((string)$texts[$textIndex]);
			if ($replacement !== '') {
				$this->setParagraphText($dom, $xpath, $paragraphs[$index], $replacement);
			}
			$textIndex++;
		}
	}

	/**
	 * @param array<int, \DOMElement> $paragraphs
	 * @param array<int, array{label: string, body: string}> $segments
	 */
	private function replaceTwoDayNarrativeParagraphs(\DOMDocument $dom, \DOMXPath $xpath, array $paragraphs, string $heading, array $segments): void {
		$headingIndex = $this->findParagraphIndex($xpath, $paragraphs, $heading);
		if ($headingIndex === null) {
			return;
		}

		$segmentIndex = 0;
		for ($index = $headingIndex + 1; $index < count($paragraphs) && $segmentIndex < count($segments); $index++) {
			$paragraphText = $this->paragraphText($xpath, $paragraphs[$index]);
			if ($paragraphText === '') {
				continue;
			}

			$segment = $segments[$segmentIndex];
			$this->setTwoDayNarrativeParagraphText(
				$dom,
				$xpath,
				$paragraphs[$index],
				$segment['label'],
				$segment['body'],
			);
			$segmentIndex++;
		}
	}

	/**
	 * @param array<int, mixed> $sectorImpacts
	 */
	private function replaceNcofSectorParagraphs(string $xml, array $sectorImpacts): string {
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = false;

		if (@$dom->loadXML($xml) === false) {
			return $xml;
		}

		$xpath = new \DOMXPath($dom);
		$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

		$paragraphs = [];
		foreach ($xpath->query('//w:body/w:p') as $paragraphNode) {
			if ($paragraphNode instanceof \DOMElement) {
				$paragraphs[] = $paragraphNode;
			}
		}

		$startIndex = null;
		$endIndex = null;
		foreach ($paragraphs as $index => $paragraph) {
			$text = $this->paragraphText($xpath, $paragraph);
			if ($startIndex === null && $text === '7.1 Agriculture') {
				$startIndex = $index;
			}

			if ($text === '8. Advisory / Recommended Actions') {
				$endIndex = $index;
				break;
			}
		}

		if ($startIndex === null || $endIndex === null || $startIndex + 1 >= $endIndex) {
			return $xml;
		}

		$headingTemplate = $paragraphs[$startIndex];
		$bodyTemplate = $paragraphs[$startIndex + 1];
		$referenceNode = $paragraphs[$endIndex];
		$parent = $referenceNode->parentNode;
		if (!($parent instanceof \DOMNode)) {
			return $xml;
		}

		for ($index = $startIndex; $index < $endIndex; $index++) {
			$node = $paragraphs[$index];
			$node->parentNode?->removeChild($node);
		}

		$insertBefore = $referenceNode;
		foreach (array_values($this->normalizeNcofSectorImpacts($sectorImpacts)) as $index => $sectorImpact) {
			$headingParagraph = $headingTemplate->cloneNode(true);
			$bodyParagraph = $bodyTemplate->cloneNode(true);
			if (!($headingParagraph instanceof \DOMElement) || !($bodyParagraph instanceof \DOMElement)) {
				continue;
			}

			$this->setParagraphText($dom, $xpath, $headingParagraph, sprintf('7.%d %s', $index + 1, $sectorImpact['name']));
			$this->setParagraphText($dom, $xpath, $bodyParagraph, $sectorImpact['description']);
			$parent->insertBefore($headingParagraph, $insertBefore);
			$parent->insertBefore($bodyParagraph, $insertBefore);
		}

		return $dom->saveXML() ?: $xml;
	}

	private function paragraphText(\DOMXPath $xpath, \DOMElement $paragraph): string {
		$text = '';
		foreach ($xpath->query('.//w:t', $paragraph) as $textNode) {
			$text .= $textNode->textContent;
		}

		return trim($text);
	}

	/**
	 * @param array<int, mixed> $temperatureRows
	 */
	private function replaceMorningTemperatureTable(\DOMDocument $dom, \DOMXPath $xpath, array $temperatureRows): void {
		$table = $xpath->query('//w:body/w:tbl')->item(0);
		if (!($table instanceof \DOMElement)) {
			return;
		}

		$rows = [];
		foreach ($xpath->query('./w:tr', $table) as $rowNode) {
			if ($rowNode instanceof \DOMElement) {
				$rows[] = $rowNode;
			}
		}

		if (count($rows) < 2) {
			return;
		}

		$templateRow = $rows[1];
		for ($index = count($rows) - 1; $index >= 1; $index--) {
			$rows[$index]->parentNode?->removeChild($rows[$index]);
		}

		$normalizedRows = [];
		foreach ($temperatureRows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$area = trim((string)($row['area'] ?? ''));
			$temperature = trim((string)($row['temperature'] ?? ''));
			if ($area === '' || $temperature === '') {
				continue;
			}

			$normalizedRows[] = [
				'area' => $area,
				'temperature' => $temperature,
			];
		}

		foreach ($normalizedRows as $rowData) {
			$row = $templateRow->cloneNode(true);
			if (!($row instanceof \DOMElement)) {
				continue;
			}

			$cells = [];
			foreach ($xpath->query('./w:tc', $row) as $cellNode) {
				if ($cellNode instanceof \DOMElement) {
					$cells[] = $cellNode;
				}
			}

			if (isset($cells[0])) {
				$this->setCellText($dom, $xpath, $cells[0], $rowData['area']);
			}

			if (isset($cells[1])) {
				$this->setCellText($dom, $xpath, $cells[1], $rowData['temperature']);
			}

			$table->appendChild($row);
		}
	}

	/**
	 * @param array<int, mixed> $temperatureRows
	 */
	private function replaceTwoDayTemperatureTable(\DOMDocument $dom, \DOMXPath $xpath, array $temperatureRows): void {
		$table = $xpath->query('//w:body/w:tbl')->item(0);
		if (!($table instanceof \DOMElement)) {
			return;
		}

		$rows = [];
		foreach ($xpath->query('./w:tr', $table) as $rowNode) {
			if ($rowNode instanceof \DOMElement) {
				$rows[] = $rowNode;
			}
		}

		if (count($rows) < 2) {
			return;
		}

		$templateRow = $rows[1];
		for ($index = count($rows) - 1; $index >= 1; $index--) {
			$rows[$index]->parentNode?->removeChild($rows[$index]);
		}

		$normalizedRows = [];
		foreach ($temperatureRows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$area = trim((string)($row['area'] ?? ''));
			$maxThisAfternoon = trim((string)($row['max_this_afternoon'] ?? ''));
			$minTonight = trim((string)($row['min_tonight'] ?? ''));
			$maxTomorrow = trim((string)($row['max_tomorrow'] ?? ''));
			$minTomorrow = trim((string)($row['min_tomorrow'] ?? ''));
			if ($area === '' || $maxThisAfternoon === '' || $minTonight === '' || $maxTomorrow === '' || $minTomorrow === '') {
				continue;
			}

			$normalizedRows[] = [
				'area' => $area,
				'max_this_afternoon' => $maxThisAfternoon,
				'min_tonight' => $minTonight,
				'max_tomorrow' => $maxTomorrow,
				'min_tomorrow' => $minTomorrow,
			];
		}

		foreach ($normalizedRows as $rowData) {
			$row = $templateRow->cloneNode(true);
			if (!($row instanceof \DOMElement)) {
				continue;
			}

			$cells = [];
			foreach ($xpath->query('./w:tc', $row) as $cellNode) {
				if ($cellNode instanceof \DOMElement) {
					$cells[] = $cellNode;
				}
			}

			if (isset($cells[0])) {
				$this->setCellText($dom, $xpath, $cells[0], $rowData['area']);
			}
			if (isset($cells[1])) {
				$this->setCellText($dom, $xpath, $cells[1], $rowData['max_this_afternoon']);
			}
			if (isset($cells[2])) {
				$this->setCellText($dom, $xpath, $cells[2], $rowData['min_tonight']);
			}
			if (isset($cells[3])) {
				$this->setCellText($dom, $xpath, $cells[3], $rowData['max_tomorrow']);
			}
			if (isset($cells[4])) {
				$this->setCellText($dom, $xpath, $cells[4], $rowData['min_tomorrow']);
			}

			$table->appendChild($row);
		}
	}

	private function setCellText(\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $cell, string $text): void {
		$paragraph = null;
		foreach ($xpath->query('./w:p', $cell) as $paragraphNode) {
			if ($paragraphNode instanceof \DOMElement) {
				$paragraph = $paragraphNode;
				break;
			}
		}

		if ($paragraph instanceof \DOMElement) {
			$this->setParagraphText($dom, $xpath, $paragraph, $text);
		}
	}

	private function setParagraphText(\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $paragraph, string $text): void {
		$run = null;
		$runProperties = null;
		foreach ($xpath->query('./w:r', $paragraph) as $candidate) {
			if ($candidate instanceof \DOMElement) {
				$run = $candidate;
				foreach ($xpath->query('./w:rPr', $candidate) as $candidateProperties) {
					if ($candidateProperties instanceof \DOMElement) {
						$runProperties = $candidateProperties->cloneNode(true);
						break;
					}
				}
				break;
			}
		}

		$paragraphProperties = null;
		foreach ($xpath->query('./w:pPr', $paragraph) as $candidateProperties) {
			if ($candidateProperties instanceof \DOMElement) {
				$paragraphProperties = $candidateProperties->cloneNode(true);
				break;
			}
		}

		while ($paragraph->firstChild !== null) {
			$paragraph->removeChild($paragraph->firstChild);
		}

		if ($paragraphProperties instanceof \DOMNode) {
			$paragraph->appendChild($paragraphProperties);
		}

		$newRun = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
		if ($runProperties instanceof \DOMNode) {
			$newRun->appendChild($runProperties);
		}

		$lines = preg_split('/\R/u', $text) ?: [''];
		foreach (array_values($lines) as $index => $line) {
			if ($index > 0) {
				$newRun->appendChild($dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:br'));
			}

			$textNode = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
			if ($line !== trim($line)) {
				$textNode->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
			}
			$textNode->appendChild($dom->createTextNode($line));
			$newRun->appendChild($textNode);
		}

		$paragraph->appendChild($newRun);
	}

	private function setOrdinalParagraphText(\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $paragraph, string $text): void {
		$this->setParagraphText($dom, $xpath, $paragraph, $text);

		$referenceRun = $xpath->query('./w:r', $paragraph)->item(0);
		if (!($referenceRun instanceof \DOMElement)) {
			return;
		}

		$runProperties = $xpath->query('./w:rPr', $referenceRun)->item(0);
		$baseRunProperties = $runProperties instanceof \DOMElement
			? $runProperties->cloneNode(true)
			: null;
		$paragraph->removeChild($referenceRun);

		$appendRun = function (string $value, bool $superscript) use ($dom, $xpath, $paragraph, $baseRunProperties): void {
			if ($value === '') {
				return;
			}

			$run = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
			$properties = $baseRunProperties instanceof \DOMNode
				? $baseRunProperties->cloneNode(true)
				: $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:rPr');
			if ($properties instanceof \DOMElement) {
				foreach ($xpath->query('./w:vertAlign', $properties) as $verticalAlignment) {
					$properties->removeChild($verticalAlignment);
				}
				if ($superscript) {
					$verticalAlignment = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:vertAlign');
					$verticalAlignment->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:val', 'superscript');
					$properties->appendChild($verticalAlignment);
				}
				$run->appendChild($properties);
			}

			$textNode = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
			if ($value !== trim($value)) {
				$textNode->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
			}
			$textNode->appendChild($dom->createTextNode($value));
			$run->appendChild($textNode);
			$paragraph->appendChild($run);
		};

		$cursor = 0;
		if (preg_match_all('/\d{1,2}(st|nd|rd|th)/i', $text, $matches, PREG_OFFSET_CAPTURE) === false) {
			$appendRun($text, false);
			return;
		}

		foreach ($matches[0] as $match) {
			$ordinal = (string)$match[0];
			$offset = (int)$match[1];
			$suffix = substr($ordinal, -2);
			$number = substr($ordinal, 0, -2);
			$appendRun(substr($text, $cursor, $offset - $cursor) . $number, false);
			$appendRun($suffix, true);
			$cursor = $offset + strlen($ordinal);
		}

		$appendRun(substr($text, $cursor), false);
	}

	private function setTwoDayNarrativeParagraphText(\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $paragraph, string $labelText, string $bodyText): void {
		$labelRuns = [];
		$bodyRuns = [];

		foreach ($xpath->query('./w:r', $paragraph) as $runNode) {
			if (!($runNode instanceof \DOMElement)) {
				continue;
			}

			if ($this->isNarrativeBodyRun($xpath, $runNode)) {
				$bodyRuns[] = $runNode;
				continue;
			}

			$labelRuns[] = $runNode;
		}

		if ($labelRuns === [] || $bodyRuns === []) {
			$this->setParagraphText($dom, $xpath, $paragraph, trim($labelText . ' ' . $bodyText));
			return;
		}

		$this->replaceRunGroupText($dom, $xpath, $labelRuns, $labelText);
		$this->replaceRunGroupText($dom, $xpath, $bodyRuns, ' ' . ltrim($bodyText));
	}

	private function setLabeledParagraphText(\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $paragraph, string $labelText, string $bodyText): void {
		$labelRuns = [];
		$bodyRuns = [];
		$seenBodyRun = false;

		foreach ($xpath->query('./w:r', $paragraph) as $runNode) {
			if (!($runNode instanceof \DOMElement)) {
				continue;
			}

			if (!$seenBodyRun && $this->isNarrativeBodyRun($xpath, $runNode)) {
				$seenBodyRun = true;
			}

			if ($seenBodyRun) {
				$bodyRuns[] = $runNode;
			} else {
				$labelRuns[] = $runNode;
			}
		}

		if ($labelRuns === [] || $bodyRuns === []) {
			$this->setParagraphText($dom, $xpath, $paragraph, trim($labelText . ' ' . $bodyText));
			return;
		}

		$this->replaceRunGroupText($dom, $xpath, $labelRuns, $labelText);
		$this->replaceRunGroupText($dom, $xpath, $bodyRuns, ' ' . ltrim($bodyText));
	}

	private function isNarrativeBodyRun(\DOMXPath $xpath, \DOMElement $run): bool {
		$hasBold = $xpath->query('./w:rPr/w:b', $run)->length > 0;
		$hasUnderline = $xpath->query('./w:rPr/w:u', $run)->length > 0;

		return !$hasBold && !$hasUnderline;
	}

	/**
	 * @param array<int, \DOMElement> $runs
	 */
	private function replaceRunGroupText(\DOMDocument $dom, \DOMXPath $xpath, array $runs, string $text): void {
		$firstRun = $runs[0] ?? null;
		if (!($firstRun instanceof \DOMElement)) {
			return;
		}

		foreach ($runs as $run) {
			foreach (iterator_to_array($run->childNodes) as $child) {
				if ($child instanceof \DOMElement && $child->namespaceURI === 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' && $child->localName === 'rPr') {
					continue;
				}
				$run->removeChild($child);
			}
		}

		$lines = preg_split('/\R/u', $text) ?: [''];
		foreach (array_values($lines) as $lineIndex => $line) {
			if ($lineIndex > 0) {
				$firstRun->appendChild($dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:br'));
			}

			$textNode = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
			if ($line !== trim($line)) {
				$textNode->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
			}
			$textNode->appendChild($dom->createTextNode($line));
			$firstRun->appendChild($textNode);
		}
	}

	private function isZipBasedDocx(string $templatePath): bool {
		$handle = @fopen($templatePath, 'rb');
		if ($handle === false) {
			return false;
		}

		try {
			$signature = fread($handle, 4);
		} finally {
			fclose($handle);
		}

		return $signature === "PK\x03\x04";
	}

	/**
	 * @return array{0: string, 1: string}
	 */
	private function convertLegacyWordTemplate(string $templatePath): array {
		$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'csis_word_' . bin2hex(random_bytes(8));
		if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
			throw new \RuntimeException('Unable to create a temporary conversion directory.');
		}

		$tempSourcePath = $tempDir . DIRECTORY_SEPARATOR . 'source.doc';
		if (!copy($templatePath, $tempSourcePath)) {
			$this->cleanupDirectory($tempDir);
			throw new \RuntimeException('Unable to prepare the legacy Word template for conversion.');
		}

		$command = sprintf(
			'soffice --headless --convert-to docx --outdir %s %s 2>&1',
			escapeshellarg($tempDir),
			escapeshellarg($tempSourcePath),
		);
		exec($command, $output, $exitCode);

		$convertedPath = $tempDir . DIRECTORY_SEPARATOR . 'source.docx';
		if ($exitCode !== 0 || !is_file($convertedPath)) {
			$this->cleanupDirectory($tempDir);
			throw new \RuntimeException('Unable to convert the legacy Word template to DOCX.');
		}

		return [$convertedPath, $tempDir];
	}

	private function cleanupDirectory(string $directory): void {
		if (!is_dir($directory)) {
			return;
		}

		$items = scandir($directory);
		if ($items === false) {
			return;
		}

		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = $directory . DIRECTORY_SEPARATOR . $item;
			if (is_dir($path)) {
				$this->cleanupDirectory($path);
				continue;
			}

			@unlink($path);
		}

		@rmdir($directory);
	}

	/**
	 * @param array<int, mixed> $sectorImpacts
	 * @return array<int, array{name: string, description: string}>
	 */
	private function normalizeNcofSectorImpacts(array $sectorImpacts): array {
		$normalized = [];
		foreach ($sectorImpacts as $sectorImpact) {
			if (!is_array($sectorImpact)) {
				continue;
			}

			$name = trim((string)($sectorImpact['name'] ?? ''));
			$description = trim((string)($sectorImpact['description'] ?? ''));
			if ($name === '' || $description === '') {
				continue;
			}

			$normalized[] = [
				'name' => $name,
				'description' => $description,
			];
		}

		return $normalized;
	}
}
