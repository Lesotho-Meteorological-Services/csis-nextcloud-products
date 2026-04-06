<?php
declare(strict_types=1);

namespace OCA\CSISProducts\Service;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;

class StructuredProductService {
	public function __construct(
		private StructuredProductRegistry $registry,
		private DocxTemplateService $docxTemplateService,
		private IRootFolder $rootFolder,
	) {
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getDefinition(string $type): ?array {
		$definition = $this->registry->get($type);
		if ($definition === null) {
			return null;
		}

		return [
			'type' => $definition['type'],
			'category' => $definition['category'],
			'label' => $definition['label'],
			'subtitle' => $definition['subtitle'] ?? null,
			'modalWidth' => $definition['modalWidth'] ?? null,
			'fields' => $definition['fields'],
			'sections' => $definition['sections'] ?? null,
		];
	}

	/**
	 * @param array<string, mixed> $submittedValues
	 * @return array<string, mixed>
	 */
	public function generate(string $uid, string $dir, string $type, array $submittedValues): array {
		$definition = $this->registry->get($type);
		if ($definition === null) {
			throw new \InvalidArgumentException('Unknown structured product type.');
		}

		$validation = $this->registry->normalizeAndValidate($definition, $submittedValues);
		if ($validation['errors'] !== []) {
			throw new ValidationException($validation['errors']);
		}

		$values = $validation['values'];
		$templatePath = \dirname(__DIR__, 2) . '/resources/templates/' . $definition['template'];
		if (!is_file($templatePath)) {
			throw new \RuntimeException('Structured product template is missing.');
		}

		$content = $this->docxTemplateService->render(
			$templatePath,
			$this->registry->buildPlaceholders($type, $values),
			[
				'type' => $type,
				'values' => $values,
			],
		);

		$userFolder = $this->rootFolder->getUserFolder($uid);
		$folder = $this->resolveFolder($userFolder, $dir);

		$nameBase = $this->registry->buildFilenameBase($type, $values);
		$name = $this->ensureUniqueFilename($folder, $nameBase);

		$file = $folder->newFile($name);
		$file->putContent($content);

		$relDir = ltrim(trim($dir) === '' ? '/' : trim($dir), '/');
		$filePath = '/' . trim($relDir . '/' . $name, '/');

		return [
			'fileId' => $file->getId(),
			'fileName' => $name,
			'filePath' => $filePath,
			'values' => $values,
		];
	}

	private function resolveFolder(Folder $userFolder, string $dir): Folder {
		$dir = trim($dir);
		$dir = $dir === '' ? '/' : $dir;
		$relDir = ltrim($dir, '/');

		if ($relDir === '') {
			$folder = $userFolder;
		} else {
			if (!$userFolder->nodeExists($relDir)) {
				throw new \RuntimeException('Target folder does not exist.');
			}

			$folder = $userFolder->get($relDir);
		}

		if (!($folder instanceof Folder) || !$folder->isCreatable()) {
			throw new \RuntimeException('Target folder is not creatable.');
		}

		return $folder;
	}

	private function ensureUniqueFilename(Folder $folder, string $nameBase): string {
		$name = $nameBase . '.docx';
		$counter = 1;

		while ($folder->nodeExists($name)) {
			$name = sprintf('%s_%d.docx', $nameBase, $counter);
			$counter++;
		}

		return $name;
	}
}
