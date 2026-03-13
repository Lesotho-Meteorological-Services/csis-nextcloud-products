<?php
declare(strict_types=1);

namespace OCA\CSISProducts\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;

class ForecastController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private IRootFolder $rootFolder,
        private IUserSession $userSession,
        private IGroupManager $groupManager,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function create(string $dir, string $type): DataResponse {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new DataResponse(['message' => 'Not authenticated'], 401);
        }

        // OPTIONAL security: enforce group
        // If you want this, ensure the group exists in Nextcloud.
        // if (!$this->groupManager->isInGroup($user->getUID(), 'LMS_Forecasters')) {
        //     return new DataResponse(['message' => 'Not allowed'], 403);
        // }

        $allowed = ['morning', 'two_day', 'weekly', 'agromet_dekadal', 'agromet_monthly', 'climate_seasonal', 'climate_ncof_report'];
        if (!in_array($type, $allowed, true)) {
            return new DataResponse(['message' => 'Invalid type'], 400);
        }

        $now = new \DateTimeImmutable('now');

        [$templateCandidates, $nameBase] = match ($type) {
            // Match current on-disk template names first, with generic fallbacks.
            'morning' => [['722C_Morning Forecast.docx', 'daily_forecast.docx'], 'Morning_Forecast_' . $now->format('Y-m-d')],
            'two_day' => [['722A_Two Day Forecast.docx', 'two_day_forecast.docx'], 'Two_Day_Forecast_' . $now->format('Y-m-d')],
            'weekly' => [['723_Weekly Forecast.docx', 'weekly_forecast.docx'], 'Weekly_Forecast_' . $now->format('o-\WW')],
            'agromet_dekadal' => [['Agromet_Dekadal.docx', 'agromet_dekadal.docx'], 'Agromet_Dekadal_' . $this->dekadLabel($now)],
            'agromet_monthly' => [['Agromet_Monthly.docx', 'agromet_monthly.docx'], 'Agromet_Monthly_' . $now->format('Y-m')],
            'climate_seasonal' => [['Climate_Seasonal_Forecast.docx', 'climate_seasonal_forecast.docx'], 'Climate_Seasonal_Forecast_' . $this->seasonLabel($now)],
            'climate_ncof_report' => [['Climate_Seasonal_Forecast.docx', 'climate_seasonal_forecast.docx'], 'Climate_NCOF_Report_' . $this->seasonLabel($now)],
        };

        $uid = $user->getUID();
        $userFolder = $this->rootFolder->getUserFolder($uid);

        // dir arrives like "/" or "/Folder/Subfolder"
        $dir = trim($dir);
        $dir = $dir === '' ? '/' : $dir;
        $relDir = ltrim($dir, '/'); // relative inside user folder

        $folder = $relDir === '' ? $userFolder : $userFolder->get($relDir);
        if (!($folder instanceof \OCP\Files\Folder) || !$folder->isCreatable()) {
            return new DataResponse(['message' => 'Target folder not creatable'], 409);
        }

        $name = $nameBase . '.docx';
        $i = 1;
        while ($folder->nodeExists($name)) {
            $name = $nameBase . "_$i.docx";
            $i++;
        }

        $templateDir = \dirname(__DIR__, 2) . '/resources/templates/';
        $templatePath = null;
        foreach ($templateCandidates as $candidate) {
            $candidatePath = $templateDir . $candidate;
            if (\is_file($candidatePath)) {
                $templatePath = $candidatePath;
                break;
            }
        }

        if ($templatePath === null) {
            return new DataResponse([
                'message' => 'Template missing',
                'expected' => $templateCandidates,
            ], 500);
        }

        $content = @file_get_contents($templatePath);
        if ($content === false) {
            return new DataResponse(['message' => 'Template unreadable'], 500);
        }

        $file = $folder->newFile($name);
        $file->putContent($content);

        // This must be the path in the user’s files tree
        $filePath = '/' . trim($relDir . '/' . $name, '/');

        return new DataResponse([
            'fileId' => $file->getId(),
            'fileName' => $name,
            'filePath' => $filePath,
        ]);
    }

    private function seasonLabel(\DateTimeImmutable $dt): string {
        // DJF, MAM, JJA, SON
        $m = (int)$dt->format('n');
        $y = (int)$dt->format('Y');

        return match (true) {
            $m === 12 => ($y . '-' . ($y + 1) . '_DJF'),
            $m === 1 || $m === 2 => (($y - 1) . '-' . $y . '_DJF'),
            $m >= 3 && $m <= 5 => ($y . '_MAM'),
            $m >= 6 && $m <= 8 => ($y . '_JJA'),
            default => ($y . '_SON'),
        };
    }

    private function dekadLabel(\DateTimeImmutable $dt): string {
        $day = (int)$dt->format('j');
        $dekad = match (true) {
            $day <= 10 => 1,
            $day <= 20 => 2,
            default => 3,
        };

        return $dt->format('Y-m') . '_D' . $dekad;
    }
}
