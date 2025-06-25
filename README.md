# PHP Stream Protocol ▲

A PHP library for handling the [Vercel AI SDK Stream Protocol](https://ai-sdk.dev/docs/ai-sdk-ui/stream-protocol#data-stream-protocol). This package provides an easy-to-use interface for creating streaming AI responses with support for tool calls, attachments, and various AI providers.

## Features

- 🚀 **Easy Integration**: Simple, fluent API for streaming responses
- 🔧 **Tool Support**: Built-in tool calling and execution
- 📎 **Attachments**: Handle file and image attachments
- 🔄 **Multi-Provider**: Support for OpenAI, Anthropic Claude, and more
- 📊 **Protocol Compliant**: Follows Vercel AI SDK Stream Protocol specifications
- ⚡ **Symfony Integration**: Built with Symfony components

## Installation

```bash
composer require premieroctet/php-stream-protocol
```

## Quick Start

### Basic Usage

```php
use PremierOctet\PhpStreamProtocol\StreamProtocol;
use OpenAI;

// Create a new StreamProtocol instance
$protocol = StreamProtocol::create()
    ->withSystemPrompt('You are a helpful assistant.');

// Register tools
$protocol->registerTool('get_weather', [WeatherService::class, 'getCurrentWeather']);

// In your controller
public function chat(Request $request): Response
{
    // Parse incoming messages
    $messages = $protocol->parseMessages($request->getContent());

    // Convert to OpenAI format and create request
    $openaiRequest = $protocol->buildOpenAIRequest($messages, 'gpt-4');

    // Create OpenAI stream
    $client = OpenAI::client($apiKey);
    $stream = $client->chat()->createStreamed($openaiRequest);

    // Return streaming response
    return $protocol->stream($stream);
}
```

### Symfony Usage

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use PremierOctet\PhpStreamProtocol\StreamProtocol;
use App\Tools\WeatherTool;
use OpenAI;


class ChatController extends AbstractController
{
    private StreamProtocol $streamProtocol;

    public function __construct()
    {
        $this->streamProtocol = StreamProtocol::create()
            ->withSystemPrompt('You are a demo assistant showcasing the integration of Vercel AI SDK with a Symfony controller.')
            ->registerTool('get_current_weather', [WeatherTool::class, 'getCurrentWeather']);
    }

    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(Request $request): Response
    {
        return $this->streamProtocol->handleRequest(
            $request->getContent(),
            function(array $openaiMessages) {
                $client = OpenAI::client($_ENV['OPENAI_API_KEY']);
                return $client->chat()->createStreamed([
                    'model' => 'gpt-4',
                    'messages' => $openaiMessages,
                    'stream' => true,
                    'tools' => [WeatherTool::getToolDefinition()],
                ]);
            },
        );
    }

    #[Route('/chat', name: 'chat')]
    public function chatPage(): Response
    {
        return $this->render('chat.html.twig');
    }
}

```

### Advanced Usage with Custom Tools

```php
use PremierOctet\PhpStreamProtocol\StreamProtocol;

class ChatController
{
    public function chat(Request $request): Response
    {
        $protocol = StreamProtocol::create()
            ->withSystemPrompt('You are a helpful assistant with access to various tools.')
            ->registerTool('search_web', [$this, 'searchWeb'])
            ->registerTool('get_weather', [$this, 'getWeather'])
            ->registerTool('send_email', [$this, 'sendEmail']);

        return $protocol->handleRequest(
            $request->getContent(),
            function($messages) use ($request) {
                $client = OpenAI::client(env('OPENAI_API_KEY'));
                return $client->chat()->createStreamed([
                    'model' => 'gpt-4',
                    'messages' => $messages,
                    'stream' => true,
                    'tools' => $protocol->getToolDefinitions(),
                ]);
            }
        );
    }

    private function searchWeb(string $query): array
    {
        // Your web search implementation
        return ['results' => "Search results for: {$query}"];
    }

    private function getWeather(string $location): array
    {
        // Your weather service implementation
        return ['weather' => "Weather in {$location}: Sunny, 25°C"];
    }
}
```

### Message Conversion

```php
// Convert messages to different AI provider formats
$messages = $protocol->parseMessages($jsonData);

// For OpenAI
$openaiMessages = $protocol->convertToOpenAI($messages);

// For Anthropic Claude
$anthropicData = $protocol->convertToAnthropic($messages);
```

### Simple Text Streaming (for testing)

```php
public function demo(): Response
{
    $protocol = StreamProtocol::create();

    return $protocol->streamText(
        'This is a demo of streaming text word by word.',
        50 // delay in milliseconds
    );
}
```

## Message Format

The library handles messages in the Vercel AI SDK format, supporting:

- **Text messages**: Simple text content
- **Tool calls**: Function calling with arguments and results
- **Attachments**: File and image attachments
- **Message parts**: Complex message structures

Example message structure:

```json
{
  "messages": [
    {
      "role": "user",
      "content": "What's the weather like?",
      "experimental_attachments": [
        {
          "contentType": "image/jpeg",
          "url": "data:image/jpeg;base64,..."
        }
      ]
    },
    {
      "role": "assistant",
      "content": "",
      "toolInvocations": [
        {
          "toolCallId": "call_123",
          "toolName": "get_weather",
          "args": { "location": "New York" },
          "result": { "temperature": "25°C", "condition": "sunny" }
        }
      ]
    }
  ]
}
```

## Stream Protocol

The library implements the Vercel AI SDK Stream Protocol with the following message types:

- `0:` - Text content
- `9:` - Tool call
- `a:` - Tool result
- `b:` - Tool call streaming start
- `c:` - Tool call delta
- `d:` - Finish message
- `e:` - Finish step
- `f:` - Message start

## Tool Integration

Tools must follow this interface:

```php
class WeatherTool
{
    public static function getCurrentWeather(string $location): array
    {
        // Your implementation
        return [
            'location' => $location,
            'temperature' => '25°C',
            'condition' => 'sunny'
        ];
    }

    public static function getToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_current_weather',
                'description' => 'Get current weather for a location',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => [
                            'type' => 'string',
                            'description' => 'The city and state, e.g. San Francisco, CA'
                        ]
                    ],
                    'required' => ['location']
                ]
            ]
        ];
    }
}
```

## Requirements

- PHP 8.1 or higher
- Symfony HttpFoundation component

## License

MIT License - see LICENSE file for details.

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request
