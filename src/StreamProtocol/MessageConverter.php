<?php

namespace App\StreamProtocol;

use App\StreamProtocol\Message\ClientMessage;

/**
 * MessageConverter - Converts messages between different formats
 * 
 * This class handles the conversion between client messages and various 
 * AI provider message formats (OpenAI, Anthropic, etc.)
 */
class MessageConverter
{
    /**
     * Convert client messages to OpenAI format
     * 
     * @param ClientMessage[] $messages
     * @return array
     */
    public static function convertToOpenAIMessages(array $messages): array
    {
        $openaiMessages = [];

        foreach ($messages as $message) {
            $content = '';
            $hasParts = isset($message->parts) && is_array($message->parts);
            
            // Extract content from parts or use direct content
            if ($hasParts) {
                foreach ($message->parts as $part) {
                    if ($part['type'] === 'text') {
                        $content .= $part['text'];
                    }
                }
            } else {
                $content = $message->content;
            }

            // Handle attachments
            $parts = [];
            if (!empty($message->experimental_attachments)) {
                foreach ($message->experimental_attachments as $attachment) {
                    if ($attachment->isImage()) {
                        $parts[] = [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $attachment->url,
                            ],
                        ];
                    } elseif ($attachment->isText()) {
                        $content .= "\n" . $attachment->url;
                    }
                }
            }

            // Handle tool invocations
            if (!empty($message->toolInvocations)) {
                $toolCalls = [];
                foreach ($message->toolInvocations as $toolInvocation) {
                    $toolCalls[] = [
                        'id' => $toolInvocation->toolCallId,
                        'type' => 'function',
                        'function' => [
                            'name' => $toolInvocation->toolName,
                            'arguments' => json_encode($toolInvocation->args),
                        ],
                    ];
                }
                
                // Add assistant message with tool calls
                $openaiMessages[] = [
                    'role' => 'assistant',
                    'tool_calls' => $toolCalls,
                ];

                // Add tool results
                foreach ($message->toolInvocations as $toolInvocation) {
                    if ($toolInvocation->hasResult()) {
                        $openaiMessages[] = [
                            'role' => 'tool',
                            'content' => json_encode($toolInvocation->result),
                            'tool_call_id' => $toolInvocation->toolCallId,
                        ];
                    }
                }
                continue;
            }

            // Add regular message
            if (!empty($content)) {
                $messageData = [
                    'role' => $message->role,
                    'content' => $content,
                ];

                // Add image parts if any
                if (!empty($parts)) {
                    $messageData['content'] = [
                        ['type' => 'text', 'text' => $content],
                        ...$parts
                    ];
                }

                $openaiMessages[] = $messageData;
            }
        }
        
        return $openaiMessages;
    }

    /**
     * Convert array of message data to ClientMessage objects
     * 
     * @param array $messagesData
     * @return ClientMessage[]
     */
    public static function convertToClientMessages(array $messagesData): array
    {
        $messages = [];
        
        foreach ($messagesData as $messageData) {
            $messages[] = ClientMessage::fromArray($messageData);
        }
        
        return $messages;
    }

    /**
     * Add system message to beginning of message array
     */
    public static function addSystemPrompt(array $messages, string $systemPrompt): array
    {
        $systemMessage = new ClientMessage('system', $systemPrompt);
        return array_merge([$systemMessage], $messages);
    }

    /**
     * Convert to Anthropic Claude format
     * 
     * @param ClientMessage[] $messages
     * @return array
     */
    public static function convertToAnthropicMessages(array $messages): array
    {
        $anthropicMessages = [];
        $systemPrompt = '';

        foreach ($messages as $message) {
            // Extract system messages separately for Anthropic
            if ($message->role === 'system') {
                $systemPrompt .= $message->content . "\n";
                continue;
            }

            $content = $message->content;
            
            // Handle parts
            if (isset($message->parts) && is_array($message->parts)) {
                $content = [];
                foreach ($message->parts as $part) {
                    if ($part['type'] === 'text') {
                        $content[] = [
                            'type' => 'text',
                            'text' => $part['text']
                        ];
                    }
                }
            }

            // Handle attachments
            if (!empty($message->experimental_attachments)) {
                if (is_string($content)) {
                    $content = [['type' => 'text', 'text' => $content]];
                }
                
                foreach ($message->experimental_attachments as $attachment) {
                    if ($attachment->isImage()) {
                        $content[] = [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $attachment->contentType,
                                'data' => base64_encode(file_get_contents($attachment->url))
                            ]
                        ];
                    }
                }
            }

            $anthropicMessages[] = [
                'role' => $message->role === 'assistant' ? 'assistant' : 'user',
                'content' => $content
            ];
        }

        return [
            'system' => trim($systemPrompt),
            'messages' => $anthropicMessages
        ];
    }
} 