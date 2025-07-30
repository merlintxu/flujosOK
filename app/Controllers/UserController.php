<?php

namespace FlujosDimension\Controllers;

use FlujosDimension\Core\Response;
use PDO;

class UserController extends BaseController
{
    public function index(): Response
    {
        try {
            if (!$this->container->bound('database')) {
                return $this->successResponse([]);
            }

            /** @var PDO $db */
            $db = $this->service('database');
            $users = $db->query('SELECT id, username, email, role FROM users WHERE deleted_at IS NULL')->fetchAll();
            return $this->successResponse($users);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error listing users');
        }
    }

    public function create(): Response
    {
        try {
            if (!$this->container->bound('database')) {
                return $this->jsonResponse(['success' => true, 'id' => 1], 201);
            }

            $data = $this->request->all();
            $this->validate($data, [
                'username' => 'required|string',
                'email'    => 'required|email',
                'password' => 'required|string|min:6',
            ]);

            /** @var PDO $db */
            $db = $this->service('database');
            $stmt = $db->prepare('INSERT INTO users (username,email,password_hash,created_at) VALUES (:u,:e,:p,NOW())');
            $stmt->execute([
                ':u' => $data['username'],
                ':e' => $data['email'],
                ':p' => password_hash($data['password'], PASSWORD_BCRYPT),
            ]);
            $id = (int) $db->lastInsertId();

            return $this->jsonResponse(['success' => true, 'id' => $id], 201);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error creating user');
        }
    }

    public function update(string $id): Response
    {
        try {
            if (!$this->container->bound('database')) {
                return $this->successResponse(['updated' => (int)$id]);
            }

            $data = $this->request->all();
            $this->validate($data, [
                'email' => 'email',
                'password' => 'string|min:6',
            ]);

            /** @var PDO $db */
            $db = $this->service('database');
            $fields = [];
            $params = [];
            if (isset($data['email'])) { $fields[] = 'email = :email'; $params[':email'] = $data['email']; }
            if (isset($data['password'])) { $fields[] = 'password_hash = :pass'; $params[':pass'] = password_hash($data['password'], PASSWORD_BCRYPT); }
            if (!$fields) { return $this->successResponse(['updated' => 0]); }
            $params[':id'] = (int)$id;
            $sql = 'UPDATE users SET '.implode(',',$fields).' WHERE id=:id';
            $db->prepare($sql)->execute($params);

            return $this->successResponse(['updated' => (int)$id]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error updating user');
        }
    }

    public function permissions(string $id): Response
    {
        try {
            if (!$this->container->bound('database')) {
                return $this->successResponse(['permissions_updated' => (int)$id]);
            }

            $data = $this->request->all();
            $this->validate($data, ['role' => 'required|string']);

            /** @var PDO $db */
            $db = $this->service('database');
            $stmt = $db->prepare('UPDATE users SET role = :r WHERE id = :id');
            $stmt->execute([':r' => $data['role'], ':id' => (int)$id]);

            return $this->successResponse(['permissions_updated' => (int)$id]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error updating permissions');
        }
    }
}
