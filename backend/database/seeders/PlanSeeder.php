<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Uses factory states as the source of truth, then upserts by slug
     * so the seeder is safe to re-run (idempotent).
     */
    public function run(): void
    {
        $descriptions = [
            'free' => 'Perfect for getting started. Build and test your first workflows.',
            'starter' => 'For small teams. Everything you need to scale your automations.',
            'pro' => 'For growing businesses. Advanced features and AI-powered capabilities.',
            'teams' => 'For organizations. Team collaboration with advanced controls.',
            'enterprise' => 'For large enterprises. Unlimited everything with dedicated support.',
        ];

        foreach (['free', 'starter', 'pro', 'teams', 'enterprise'] as $state) {
            $model = Plan::factory()->{$state}()->make([
                'description' => $descriptions[$state],
            ]);

            $slug = $model->slug;

            $existing = Plan::where('slug', $slug)->first();
            if ($existing) {
                $existing->fill($model->attributesToArray())->save();
            } else {
                $model->id = (string) Str::uuid();
                $model->save();
            }
        }
    }
}
