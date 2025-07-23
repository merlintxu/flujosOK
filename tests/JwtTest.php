<?php
namespace Tests;

use FlujosDimension\Core\JWT;
use FlujosDimension\Core\Database;
use FlujosDimension\Core\Config;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TokenDbStub {
    public int $inserts = 0;
    public int $selects = 0;
    public int $updates = 0;
    public function insert($q,$p=[]) { $this->inserts++; }
    public function selectOne($q,$p=[]) { $this->selects++; return ['id'=>1]; }
    public function update($q,$p=[]) { $this->updates++; return 1; }
    public function delete($q,$p=[]) { return 0; }
    public function select($q,$p=[]) { return []; }
}

class JwtTest extends TestCase
{
    protected function setUp(): void
    {
        $config = Config::getInstance();
        $config->set('JWT_SECRET', 'secret');
        $config->set('JWT_EXPIRATION_HOURS', '1');
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, new TokenDbStub());
    }

    public function testGenerateAndValidateToken()
    {
        $jwt = new JWT();
        $token = $jwt->generateToken(['user_id' => 5]);
        $this->assertMatchesRegularExpression('/^[^.]+\.[^.]+\.[^.]+$/', $token);
        $payload = $jwt->validateToken($token);
        $this->assertSame(5, $payload['user_id']);
    }
}
