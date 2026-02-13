<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Database\Seeders;

use Illuminate\Database\Seeder;
use LaravelPlus\Subscribe\Models\Subscriber;
use LaravelPlus\Subscribe\Models\SubscriptionList;

final class SubscribeSeeder extends Seeder
{
    public function run(): void
    {
        $this->createLists();
        $this->createSubscribers();
    }

    private function createLists(): void
    {
        $lists = [
            [
                'name' => 'Newsletter',
                'slug' => 'newsletter',
                'description' => 'Our main newsletter with updates and news',
                'is_public' => true,
                'is_default' => true,
                'double_opt_in' => true,
                'welcome_email_enabled' => true,
                'welcome_email_subject' => 'Welcome to our Newsletter!',
                'welcome_email_content' => "Thank you for subscribing to our newsletter!\n\nYou'll receive the latest updates, news, and exclusive content directly in your inbox.",
            ],
            [
                'name' => 'Product Updates',
                'slug' => 'product-updates',
                'description' => 'Get notified about new features and improvements',
                'is_public' => true,
                'is_default' => false,
                'double_opt_in' => true,
                'welcome_email_enabled' => false,
            ],
            [
                'name' => 'Promotions',
                'slug' => 'promotions',
                'description' => 'Special offers and discounts',
                'is_public' => true,
                'is_default' => false,
                'double_opt_in' => false,
                'welcome_email_enabled' => false,
            ],
            [
                'name' => 'Beta Testers',
                'slug' => 'beta-testers',
                'description' => 'Early access to new features',
                'is_public' => false,
                'is_default' => false,
                'double_opt_in' => true,
                'welcome_email_enabled' => true,
                'welcome_email_subject' => 'Welcome to the Beta Program!',
                'welcome_email_content' => "You're now part of our exclusive beta testing program.\n\nYou'll get early access to new features before anyone else.",
            ],
        ];

        foreach ($lists as $listData) {
            SubscriptionList::updateOrCreate(
                ['slug' => $listData['slug']],
                $listData
            );
        }
    }

    private function createSubscribers(): void
    {
        if (Subscriber::count() >= 50) {
            return;
        }

        $lists = SubscriptionList::all();
        $defaultList = $lists->firstWhere('is_default', true);

        for ($i = 0; $i < 50; $i++) {
            $status = fake()->randomElement(['pending', 'subscribed', 'subscribed', 'subscribed', 'unsubscribed']);

            $subscriber = Subscriber::create([
                'email' => fake()->unique()->safeEmail(),
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'phone' => fake()->optional(0.3)->phoneNumber(),
                'company' => fake()->optional(0.2)->company(),
                'tags' => fake()->randomElements(['newsletter', 'updates', 'beta'], rand(0, 2)),
                'source' => fake()->randomElement(['website', 'api', 'import']),
                'ip_address' => fake()->ipv4(),
                'status' => $status,
                'confirmed_at' => $status === 'subscribed' ? fake()->dateTimeBetween('-6 months') : null,
                'created_at' => fake()->dateTimeBetween('-6 months'),
            ]);

            if ($defaultList) {
                $subscriber->lists()->attach($defaultList->id);
            }

            $additionalLists = $lists->where('is_default', false)->random(rand(0, 2));
            foreach ($additionalLists as $list) {
                $subscriber->lists()->syncWithoutDetaching([$list->id]);
            }
        }
    }
}
