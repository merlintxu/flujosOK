<?php

namespace FlujosDimension\Core;

class Response
{
    private string $content;
    private int $statusCode;
    private array $headers;
    
    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }
    
    public function send(): void
    {
        http_response_code($this->statusCode);
        
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        
        echo $this->content;
    }
    
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }
    
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }
    
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }
}
