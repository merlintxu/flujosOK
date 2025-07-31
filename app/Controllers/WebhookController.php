<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;

/**
 * Receive external webhook calls.
 */
class WebhookController extends BaseController
{
    /**
     * Register a new webhook endpoint.
     */
    public function create(): Response
    {
        try {
            $data = $this->request->all();

            $this->validate($data, [
                'url'   => 'required|string',
                'event' => 'required|string',
            ]);

            if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('Invalid URL');
            }

            $data['created_at'] = date('Y-m-d H:i:s');

            if ($this->container->bound(\FlujosDimension\Models\Webhook::class)) {
                /** @var \FlujosDimension\Models\Webhook $model */
                $model  = $this->service(\FlujosDimension\Models\Webhook::class);
                $created = $model->create($data);

                return $this->jsonResponse(['success' => true, 'data' => $created], 201);
            }

            return $this->jsonResponse(['success' => true], 201);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error creating webhook');
        }
    }
}
