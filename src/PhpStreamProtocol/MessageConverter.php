<?php

namespace PremierOctet\PhpStreamProtocol;

use PremierOctet\PhpStreamProtocol\Message\ClientMessage;

/**
 * MessageConverter - Converts messages between different formats
 * 
 * This class handles the conversion between client messages and various 
 * AI provider message formats (OpenAI, etc.)
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
            $messageData = [];
            $messageData['role'] = $message->role;
            $messageData['content'] = [];

            if ($message->parts && is_array($message->parts)) {
                foreach ($message->parts as $part) {
                    if ($part['type'] === 'text') {
                        $messageData['content'][] = [
                            'type' => 'text',
                            'text' => $part['text'],
                        ];
                    } elseif ($part['type'] === 'file') {
                        $messageData['content'][] = [
                            'type' => 'text',
                            'text' => json_encode(['file' => [
                                'name' => $part['name'],
                                'mime_type' => $part['mimeType'],
                                'url' => $part['data'],
                            ]]),
                        ];
                    }
                }
            }

            if (!empty($message->experimental_attachments)) {
                foreach ($message->experimental_attachments as $attachment) {
                    if ($attachment->isImage()) {
                        $messageData['content'][] = [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $attachment->url,
                            ],
                        ];
                    } elseif ($attachment->isText()) {
                        $messageData['content'][] = [
                            'type' => 'file',
                            'file' => [
                                'file_data' => $attachment->url,
                                'filename' => $attachment->url,
                            ],
                        ];
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
            }

            $openaiMessages[] = $messageData;
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
}
