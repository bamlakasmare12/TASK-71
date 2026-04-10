<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\DataDictionary;
use App\Models\PasswordHistory;
use App\Models\Service;
use App\Models\Tag;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Users ──
        $users = [
            ['username' => 'admin', 'name' => 'System Administrator', 'email' => 'admin@researchhub.local', 'password' => 'Admin123!@#456', 'role' => UserRole::Admin],
            ['username' => 'editor', 'name' => 'Content Editor', 'email' => 'editor@researchhub.local', 'password' => 'Editor123!@#456', 'role' => UserRole::Editor],
            ['username' => 'learner', 'name' => 'Jane Researcher', 'email' => 'learner@researchhub.local', 'password' => 'Learner123!@#456', 'role' => UserRole::Learner],
        ];

        foreach ($users as $data) {
            $hashedPw = Hash::make($data['password']);
            $user = User::create(array_merge($data, [
                'password_updated_at' => now(),
                'is_active' => true,
            ]));
            PasswordHistory::create([
                'user_id' => $user->id,
                'password_hash' => $hashedPw,
                'created_at' => now(),
            ]);
        }

        $editor = User::where('username', 'editor')->first();

        // ── Data Dictionaries ──
        $dictionaries = [
            ['type' => 'service_type', 'key' => 'consultation', 'label' => 'Consultation', 'sort_order' => 1],
            ['type' => 'service_type', 'key' => 'equipment', 'label' => 'Equipment Reservation', 'sort_order' => 2],
            ['type' => 'service_type', 'key' => 'editorial', 'label' => 'Editorial Review', 'sort_order' => 3],
            ['type' => 'service_type', 'key' => 'training', 'label' => 'Training Session', 'sort_order' => 4],
            ['type' => 'eligibility', 'key' => 'faculty', 'label' => 'Faculty', 'sort_order' => 1],
            ['type' => 'eligibility', 'key' => 'staff', 'label' => 'Staff', 'sort_order' => 2],
            ['type' => 'eligibility', 'key' => 'graduate', 'label' => 'Graduate Learner', 'sort_order' => 3],
            ['type' => 'eligibility', 'key' => 'undergraduate', 'label' => 'Undergraduate Learner', 'sort_order' => 4],
            ['type' => 'breach_reason', 'key' => 'no_show', 'label' => 'No-Show', 'sort_order' => 1],
            ['type' => 'breach_reason', 'key' => 'late_cancel', 'label' => 'Late Cancellation', 'sort_order' => 2],
            ['type' => 'breach_reason', 'key' => 'policy_violation', 'label' => 'Policy Violation', 'sort_order' => 3],
        ];

        foreach ($dictionaries as $dict) {
            DataDictionary::create($dict);
        }

        // ── Tags ──
        $tags = [];
        foreach (['Writing', 'Statistics', 'Data Analysis', 'Lab Equipment', 'Grant Writing', 'Peer Review', 'Methodology', 'Software'] as $name) {
            $tags[$name] = Tag::create(['name' => $name]);
        }

        // ── Services ──
        $services = [
            [
                'title' => 'Research Methodology Consultation',
                'description' => 'One-on-one consultation with a research methodology expert to help design your study, refine research questions, and select appropriate methods.',
                'service_type' => 'consultation',
                'target_audience' => ['faculty', 'graduate'],
                'price' => 0,
                'category' => 'Research Support',
                'eligibility_notes' => 'Available to all faculty and graduate students with an active research project.',
                'tags' => ['Methodology', 'Writing'],
            ],
            [
                'title' => 'Statistical Analysis Support',
                'description' => 'Get help with statistical analysis for your research project. Supports SPSS, R, Stata, and Python-based analysis workflows.',
                'service_type' => 'consultation',
                'target_audience' => ['faculty', 'staff', 'graduate'],
                'price' => 0,
                'category' => 'Research Support',
                'tags' => ['Statistics', 'Data Analysis', 'Software'],
            ],
            [
                'title' => 'Manuscript Editorial Review',
                'description' => 'Professional editorial review of journal manuscripts, conference papers, and dissertations. Includes feedback on structure, clarity, and APA/MLA formatting.',
                'service_type' => 'editorial',
                'target_audience' => ['faculty', 'graduate'],
                'price' => 25.00,
                'category' => 'Editorial Services',
                'eligibility_notes' => 'Manuscripts must be under 50 pages. Longer works require multiple sessions.',
                'tags' => ['Writing', 'Peer Review'],
            ],
            [
                'title' => 'Grant Proposal Writing Workshop',
                'description' => 'Intensive workshop covering grant proposal structure, budget justification, and persuasive writing techniques for funding applications.',
                'service_type' => 'training',
                'target_audience' => ['faculty', 'staff', 'graduate'],
                'price' => 50.00,
                'category' => 'Training',
                'tags' => ['Grant Writing', 'Writing'],
            ],
            [
                'title' => '3D Printer Reservation',
                'description' => 'Reserve time on our Prusa MK4 or Formlabs Form 3+ printers for research prototyping and model creation.',
                'service_type' => 'equipment',
                'target_audience' => ['faculty', 'staff', 'graduate', 'undergraduate'],
                'price' => 15.00,
                'category' => 'Equipment',
                'eligibility_notes' => 'Must complete safety training before first use.',
                'tags' => ['Lab Equipment'],
            ],
            [
                'title' => 'Qualitative Data Analysis Lab',
                'description' => 'Access to NVivo and ATLAS.ti software stations for qualitative data coding and analysis. Includes brief orientation.',
                'service_type' => 'equipment',
                'target_audience' => ['faculty', 'graduate'],
                'price' => 0,
                'category' => 'Equipment',
                'tags' => ['Data Analysis', 'Software'],
            ],
        ];

        foreach ($services as $sData) {
            $tagNames = $sData['tags'] ?? [];
            unset($sData['tags']);

            $service = Service::create(array_merge($sData, [
                'is_active' => true,
                'created_by' => $editor->id,
                'updated_by' => $editor->id,
            ]));

            $tagIds = collect($tagNames)->map(fn($n) => $tags[$n]->id)->toArray();
            $service->tags()->sync($tagIds);

            // Create time slots for next 7 days
            for ($day = 1; $day <= 7; $day++) {
                $date = now()->addDays($day);
                if ($date->isWeekend()) {
                    continue;
                }

                TimeSlot::create([
                    'service_id' => $service->id,
                    'start_time' => $date->copy()->setTime(9, 0),
                    'end_time' => $date->copy()->setTime(10, 0),
                    'capacity' => 2,
                    'is_active' => true,
                    'created_by' => $editor->id,
                ]);

                TimeSlot::create([
                    'service_id' => $service->id,
                    'start_time' => $date->copy()->setTime(14, 0),
                    'end_time' => $date->copy()->setTime(15, 0),
                    'capacity' => 2,
                    'is_active' => true,
                    'created_by' => $editor->id,
                ]);
            }
        }
    }
}
