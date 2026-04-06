<?php
declare(strict_types=1);

namespace OCA\CSISProducts\Controller;

use OCA\CSISProducts\Service\StructuredProductService;
use OCA\CSISProducts\Service\ValidationException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class StructuredProductController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private StructuredProductService $structuredProductService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function definition(string $type): DataResponse {
		$definition = $this->structuredProductService->getDefinition($type);
		if ($definition === null) {
			return new DataResponse(['message' => 'Structured product type not found'], 404);
		}

		return new DataResponse($definition);
	}

	#[NoAdminRequired]
	public function generate(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['message' => 'Not authenticated'], 401);
		}

		$dir = (string)$this->request->getParam('dir', '/');
		$type = (string)$this->request->getParam('type', '');
		$values = $this->request->getParam('values', []);
		if (!is_array($values)) {
			return new DataResponse(['message' => 'Invalid values payload'], 400);
		}

		try {
			$result = $this->structuredProductService->generate($user->getUID(), $dir, $type, $values);
		} catch (ValidationException $exception) {
			return new DataResponse([
				'message' => $exception->getMessage(),
				'errors' => $exception->getErrors(),
			], 422);
		} catch (\InvalidArgumentException $exception) {
			return new DataResponse(['message' => $exception->getMessage()], 400);
		} catch (\RuntimeException $exception) {
			return new DataResponse(['message' => $exception->getMessage()], 409);
		} catch (\Throwable $exception) {
			return new DataResponse(['message' => 'Failed to generate structured product'], 500);
		}

		return new DataResponse($result);
	}
}
