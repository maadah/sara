<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\AiChatTestController;
use Illuminate\Http\Request;

/**
 * AI Chat Test Command
 *
 * Test the AI chat system from terminal without Facebook Messenger.
 * Run with: php artisan ai:test
 */
class AiChatTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:test
                            {--user=1 : User ID to test with}
                            {--scenario= : Run specific scenario}
                            {--all : Run all test scenarios}
                            {--chat : Interactive chat mode}
                            {--list : List all available scenarios}';

    /**
     * The console command description.
     */
    protected $description = 'Test AI chat system - run scenarios or interactive chat';

    /**
     * Test controller
     */
    protected AiChatTestController $controller;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->controller = new AiChatTestController();
        $userId = (int) $this->option('user');

        if ($this->option('list')) {
            return $this->listScenarios();
        }

        if ($this->option('all')) {
            return $this->runAllScenarios($userId);
        }

        if ($this->option('scenario')) {
            return $this->runScenario($userId, $this->option('scenario'));
        }

        if ($this->option('chat')) {
            return $this->interactiveChat($userId);
        }

        // Default: show help
        $this->showHelp();
        return 0;
    }

    /**
     * List all available scenarios
     */
    protected function listScenarios(): int
    {
        $this->info("\n📋 Available Test Scenarios\n");

        $request = new Request();
        $response = $this->controller->listScenarios();
        $data = json_decode($response->getContent(), true);

        $scenarios = $data['scenarios'] ?? [];

        $tableData = [];
        foreach ($scenarios as $name => $info) {
            $tableData[] = [$name, $info['description'], $info['steps']];
        }

        $this->table(['Name', 'Description', 'Steps'], $tableData);

        $this->info("\n💡 Usage: php artisan ai:test --scenario=<name>");
        $this->info("         php artisan ai:test --all");

        return 0;
    }

    /**
     * Run all test scenarios
     */
    protected function runAllScenarios(int $userId): int
    {
        $this->info("\n🧪 Running ALL Test Scenarios\n");
        $this->info("User ID: {$userId}");
        $this->newLine();

        $request = new Request(['user_id' => $userId]);
        $response = $this->controller->fullTest($request);
        $data = json_decode($response->getContent(), true);

        $summary = $data['summary'];

        // Summary
        $this->newLine();
        $this->info("════════════════════════════════════════════════════════════");
        $this->info("                    📊 TEST SUMMARY                          ");
        $this->info("════════════════════════════════════════════════════════════");
        $this->newLine();

        $statusIcon = $summary['failed'] === 0 ? '✅' : '⚠️';
        $this->info("  Total Scenarios:  {$summary['total_scenarios']}");
        $this->info("  {$statusIcon} Passed:         {$summary['passed']}");

        if ($summary['failed'] > 0) {
            $this->error("  ❌ Failed:         {$summary['failed']}");
        } else {
            $this->info("  ❌ Failed:         {$summary['failed']}");
        }

        $this->info("  Success Rate:     {$summary['success_rate']}");
        $this->newLine();

        // Results table
        $tableData = [];
        foreach ($data['results'] as $name => $result) {
            $status = $result['success'] ? '✅ PASS' : '❌ FAIL';
            $issues = $result['success'] ? '' : count($result['issues']) . ' issues';
            $tableData[] = [$name, $status, $result['step_count'], $issues];
        }

        $this->table(['Scenario', 'Status', 'Steps', 'Issues'], $tableData);

        // Show all issues
        if (!empty($data['all_issues'])) {
            $this->newLine();
            $this->error("📝 Issues Found:");
            foreach ($data['all_issues'] as $issue) {
                $this->warn("  • {$issue}");
            }
        }

        $this->newLine();
        return $summary['failed'] === 0 ? 0 : 1;
    }

    /**
     * Run specific scenario
     */
    protected function runScenario(int $userId, string $scenario): int
    {
        $this->info("\n🎬 Running Scenario: {$scenario}\n");

        $request = new Request(['user_id' => $userId, 'scenario' => $scenario]);
        $response = $this->controller->runScenario($request);

        $statusCode = $response->getStatusCode();
        $data = json_decode($response->getContent(), true);

        if ($statusCode === 400) {
            $this->error("Error: " . ($data['error'] ?? 'Unknown error'));
            if ($available = $data['available_scenarios'] ?? null) {
                $this->info("\nAvailable scenarios:");
                foreach ($available as $name) {
                    $this->line("  • {$name}");
                }
            }
            return 1;
        }

        $this->info("Description: {$data['description']}");
        $this->newLine();

        foreach ($data['steps'] as $step) {
            $stepNum = str_pad($step['step'], 2, ' ', STR_PAD_LEFT);
            $icon = $step['passed'] ? '✅' : '❌';

            $this->info("─────────────────────────────────────────────");
            $this->info("{$icon} Step {$stepNum}");
            $this->info("─────────────────────────────────────────────");

            $this->warn("📤 Message: " . $step['message']);

            if (isset($step['error'])) {
                $this->error("Error: " . $step['error']);
            } else {
                $response = mb_substr($step['response'] ?? 'No response', 0, 200);
                $this->info("📥 Response: {$response}");

                if (($step['cart_items'] ?? 0) > 0) {
                    $this->info("🛒 Cart: {$step['cart_items']} items ({$step['cart_total']} total)");
                }

                if (!empty($step['customer_data'])) {
                    $this->info("👤 Customer: " . json_encode($step['customer_data'], JSON_UNESCAPED_UNICODE));
                }

                $this->line("⏱️ Duration: " . ($step['duration_ms'] ?? 0) . "ms");

                if (!empty($step['issues'])) {
                    foreach ($step['issues'] as $issue) {
                        $this->error("  ⚠️ {$issue}");
                    }
                }
            }
            $this->newLine();
        }

        // Final state
        $final = $data['final_state'];
        $this->info("═══════════════════════════════════════════════════════════");
        $this->info("                    📋 FINAL STATE                          ");
        $this->info("═══════════════════════════════════════════════════════════");

        $this->info("🛒 Cart Total: {$final['cart_total']}");
        $this->info("📦 Cart Items: " . count($final['cart'] ?? []));

        if (!empty($final['customer_data'])) {
            $this->info("👤 Customer: " . json_encode($final['customer_data'], JSON_UNESCAPED_UNICODE));
        }

        if ($final['order'] ?? null) {
            $this->info("📋 Order Created: #{$final['order']['order_number']}");
        }

        $this->newLine();

        $status = $data['success'] ? '✅ SCENARIO PASSED' : '❌ SCENARIO FAILED';
        $this->info($status);

        if (!empty($data['issues'])) {
            $this->error("\nIssues:");
            foreach ($data['issues'] as $issue) {
                $this->warn("  • {$issue}");
            }
        }

        $this->newLine();
        return $data['success'] ? 0 : 1;
    }

    /**
     * Interactive chat mode
     */
    protected function interactiveChat(int $userId): int
    {
        $this->info("\n💬 Interactive AI Chat Test");
        $this->info("═══════════════════════════════════════════════════════════");
        $this->info("User ID: {$userId}");
        $this->info("Type 'exit' to quit, 'reset' to clear session");
        $this->info("═══════════════════════════════════════════════════════════\n");

        $sessionId = null;

        while (true) {
            $message = $this->ask("\n📤 You");

            if (strtolower(trim($message ?? '')) === 'exit') {
                $this->info("\n👋 Goodbye!\n");
                break;
            }

            if (strtolower(trim($message ?? '')) === 'reset') {
                $sessionId = null;
                $this->info("🔄 Session reset. Starting fresh.");
                continue;
            }

            if (empty(trim($message ?? ''))) {
                continue;
            }

            $request = new Request([
                'user_id' => $userId,
                'message' => $message,
                'session_id' => $sessionId,
            ]);

            $response = $this->controller->chat($request);
            $data = json_decode($response->getContent(), true);

            if ($response->getStatusCode() !== 200) {
                $this->error("Error: " . ($data['error'] ?? 'Unknown error'));
                continue;
            }

            $sessionId = $data['session_id'] ?? $sessionId;

            // Display response
            $this->newLine();
            $this->info("📥 AI Response:");
            $this->line($data['response'] ?? 'No response');

            // Show cart status
            if (!empty($data['cart'])) {
                $this->newLine();
                $this->info("🛒 Cart ({$data['cart_total']}):");
                foreach ($data['cart'] as $item) {
                    $this->line("  • {$item['product_name']} × {$item['quantity']} = {$item['subtotal']}");
                }
            }

            // Show customer data
            if (!empty($data['customer_data'])) {
                $cd = $data['customer_data'];
                $info = [];
                if (!empty($cd['name'])) $info[] = "👤 {$cd['name']}";
                if (!empty($cd['phone'])) $info[] = "📞 {$cd['phone']}";
                if (!empty($cd['address'])) $info[] = "📍 {$cd['address']}";
                if (!empty($info)) {
                    $this->info(implode(' | ', $info));
                }
            }

            // Show order if created
            if ($data['order_created'] ?? null) {
                $order = $data['order_created'];
                $this->newLine();
                $this->info("✅ Order Created: #{$order['order_number']} ({$order['total']})");
            }

            $this->line("⏱️ " . ($data['duration_ms'] ?? 0) . "ms");
        }

        return 0;
    }

    /**
     * Show help
     */
    protected function showHelp(): void
    {
        $this->info("\n🤖 AI Chat Test Tool");
        $this->info("═══════════════════════════════════════════════════════════\n");

        $this->info("Usage:");
        $this->line("  php artisan ai:test --list          List all scenarios");
        $this->line("  php artisan ai:test --all           Run all scenarios");
        $this->line("  php artisan ai:test --scenario=xxx  Run specific scenario");
        $this->line("  php artisan ai:test --chat          Interactive chat mode");
        $this->newLine();

        $this->info("Options:");
        $this->line("  --user=ID    User ID to test with (default: 1)");
        $this->newLine();

        $this->info("Examples:");
        $this->line("  php artisan ai:test --all --user=1");
        $this->line("  php artisan ai:test --scenario=full_order_iraqi");
        $this->line("  php artisan ai:test --chat --user=2");
        $this->newLine();
    }
}
