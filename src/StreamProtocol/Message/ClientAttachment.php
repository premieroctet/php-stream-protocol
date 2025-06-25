<?php

namespace PremierOctet\StreamProtocol\Message;

/**
 * ClientAttachment - Represents a file attachment in a message
 */
class ClientAttachment
{
    public string $contentType;
    public string $url;

    public function __construct(string $contentType = '', string $url = '')
    {
        $this->contentType = $contentType;
        $this->url = $url;
    }

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['contentType'] ?? '',
            $data['url'] ?? ''
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'contentType' => $this->contentType,
            'url' => $this->url,
        ];
    }

    /**
     * Check if attachment is an image
     */
    public function isImage(): bool
    {
        return strpos($this->contentType, 'image') === 0;
    }

    /**
     * Check if attachment is text
     */
    public function isText(): bool
    {
        return strpos($this->contentType, 'text') === 0;
    }
} 