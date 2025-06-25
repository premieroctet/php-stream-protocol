<?php

namespace App\StreamProtocol;

use App\StreamProtocol\Message\ClientMessage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * StreamProtocol - Main facade class for AI streaming protocols
 * 
 * This class provides a simple, fluent API for creating streaming AI responses
 * that follow the Vercel AI SDK Stream Protocol specifications.
 * 
 * Example usage:
 * 
 * ```php
 * $protocol = new StreamProtocol();
 * $protocol
 *     ->registerTool('get_weather', [WeatherService::class, 'getWeather'])
 *     ->registerTool('search', [SearchService::class, 'search']);
 * 
 * $messages = $protocol->parseMessages($request->getContent());
 * $openaiMessages = $protocol->convertToOpenAI($messages);
 * 
 * return $protocol->stream($openaiStream);
 * ```
 */
class StreamProtocol
{
    private StreamHandler $streamHandler;
    private array $tools = [];
    private string $defaultSystemPrompt = '';

    public function __construct(array $tools = [])
    {
        $this->streamHandler = new StreamHandler($tools);
        $this->tools = $tools;
    }

    /**
     * Create a new StreamProtocol instance
     */
    public static function create(array $tools = []): self
    {
        return new self($tools);
    }

    /**
     * Register a tool that can be called during streaming
     */
    public function registerTool(string $name, callable $callback): self
    {
        $this->tools[$name] = $callback;
        $this->streamHandler->registerTool($name, $callback);
        return $this;
    }

    /**
     * Set default system prompt
     */
    public function withSystemPrompt(string $prompt): self
    {
        $this->defaultSystemPrompt = $prompt;
        return $this;
    }

    /**
     * Parse JSON request data into ClientMessage objects
     * 
     * @param string $jsonData JSON string from request
     * @return ClientMessage[]
     */
    public function parseMessages(string $jsonData): array
    {
        $data = json_decode($jsonData, true);
        
        if (!isset($data['messages']) || !is_array($data['messages'])) {
            throw new \InvalidArgumentException('Invalid message format: missing messages array');
        }

        return MessageConverter::convertToClientMessages($data['messages']);
    }

    /**
     * Convert messages to OpenAI format
     * 
     * @param ClientMessage[] $messages
     * @return array
     */
    public function convertToOpenAI(array $messages, ?string $systemPrompt = null): array
    {
        // Add system prompt if provided or if default is set
        $systemPrompt = $systemPrompt ?? $this->defaultSystemPrompt;
        if (!empty($systemPrompt)) {
            $messages = MessageConverter::addSystemPrompt($messages, $systemPrompt);
        }

        return MessageConverter::convertToOpenAIMessages($messages);
    }

    /**
     * Convert messages to Anthropic format
     * 
     * @param ClientMessage[] $messages
     * @return array
     */
    public function convertToAnthropic(array $messages, ?string $systemPrompt = null): array
    {
        // Add system prompt if provided or if default is set
        $systemPrompt = $systemPrompt ?? $this->defaultSystemPrompt;
        if (!empty($systemPrompt)) {
            $messages = MessageConverter::addSystemPrompt($messages, $systemPrompt);
        }

        return MessageConverter::convertToAnthropicMessages($messages);
    }

    /**
     * Create streaming response from an AI stream
     */
    public function stream(
        iterable $stream, 
        string $protocol = 'data', 
        array $additionalHeaders = []
    ): StreamedResponse {
        return $this->streamHandler->createStreamingResponse($stream, $protocol, $additionalHeaders);
    }

    /**
     * Create a simple text streaming response (for testing/demo)
     */
    public function streamText(string $text, int $delay = 50): StreamedResponse
    {
        return $this->streamHandler->createTextStream($text, $delay);
    }

    /**
     * Complete workflow: parse request, convert messages, and return streaming response
     * 
     * @param string $jsonData Request JSON data
     * @param callable $streamProvider Function that takes messages and returns a stream
     * @param string $protocol Protocol type ('data' or 'text')
     * @param string|null $systemPrompt Optional system prompt
     * @return StreamedResponse
     */
    public function handleRequest(
        string $jsonData,
        callable $streamProvider,
        string $protocol = 'data',
        ?string $systemPrompt = null
    ): StreamedResponse {
        // Parse messages
        $messages = $this->parseMessages($jsonData);
        
        // Convert to OpenAI format with system prompt
        $openaiMessages = $this->convertToOpenAI($messages, $systemPrompt);
        
        // Get stream from provider
        $stream = $streamProvider($openaiMessages);
        
        // Return streaming response
        return $this->stream($stream, $protocol);
    }

    /**
     * Get available tools
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Get tool definitions in OpenAI format
     */
    public function getToolDefinitions(): array
    {
        $definitions = [];
        
        foreach ($this->tools as $name => $callback) {
            // Try to get definition from callback if it's a class method
            if (is_array($callback) && method_exists($callback[0], 'getToolDefinition')) {
                $definitions[] = call_user_func([$callback[0], 'getToolDefinition']);
            } else {
                // Default definition structure
                $definitions[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $name,
                        'description' => "Tool: {$name}",
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [],
                            'required' => []
                        ]
                    ]
                ];
            }
        }
        
        return $definitions;
    }

    /**
     * Build complete OpenAI chat request with tools
     */
    public function buildOpenAIRequest(
        array $messages,
        string $model = 'gpt-4',
        ?string $systemPrompt = null,
        array $additionalOptions = []
    ): array {
        $openaiMessages = $this->convertToOpenAI($messages, $systemPrompt);
        
        $request = array_merge([
            'model' => $model,
            'messages' => $openaiMessages,
            'stream' => true,
        ], $additionalOptions);

        // Add tools if available
        $toolDefinitions = $this->getToolDefinitions();
        if (!empty($toolDefinitions)) {
            $request['tools'] = $toolDefinitions;
        }

        return $request;
    }
} 