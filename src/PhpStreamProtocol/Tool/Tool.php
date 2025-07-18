<?php

declare(strict_types=1);

namespace PremierOctet\PhpStreamProtocol\Tool;

class Tool implements ToolInterface
{
  private $callback;

  public function __construct(
    public string $name,
    public string $description,
    callable $callback,
    public ?array $parameters = null,
    public bool $strict = true,
  ) {
    $this->callback = $callback;
  }

  public function execute(array $parameters): mixed
  {
    if ($this->callback === null) {
      throw new \RuntimeException("No callback provided for tool '{$this->name}'");
    }

    return call_user_func($this->callback, $parameters);
  }

  public function getName(): string
  {
    return $this->name;
  }

  public function getDescription(): string
  {
    return $this->description;
  }

  public function getParameters(): ?array
  {
    return $this->parameters;
  }

  public function isStrict(): bool
  {
    return $this->strict;
  }
}
