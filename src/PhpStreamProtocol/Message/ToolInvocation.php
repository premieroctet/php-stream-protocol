<?php

namespace PremierOctet\PhpStreamProtocol\Message;

/**
 * ToolInvocation - Represents a tool call and its result
 */
class ToolInvocation
{
    public string $toolCallId;
    public string $toolName;
    public array $args;
    public $result;

    public function __construct(string $toolCallId = '', string $toolName = '', array $args = [], $result = null)
    {
        $this->toolCallId = $toolCallId;
        $this->toolName = $toolName;
        $this->args = $args;
        $this->result = $result;
    }

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['toolCallId'] ?? '',
            $data['toolName'] ?? '',
            $data['args'] ?? [],
            $data['result'] ?? null
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'toolCallId' => $this->toolCallId,
            'toolName' => $this->toolName,
            'args' => $this->args,
            'result' => $this->result,
        ];
    }

    /**
     * Check if tool has been executed (has result)
     */
    public function hasResult(): bool
    {
        return $this->result !== null;
    }
} 