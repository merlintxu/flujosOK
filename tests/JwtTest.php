<?php
namespace Tests;

use FlujosDimension\Core\JWT;
use FlujosDimension\Core\Config;
use PHPUnit\Framework\TestCase;
use PDO;

class JwtTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $config = Config::getInstance();
        $config->set('JWT_SECRET', 'secret');
        $config->set('JWT_EXPIRATION_HOURS', '1');

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE api_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token_hash TEXT UNIQUE,
                name TEXT,
                expires_at TEXT,
                last_used_at TEXT,
                is_active BOOLEAN DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    public function testGenerateAndValidateToken()
    {
        $jwt = new JWT($this->pdo);
        $token = $jwt->generateToken(['user_id' => 5]);
        $this->assertMatchesRegularExpression('/^[^.]+\.[^.]+\.[^.]+$/', $token);
        $payload = $jwt->validateToken($token);
        $this->assertSame(5, $payload['user_id']);
    }
}
