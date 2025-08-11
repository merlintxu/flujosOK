<?php
namespace FlujosDimension\Jobs;

/** Basic job contract for queue worker. */
interface JobInterface
{
    /**
     * Handle the job logic.
     * @param array<string,mixed> $payload
     */
    public function handle(array $payload): void;
}
