<?php

/**
 * StreamProtocol Package Usage Examples
 * 
 * This file demonstrates various ways to use the StreamProtocol package
 * in your PHP applications.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PremierOctet\PhpStreamProtocol\StreamProtocol;
use PremierOctet\PhpStreamProtocol\Message\ClientMessage;
use PremierOctet\PhpStreamProtocol\MessageConverter;
use Symfony\Component\HttpFoundation\Request;
use OpenAI;

// ============================================================================
// EXAMPLE 1: Basic Usage
// ============================================================================

function basicUsage()
{
    // Create a StreamProtocol instance
    $protocol = StreamProtocol::create()
        ->withSystemPrompt('You are a helpful assistant.')
        ->registerTool('get_weather', function(string $location) {
            return ['weather' => "Sunny, 25°C in {$location}"];
        });

    // Simulate incoming request data
    $requestData = json_encode([
        'messages' => [
            [
                'role' => 'user',
                'content' => 'What is the weather like in Paris?'
            ]
        ]
    ]);

    // Parse messages
    $messages = $protocol->parseMessages($requestData);
    
    // Convert to OpenAI format
    $openaiMessages = $protocol->convertToOpenAI($messages);
    
    print_r($openaiMessages);
}

// ============================================================================
// EXAMPLE 2: Complete Request Handling
// ============================================================================

function completeRequestHandling()
{
    $protocol = StreamProtocol::create()
        ->withSystemPrompt('You are an AI assistant with access to various tools.')
        ->registerTool('search', function(string $query) {
            return ['results' => "Search results for: {$query}"];
        })
        ->registerTool('calculate', function(string $expression) {
            return ['result' => eval("return {$expression};")];
        });

    // Simulate a request
    $request = Request::create('/api/chat', 'POST', [], [], [], [], json_encode([
        'messages' => [
            [
                'role' => 'user',
                'content' => 'Search for "PHP streaming" and calculate 25 * 4'
            ]
        ]
    ]));

    // Handle the complete request
    return $protocol->handleRequest(
        $request->getContent(),
        function(array $openaiMessages) {
            // This would normally call OpenAI API
            // For demo, we'll return a mock stream
            return [
                [
                    'choices' => [
                        [
                            'delta' => ['content' => 'Here are the results...'],
                            'finish_reason' => null
                        ]
                    ]
                ],
                [
                    'choices' => [
                        [
                            'delta' => [],
                            'finish_reason' => 'stop'
                        ]
                    ],
                    'usage' => [
                        'prompt_tokens' => 50,
                        'completion_tokens' => 25
                    ]
                ]
            ];
        }
    );
}

// ============================================================================
// EXAMPLE 3: Message Conversion for Different Providers
// ============================================================================

function multiProviderSupport()
{
    $protocol = StreamProtocol::create();
    
    $messages = [
        new ClientMessage('system', 'You are a helpful assistant.'),
        new ClientMessage('user', 'Hello, how are you?'),
        new ClientMessage('assistant', 'I am doing well, thank you!')
    ];

    // Convert to OpenAI format
    $openaiFormat = MessageConverter::convertToOpenAIMessages($messages);
    echo "OpenAI Format:\n";
    print_r($openaiFormat);

    // Convert to Anthropic format
    $anthropicFormat = MessageConverter::convertToAnthropicMessages($messages);
    echo "\nAnthropic Format:\n";
    print_r($anthropicFormat);
}

// ============================================================================
// EXAMPLE 4: Tool Definition and Execution
// ============================================================================

class WeatherService
{
    public static function getCurrentWeather(string $location, string $unit = 'celsius'): array
    {
        // Mock weather data - in real app, call weather API
        return [
            'location' => $location,
            'temperature' => $unit === 'celsius' ? '25°C' : '77°F',
            'condition' => 'sunny',
            'humidity' => '65%'
        ];
    }

    public static function getToolDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_current_weather',
                'description' => 'Get current weather information for a specific location',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => [
                            'type' => 'string',
                            'description' => 'The city and state, e.g. San Francisco, CA'
                        ],
                        'unit' => [
                            'type' => 'string',
                            'enum' => ['celsius', 'fahrenheit'],
                            'description' => 'Temperature unit'
                        ]
                    ],
                    'required' => ['location']
                ]
            ]
        ];
    }
}

function toolDefinitionExample()
{
    $protocol = StreamProtocol::create()
        ->registerTool('get_current_weather', [WeatherService::class, 'getCurrentWeather']);

    // Get tool definitions
    $definitions = $protocol->getToolDefinitions();
    
    echo "Tool Definitions:\n";
    print_r($definitions);
}

// ============================================================================
// EXAMPLE 5: Handling Attachments
// ============================================================================

function attachmentHandling()
{
    $requestData = json_encode([
        'messages' => [
            [
                'role' => 'user',
                'content' => 'What do you see in this image?',
                'experimental_attachments' => [
                    [
                        'contentType' => 'image/jpeg',
                        'url' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ...'
                    ]
                ]
            ]
        ]
    ]);

    $protocol = StreamProtocol::create();
    $messages = $protocol->parseMessages($requestData);
    $openaiMessages = $protocol->convertToOpenAI($messages);

    echo "Messages with Attachments:\n";
    print_r($openaiMessages);
}

// ============================================================================
// EXAMPLE 6: Symfony Controller Integration
// ============================================================================

/*
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChatController extends AbstractController
{
    private StreamProtocol $protocol;

    public function __construct()
    {
        $this->protocol = StreamProtocol::create()
            ->withSystemPrompt('You are a helpful assistant.')
            ->registerTool('get_weather', [WeatherService::class, 'getCurrentWeather']);
    }

    #[Route('/api/chat', methods: ['POST'])]
    public function chat(Request $request): Response
    {
        return $this->protocol->handleRequest(
            $request->getContent(),
            function(array $messages) {
                $client = OpenAI::client($_ENV['OPENAI_API_KEY']);
                return $client->chat()->createStreamed([
                    'model' => 'gpt-4',
                    'messages' => $messages,
                    'stream' => true,
                    'tools' => $this->protocol->getToolDefinitions()
                ]);
            }
        );
    }
}
*/

// ============================================================================
// Run Examples
// ============================================================================

if (php_sapi_name() === 'cli') {
    echo "=== StreamProtocol Package Examples ===\n\n";
    
    echo "1. Basic Usage:\n";
    basicUsage();
    
    echo "\n2. Multi-Provider Support:\n";
    multiProviderSupport();
    
    echo "\n3. Tool Definitions:\n";
    toolDefinitionExample();
    
    echo "\n4. Attachment Handling:\n";
    attachmentHandling();
    
    echo "\n=== Examples Complete ===\n";
} 