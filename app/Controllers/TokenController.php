<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use FlujosDimension\Core\JWT;

/**
 * Issue and validate API tokens.
 */
class TokenController extends BaseController
{
    /**
     * Generate a new API token for the caller.
     */
    public function generate(): Response
    {
        try {
            if ($this->container->bound(JWT::class) || $this->container->bound('jwtService')) {
                /** @var JWT $jwt */
                $jwt = $this->service('jwtService');
                $token = $jwt->generateToken();
            } else {
                $token = bin2hex(random_bytes(16));
            }
            return $this->jsonResponse(['token' => $token]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error generating token');
        }
    }

    /**
     * Check if a provided token is valid.
     */
    public function verify(): Response
    {
        try {
            $data = $this->request->all();
            $this->validate($data, ['token' => 'required|string']);

            $valid = true;
            if ($this->container->bound(JWT::class) || $this->container->bound('jwtService')) {
                /** @var JWT $jwt */
                $jwt = $this->service('jwtService');
                $valid = (bool) $jwt->validateToken($data['token']);
            } else {
                $valid = $data['token'] !== '';
            }
            return $this->jsonResponse(['valid' => $valid]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error validating token');
        }
    }

    /**
     * Revoke a previously issued token.
     */
    public function revoke(): Response
    {
        try {
            $data = $this->request->all();
            $this->validate($data, ['token' => 'required|string']);

            $revoked = true;
            if ($this->container->bound(JWT::class) || $this->container->bound('jwtService')) {
                /** @var JWT $jwt */
                $jwt = $this->service('jwtService');
                $revoked = $jwt->revokeToken($data['token']);
            }
            return $this->jsonResponse(['revoked' => $revoked]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error revoking token');
        }
    }

    /**
     * List currently active tokens.
     */
    public function active(): Response
    {
        try {
            if (!$this->container->bound(JWT::class) && !$this->container->bound('jwtService')) {
                return $this->successResponse([]);
            }

            /** @var JWT $jwt */
            $jwt = $this->service('jwtService');
            $tokens = $jwt->getActiveTokens();

            return $this->successResponse($tokens);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error listing active tokens');
        }
    }
}
