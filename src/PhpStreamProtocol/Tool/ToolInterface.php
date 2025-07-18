<?php

declare(strict_types=1);

namespace PremierOctet\PhpStreamProtocol\Tool;

interface ToolInterface
{

  public function getName(): string;
  public function getDescription(): string;
  public function getParameters(): ?array; // JSON Schema format
  public function isStrict(): bool;
  public function execute(array $parameters): mixed;
}
