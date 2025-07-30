<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\Models\Call;

class CallsController extends BaseController
{
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

    public function store(): Response
    {
        try {
            if (!$this->container->bound(Call::class)) {
                return $this->jsonResponse(['success' => true], 201);
            }

            $data = $this->request->all();
            $this->validate($data, [
                'phone_number' => 'required|string',
                'direction'    => 'required|in:inbound,outbound',
                'status'       => 'required|string',
                'duration'     => 'integer',
            ]);

            /** @var Call $model */
            $model = $this->service(Call::class);
            $created = $model->create($data);
            return $this->jsonResponse(['success' => true, 'data' => $created], 201);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error creating call');
        }
    }

    public function update(string $id): Response
    {
        try {
            if (!$this->container->bound(Call::class)) {
                return $this->successResponse(['updated' => (int)$id]);
            }

            $data = $this->request->all();
            $this->validate($data, [
                'phone_number' => 'string',
                'direction'    => 'in:inbound,outbound',
                'status'       => 'string',
                'duration'     => 'integer',
            ]);

            /** @var Call $model */
            $model  = $this->service(Call::class);
            $updated = $model->update((int) $id, $data);
            return $this->successResponse($updated);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error updating call');
        }
    }

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
