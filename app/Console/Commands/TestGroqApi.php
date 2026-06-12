<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestGroqApi extends Command
{
    protected $signature = 'test:groq';
    protected $description = 'Test Groq API connection and response';

    public function handle()
    {
        $this->info('Testing Groq API...');
        $this->newLine();

        // 1. Check API Key
        $apiKey = config('services.groq.api_key') ?? env('GROQ_API_KEY');
        
        if (empty($apiKey)) {
            $this->error('❌ GROQ_API_KEY is empty!');
            $this->info('Check your .env file');
            return 1;
        }
        
        $this->info('✅ API Key found: ' . substr($apiKey, 0, 20) . '...');
        $this->newLine();

        // 2. Test API Call
        $this->info('Testing API call...');
        
        $testMessage = 'شنو المنتجات الي عندк؟';
        
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(15)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant for a store in Iraq. Answer in Iraqi Arabic.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $testMessage
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 200,
            ]);

            if ($response->successful()) {
                $this->info('✅ API call successful!');
                $this->newLine();
                
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                
                $this->info('Response:');
                $this->line($content);
                $this->newLine();
                
                $this->info('✅ Groq API is working correctly!');
                return 0;
            } else {
                $this->error('❌ API call failed!');
                $this->error('Status: ' . $response->status());
                $this->error('Body: ' . $response->body());
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Exception occurred!');
            $this->error($e->getMessage());
            return 1;
        }
    }
}
