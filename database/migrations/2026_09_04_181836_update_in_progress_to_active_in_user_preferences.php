<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceInFilterStatuses('in_progress', 'active');
    }

    public function down(): void
    {
        $this->replaceInFilterStatuses('active', 'in_progress');
    }

    private function replaceInFilterStatuses(string $from, string $to): void
    {
        User::whereNotNull('preferences')->each(function (User $user) use ($from, $to) {
            $preferences = $user->preferences;

            if (! isset($preferences['filter_statuses']) || ! is_array($preferences['filter_statuses'])) {
                return;
            }

            $changed = false;

            foreach ($preferences['filter_statuses'] as $view => $statuses) {
                if (! is_array($statuses)) {
                    continue;
                }

                $index = array_search($from, $statuses, strict: true);

                if ($index === false) {
                    continue;
                }

                $statuses[$index] = $to;
                $preferences['filter_statuses'][$view] = array_values(array_unique($statuses));
                $changed = true;
            }

            if ($changed) {
                $user->update(['preferences' => $preferences]);
            }
        });
    }
};
