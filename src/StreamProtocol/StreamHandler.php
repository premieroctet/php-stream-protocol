<?php

namespace App\StreamProtocol;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * StreamHandler - A reusable class for handling AI streaming protocols
 * 
 * This class provides an easy-to-use interface for streaming AI responses
 * following the Vercel AI SDK Stream Protocol specifications.
 */
class StreamHandler
{
    private array $availableTools = [];
    private array $defaultHeaders = [
        'Content-Type' => 'text/plain',
        'Cache-Control' => 'no-cache',
        'X-Accel-Buffering' => 'no',
        'x-vercel-ai-data-stream' => 'v1',
        'X-Debug-Token-Link' => '', // Disable web profiler
        'X-Debug-Token' => ''       // Disable web profiler
    ];

    public function __construct(array $tools = [])
    {
        $this->availableTools = $tools;
    }

    /**
     * Register a tool that can be called during streaming
     */
    public function registerTool(string $name, callable $callback): self
    {
        $this->availableTools[$name] = $callback;
        return $this;
    }

    /**
     * Create a streaming response
     */
    public function createStreamingResponse(
        iterable $stream,
        string $protocol = 'data',
        array $additionalHeaders = []
    ): StreamedResponse {
        $response = new StreamedResponse(function () use ($stream, $protocol) {
            // Prevent any error handling or profiling from interfering
            $this->disableAllInterference();
            $this->handleStream($stream, $protocol);
        });

        // Set headers
        foreach (array_merge($this->defaultHeaders, $additionalHeaders) as $key => $value) {
            $response->headers->set($key, $value);
        }

        // Prevent Symfony from adding any additional headers
        $response->headers->set('X-Debug-Token-Link', '');
        $response->headers->set('X-Debug-Token', '');

        return $response;
    }

    /**
     * Handle the actual streaming process
     */
    private function handleStream(iterable $stream, string $protocol): void
    {
        $messageId = uniqid('msg-');
        $this->emitMessageStart($messageId);

        $draftToolCalls = [];
        $draftToolCallsIndex = -1;
        $lastChunk = null;

        foreach ($stream as $chunk) {
            // Convert OpenAI response object to array if needed
            if (is_object($chunk)) {
                if (method_exists($chunk, 'toArray')) {
                    $chunkArray = $chunk->toArray();
                } elseif (method_exists($chunk, '__toArray')) {
                    $chunkArray = $chunk->__toArray();
                } else {
                    // Fallback: convert object to array
                    $chunkArray = json_decode(json_encode($chunk), true);
                }
            } else {
                $chunkArray = $chunk;
            }
            
            $lastChunk = $chunkArray;
            $this->processChunk($chunkArray, $protocol, $draftToolCalls, $draftToolCallsIndex);
        }

        $this->emitFinish($lastChunk, $draftToolCalls);
    }

    /**
     * Disable all potential interference from Symfony components
     */
    private function disableAllInterference(): void
    {
        // Disable error reporting to prevent error pages from interfering
        $originalErrorReporting = error_reporting(0);
        
        // Set a custom error handler that doesn't output anything
        set_error_handler(function($severity, $message, $file, $line) {
            // Log errors silently if needed, but don't output anything
            return true;
        });
        
        // Clean all output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Turn off output buffering completely
        ini_set('output_buffering', 'off');
        ini_set('zlib.output_compression', 'off');
        
        // Enable implicit flush
        ob_implicit_flush(true);
        
        // Ignore user abort for streaming
        ignore_user_abort(true);
        
        // Disable any potential interference from Apache/Nginx
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }
    }

    /**
     * Emit message start
     */
    private function emitMessageStart(string $messageId): void
    {
        $this->emit('f', ['messageId' => $messageId]);
    }

    /**
     * Process individual chunk
     */
    private function processChunk(
        array $chunk, 
        string $protocol, 
        array &$draftToolCalls, 
        int &$draftToolCallsIndex
    ): void {
        // Safety check for chunk structure
        if (!isset($chunk['choices']) || !is_array($chunk['choices']) || empty($chunk['choices'])) {
            return;
        }

        $choice = $chunk['choices'][0];
        $delta = $choice['delta'] ?? [];

        if (($choice['finish_reason'] ?? null) === 'tool_calls') {
            $this->handleToolCalls($draftToolCalls);
        } elseif (isset($delta['tool_calls'])) {
            $this->handleToolCallStreaming($delta['tool_calls'], $draftToolCalls, $draftToolCallsIndex);
        } else {
            $content = $delta['content'] ?? '';
            if ($protocol === 'text') {
                echo $content;
            } else {
                $this->emit('0', $content);
            }
        }
    }

    /**
     * Handle completed tool calls
     */
    private function handleToolCalls(array $draftToolCalls): void
    {
        foreach ($draftToolCalls as $toolCall) {
            // Emit tool call
            $this->emit('9', [
                'toolCallId' => $toolCall['id'],
                'toolName' => $toolCall['name'],
                'args' => json_decode($toolCall['arguments'], true)
            ]);

            // Execute tool and emit result
            if (isset($this->availableTools[$toolCall['name']])) {
                $args = json_decode($toolCall['arguments'], true);
                $result = call_user_func_array($this->availableTools[$toolCall['name']], $args);

                $this->emit('a', [
                    'toolCallId' => $toolCall['id'],
                    'result' => $result
                ]);
            }
        }
    }

    /**
     * Handle streaming tool calls
     */
    private function handleToolCallStreaming(
        array $toolCalls, 
        array &$draftToolCalls, 
        int &$draftToolCallsIndex
    ): void {
        foreach ($toolCalls as $toolCall) {
            $id = $toolCall['id'] ?? null;
            $function = $toolCall['function'] ?? [];
            $name = $function['name'] ?? '';
            $arguments = $function['arguments'] ?? '';

            if ($id !== null) {
                $draftToolCallsIndex++;
                
                // Emit tool call start
                $this->emit('b', [
                    'toolCallId' => $id,
                    'toolName' => $name
                ]);
                
                $draftToolCalls[] = [
                    'id' => $id,
                    'name' => $name,
                    'arguments' => ''
                ];
            } else {
                // Emit tool call delta
                $this->emit('c', [
                    'toolCallId' => $draftToolCalls[$draftToolCallsIndex]['id'],
                    'argsTextDelta' => $arguments
                ]);
                
                $draftToolCalls[$draftToolCallsIndex]['arguments'] .= $arguments;
            }
        }
    }

    /**
     * Emit finish events
     */
    private function emitFinish(?array $lastChunk, array $draftToolCalls): void
    {
        if (!$lastChunk || !isset($lastChunk['choices']) || empty($lastChunk['choices'])) {
            // Emit default finish events even if no valid chunk
            $this->emit('e', [
                'finishReason' => 'stop',
                'usage' => ['promptTokens' => 0, 'completionTokens' => 0],
                'isContinued' => false
            ]);
            $this->emit('d', [
                'finishReason' => 'stop',
                'usage' => ['promptTokens' => 0, 'completionTokens' => 0]
            ]);
            return;
        }

        $finishReason = $lastChunk['choices'][0]['finish_reason'] ?? 'stop';
        $usage = $lastChunk['usage'] ?? [
            'prompt_tokens' => 0,
            'completion_tokens' => 0
        ];

        $finalFinishReason = count($draftToolCalls) > 0 ? 'tool_calls' : $finishReason;
        $usageData = [
            'promptTokens' => $usage['prompt_tokens'] ?? 0,
            'completionTokens' => $usage['completion_tokens'] ?? 0
        ];

        // Emit finish step
        $this->emit('e', [
            'finishReason' => $finalFinishReason,
            'usage' => $usageData,
            'isContinued' => false
        ]);

        // Emit finish message
        $this->emit('d', [
            'finishReason' => $finalFinishReason,
            'usage' => $usageData
        ]);
    }

    /**
     * Emit a protocol message
     */
    private function emit(string $type, $data): void
    {
        echo $type . ':' . json_encode($data) . "\n";
        flush();
    }

    /**
     * Create a simple text streaming response
     */
    public function createTextStream(string $text, int $delay = 50): StreamedResponse
    {
        return $this->createStreamingResponse(
            $this->generateTextChunks($text, $delay),
            'text'
        );
    }

    /**
     * Generate text chunks for simple streaming
     */
    private function generateTextChunks(string $text, int $delay): \Generator
    {
        $words = explode(' ', $text);
        foreach ($words as $word) {
            yield [
                'choices' => [
                    [
                        'delta' => ['content' => $word . ' '],
                        'finish_reason' => null
                    ]
                ]
            ];
            usleep($delay * 1000);
        }
        
        // Final chunk
        yield [
            'choices' => [
                [
                    'delta' => [],
                    'finish_reason' => 'stop'
                ]
            ],
            'usage' => [
                'prompt_tokens' => 0,
                'completion_tokens' => count($words)
            ]
        ];
    }
} 