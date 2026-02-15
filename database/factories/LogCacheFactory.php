<?php

namespace Database\Factories;

use App\Models\DeviceAuthorization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LogCache>
 */
class LogCacheFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_authorization_id' => DeviceAuthorization::factory(),
            'project_slug' => fake()->randomElement(['chief-uplink', 'api-gateway', 'data-pipeline', 'frontend-app']),
            'log_type' => 'claude_output',
            'story_id' => 'US-'.str_pad(fake()->numberBetween(1, 20), 3, '0', STR_PAD_LEFT),
            'content' => $this->generateClaudeOutput(),
        ];
    }

    private function generateClaudeOutput(): string
    {
        $lines = [
            "I'll implement the user authentication system. Let me start by examining the existing code...",
            '',
            '```php',
            '// app/Http/Controllers/AuthController.php',
            'public function login(Request $request)',
            '{',
            '    $credentials = $request->validate([',
            "        'email' => 'required|email',",
            "        'password' => 'required',",
            '    ]);',
            '',
            '    if (Auth::attempt($credentials)) {',
            '        $request->session()->regenerate();',
            "        return redirect()->intended('/dashboard');",
            '    }',
            '',
            "    return back()->withErrors(['email' => 'Invalid credentials.']);",
            '}',
            '```',
            '',
            'Now I need to create the migration for the sessions table...',
            '',
            'All tests passing. Committing changes.',
        ];

        return implode("\n", $lines);
    }
}
