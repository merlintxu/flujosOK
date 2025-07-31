<?php

namespace FlujosDimension\Models;

use FlujosDimension\Core\Container;
use InvalidArgumentException;

class Webhook extends BaseModel
{
    protected string $table = 'webhooks';
    protected array $fillable = ['url', 'event', 'created_at'];
    protected bool $timestamps = false;
    protected array $casts = [
        'id' => 'int',
        'created_at' => 'datetime',
    ];

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    protected function validate(array $data): array
    {
        if (empty($data['url']) || !filter_var($data['url'], FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid URL');
        }
        if (empty($data['event'])) {
            throw new InvalidArgumentException('Event is required');
        }
        return $data;
    }
}
