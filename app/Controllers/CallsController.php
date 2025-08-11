<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\Models\Call;
use FlujosDimension\DTO\CallMetadataDTO;
use FlujosDimension\Support\Validator;

/**
 * CRUD operations for call records stored in the system.
 */
class CallsController extends BaseController
{
    /**
     * List calls with pagination metadata.
     */
    public function index(): Response
    {
        try {
            if (!$this->container->bound(Call::class)) {
                return $this->successResponse([]);
            }

            $params = $this->getPaginationParams();
            /** @var Call $model */
            $model  = $this->service(Call::class);
            $result = $model->paginate($params['page'], $params['per_page'], $params['order_by'], $params['direction']);

            return $this->jsonResponse([
                'success' => true,
                'data'    => $result['data'],
                'meta'    => $result['meta'],
            ]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error listing calls');
        }
    }

    /**
     * Display a single call record.
     */
    public function show(string $id): Response
    {
        try {
            if (!$this->container->bound(Call::class)) {
                return $this->successResponse(null);
            }

            /** @var Call $model */
            $model = $this->service(Call::class);
            $call  = $model->findOrFail((int) $id);
            return $this->successResponse($call);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error retrieving call');
        }
    }

    /**
     * Persist a new call entry.
     */
    public function store(): Response
    {
        try {
            if (!$this->container->bound(Call::class)) {
                return $this->jsonResponse(['success' => true], 201);
            }

            $data = $this->normalizeInput($this->request->all());
            $dto  = new CallMetadataDTO(
                $data['phone_number'] ?? '',
                $data['direction'] ?? '',
                $data['status'] ?? '',
                isset($data['duration']) ? (int)$data['duration'] : null
            );

            $errors = Validator::validate($dto->toArray(), [
                'phone_number' => 'required|format:phone',
                'direction'    => 'required|in:inbound,outbound',
                'status'       => 'required|string',
                'duration'     => 'integer',
            ]);

            if ($errors) {
                return $this->jsonResponse(['success' => false, 'errors' => $errors], 422);
            }

            /** @var Call $model */
            $model = $this->service(Call::class);
            $created = $model->create($dto->toArray());
            return $this->jsonResponse(['success' => true, 'data' => $created], 201);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error creating call');
        }
    }

    /**
     * Update an existing call.
     */
    public function update(string $id): Response
    {
        try {
            if (!$this->container->bound(Call::class)) {
                return $this->successResponse(['updated' => (int)$id]);
            }

            $data = $this->normalizeInput($this->request->all());
            $dto  = new CallMetadataDTO(
                $data['phone_number'] ?? '',
                $data['direction'] ?? '',
                $data['status'] ?? '',
                isset($data['duration']) ? (int)$data['duration'] : null
            );

            $payload = array_filter($dto->toArray(), fn($v) => $v !== '' && $v !== null);
            $rules = [];
            if (array_key_exists('phone_number', $payload)) {
                $rules['phone_number'] = 'format:phone';
            }
            if (array_key_exists('direction', $payload)) {
                $rules['direction'] = 'in:inbound,outbound';
            }
            if (array_key_exists('status', $payload)) {
                $rules['status'] = 'string';
            }
            if (array_key_exists('duration', $payload)) {
                $rules['duration'] = 'integer';
            }

            $errors = Validator::validate($payload, $rules);
            if ($errors) {
                return $this->jsonResponse(['success' => false, 'errors' => $errors], 422);
            }

            /** @var Call $model */
            $model  = $this->service(Call::class);
            $updated = $model->update((int) $id, $payload);
            return $this->successResponse($updated);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error updating call');
        }
    }

    /**
     * Delete a call record from storage.
     */
    public function destroy(string $id): Response
    {
        try {
            if (!$this->container->bound(Call::class)) {
                return $this->successResponse(['deleted' => (int)$id]);
            }

            /** @var Call $model */
            $model = $this->service(Call::class);
            $model->delete((int) $id);
            return $this->successResponse(['deleted' => (int) $id]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error deleting call');
        }
    }
}
