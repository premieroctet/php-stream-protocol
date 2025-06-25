<?php

namespace PremierOctet\PhpStreamProtocol\Message;

/**
 * ClientMessage - Represents a message from the client
 */
class ClientMessage
{
    public string $role;
    public string $content;
    /** @var ClientAttachment[]|null */
    public ?array $experimental_attachments = null;
    /** @var ToolInvocation[]|null */
    public ?array $toolInvocations = null;
    public ?array $parts = null;

    public function __construct(
        string $role = '',
        string $content = '',
        ?array $experimental_attachments = null,
        ?array $toolInvocations = null,
        ?array $parts = null
    ) {
        $this->role = $role;
        $this->content = $content;
        $this->experimental_attachments = $experimental_attachments;
        $this->toolInvocations = $toolInvocations;
        $this->parts = $parts;
    }

    /**
     * Create a ClientMessage from array data
     */
    public static function fromArray(array $data): self
    {
        $message = new self();
        $message->role = $data['role'] ?? '';
        $message->content = $data['content'] ?? '';
        
        // Handle message parts if they exist
        if (isset($data['parts'])) {
            $message->parts = $data['parts'];
        }

        // Handle attachments
        if (!empty($data['experimental_attachments'])) {
            $message->experimental_attachments = [];
            foreach ($data['experimental_attachments'] as $att) {
                $attachment = ClientAttachment::fromArray($att);
                $message->experimental_attachments[] = $attachment;
            }
        }

        // Handle tool invocations
        if (!empty($data['toolInvocations'])) {
            $message->toolInvocations = [];
            foreach ($data['toolInvocations'] as $ti) {
                $tool = ToolInvocation::fromArray($ti);
                $message->toolInvocations[] = $tool;
            }
        }

        return $message;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        $data = [
            'role' => $this->role,
            'content' => $this->content,
        ];

        if ($this->parts !== null) {
            $data['parts'] = $this->parts;
        }

        if ($this->experimental_attachments !== null) {
            $data['experimental_attachments'] = array_map(
                fn($att) => $att->toArray(),
                $this->experimental_attachments
            );
        }

        if ($this->toolInvocations !== null) {
            $data['toolInvocations'] = array_map(
                fn($tool) => $tool->toArray(),
                $this->toolInvocations
            );
        }

        return $data;
    }
} 