<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use SoftCreatR\PerplexityAI\PerplexityAI;

class PerplexityController extends Controller
{
    public function __construct(
        protected PerplexityAI $client
    ) {}

    public function ask(string $question)
    {
        //$request->validate(['question' => 'required|string']);

        $response = $this->client->createChatCompletion([], 
            [
                'model' => 'sonar',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Be precise and concise.'
                    ],
                    [
                        'role' => 'user',
                    '   content' => 'How many stars are there in our galaxy?'
                ]
            ],
        ]);

        return response()->json($response);
    }
}
