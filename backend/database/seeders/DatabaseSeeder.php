<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Material;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $admin = User::create([
            'name' => 'Iris Alcantara',
            'email' => 'admin@lumen.test',
            'password' => $password,
            'role' => User::ROLE_ADMIN,
            'headline' => 'Director of Academic Operations',
            'phone' => '+1 555 0100',
            'last_seen_at' => now(),
        ]);

        $teacherSeeds = [
            ['Ada Whitfield', 'ada@lumen.test', 'Computing & Systems', 'Senior Lecturer, Computer Science'],
            ['Marcus Rhee', 'marcus@lumen.test', 'Mathematics', 'Department Chair, Mathematics'],
            ['Noor Haddad', 'noor@lumen.test', 'Life Sciences', 'Research Fellow, Molecular Biology'],
            ['Elena Vasquez', 'elena@lumen.test', 'Design & Media', 'Studio Lead, Visual Communication'],
            ['Theo Lindqvist', 'theo@lumen.test', 'Humanities', 'Lecturer, History & Rhetoric'],
        ];

        $teachers = collect($teacherSeeds)->map(fn ($t) => User::create([
            'name' => $t[0],
            'email' => $t[1],
            'password' => $password,
            'role' => User::ROLE_TEACHER,
            'headline' => $t[3],
            'phone' => '+1 555 '.random_int(1000, 9999),
            'last_seen_at' => now()->subMinutes(random_int(5, 4000)),
        ]));

        $studentNames = [
            'Jonah Beck', 'Priya Nair', 'Samuel Okafor', 'Mia Lindgren', 'Diego Salazar',
            'Hannah Petrova', 'Kwame Mensah', 'Sofia Ricci', 'Ethan Caldwell', 'Amara Diallo',
            'Lucas Fontaine', 'Yuki Tanaka', 'Isabella Moreau', 'Omar Farouk', 'Grace Osei',
            'Nikolai Volkov', 'Freya Andersen', 'Rafael Duarte', 'Zainab Ali', 'Caleb Whitmore',
            'Leila Haddad', 'Marcus Oyelaran', 'Chloe Duval', 'Arjun Kapoor', 'Rosa Calderon',
            'Tobias Krause', 'Nadia Rahman', 'Felix Moretti', 'Ingrid Solberg', 'Malik Johnson',
            'Camila Rojas', 'Raj Patel', 'Aurora Bellini', 'Emeka Nwosu', 'Clara Fontaine',
            'Hugo Marchetti', 'Salma Barakat', 'Theodore Grant', 'Anika Sharma', 'Bianca Costa',
        ];

        $students = collect($studentNames)->map(function (string $name, int $i) use ($password) {
            $slug = Str::of($name)->lower()->replace(' ', '.')->value();

            return User::create([
                'name' => $name,
                'email' => $slug.'@student.lumen.test',
                'password' => $password,
                'role' => User::ROLE_STUDENT,
                'headline' => 'Year '.random_int(1, 4).' student',
                'last_seen_at' => now()->subHours(random_int(1, 300)),
                'status' => $i === 39 ? 'suspended' : 'active',
                'created_at' => now()->subDays(random_int(5, 170)),
            ]);
        });

        $courseSeeds = [
            ['CS 210', 'Algorithms & Data Structures', 'Computer Science', 'violet', 'code', 0, 'Fall 2026'],
            ['MATH 301', 'Linear Algebra Intensives', 'Mathematics', 'sky', 'compass', 1, 'Fall 2026'],
            ['BIO 145', 'Cell Biology Laboratory', 'Life Sciences', 'emerald', 'flask', 2, 'Fall 2026'],
            ['DES 220', 'Visual Identity Studio', 'Design', 'rose', 'palette', 3, 'Fall 2026'],
            ['HIST 118', 'Revolutions That Shaped Us', 'History', 'amber', 'globe', 4, 'Fall 2026'],
            ['CS 340', 'Systems & Networking', 'Computer Science', 'indigo', 'book', 0, 'Fall 2026'],
            ['MUS 110', 'Sound & Signal Theory', 'Music', 'violet', 'music', 3, 'Spring 2026'],
            ['LAW 205', 'Digital Ethics & Law', 'Law', 'sky', 'scale', 1, 'Fall 2026'],
            ['CS 105', 'Foundations of Programming', 'Computer Science', 'emerald', 'code', 0, 'Spring 2026'],
        ];

        $courses = collect($courseSeeds)->map(function ($c, $i) use ($teachers) {
            $status = in_array($i, [6, 8], true) ? 'archived' : 'active';

            return Course::create([
                'code' => $c[0],
                'title' => $c[1],
                'subject' => $c[2],
                'accent' => $c[3],
                'icon' => $c[4],
                'teacher_id' => $teachers[$c[5]]->id,
                'term' => $c[6],
                'description' => $this->courseDescription($c[1]),
                'room_code' => strtoupper(Str::random(6)),
                'capacity' => [24, 30, 40, 50][random_int(0, 3)],
                'status' => $status,
                'starts_at' => now()->subWeeks(random_int(2, 10)),
                'ends_at' => now()->addWeeks(random_int(3, 14)),
            ]);
        });

        foreach ($courses as $course) {
            $roster = $students->shuffle()->take(random_int(12, 26));

            foreach ($roster as $student) {
                Enrollment::create([
                    'course_id' => $course->id,
                    'user_id' => $student->id,
                    'role_in_course' => 'student',
                    'status' => 'active',
                    'joined_at' => now()->subDays(random_int(3, 60)),
                ]);
            }

            if (random_int(0, 1)) {
                $assistant = $roster->first();
                Enrollment::where('course_id', $course->id)->where('user_id', $assistant->id)
                    ->update(['role_in_course' => 'assistant_teacher']);
            }

            $this->seedCourseContent($course, $roster);
        }

        $this->seedMessages($teachers, $students);
        $this->seedAdminActivity($admin);
    }

    private function seedCourseContent(Course $course, $roster): void
    {
        $announcementTemplates = [
            ['Week '.random_int(1, 6).' plan is live', 'I have published this week\'s roadmap under Resources. Office hours move to Thursday 3pm — same room code.', 'general', true],
            ['Guest speaker this Friday', 'We are hosting a practitioner from industry for a 45 minute session. Attendance is strongly encouraged; a short reflection will count toward participation.', 'event', false],
            ['Reading pack updated', 'I added two new chapters to the reading pack. Skim chapter 4 before Thursday so we can move straight into the workshop.', 'resource', false],
            ['Deadline shift', 'Because of the lab equipment outage, the deadline for the current module slides by 48 hours. No penalty, no forms.', 'urgent', false],
            ['Reminder: submit your proposal', 'Proposals are due end of week. One page is plenty — problem, approach, and what success looks like.', 'schedule', false],
        ];

        collect($announcementTemplates)->shuffle()->take(random_int(2, 4))->each(function ($a) use ($course, $roster) {
            $announcement = Announcement::create([
                'course_id' => $course->id,
                'user_id' => $course->teacher_id,
                'title' => $a[0],
                'body' => $a[1],
                'category' => $a[2],
                'pinned' => $a[3],
                'published_at' => now()->subDays(random_int(1, 30)),
            ]);

            $replies = $roster->shuffle()->take(random_int(0, 5));
            foreach ($replies as $reply) {
                $announcement->comments()->create([
                    'user_id' => $reply->id,
                    'body' => collect([
                        'Got it — thanks for the heads up.',
                        'Will this be recorded for those of us commuting?',
                        'Is the reading pack the same as last term or updated?',
                        'Appreciate the extension, that helps a lot.',
                        'Quick question: does the reflection count toward the final weight?',
                    ])->random(),
                    'created_at' => now()->subDays(random_int(0, 20)),
                ]);
            }
        });

        $assignmentTemplates = [
            ['Problem Set '.random_int(1, 5), 'assignment', 100, 'Complete the exercises in the handout. Show your reasoning, not just answers.'],
            ['Studio Critique '.random_int(1, 3), 'project', 150, 'Present three directions with rationale. You will receive structured peer feedback during the session.'],
            ['Reading Response', 'reading', 40, 'Write 400 words connecting this week\'s reading to a real example from your own experience.'],
            ['Module Quiz', 'quiz', 25, 'Short-format quiz covering weeks 1 to 3. Open notes, 45 minutes, one attempt.'],
            ['Lab Report', 'assignment', 80, 'Submit your observations, method, and a short discussion of sources of error.'],
            ['Final Project Milestone', 'project', 200, 'A working prototype plus a two-page design note describing trade-offs and next steps.'],
        ];

        $assignments = collect($assignmentTemplates)->shuffle()->take(random_int(4, 6))->map(function ($tpl, $i) use ($course) {
            $due = now()->addDays(random_int(-14, 21))->setTime(random_int(9, 22), [0, 15, 30, 45][random_int(0, 3)]);

            return Assignment::create([
                'course_id' => $course->id,
                'created_by' => $course->teacher_id,
                'title' => $tpl[0],
                'type' => $tpl[1],
                'max_points' => $tpl[2],
                'summary' => $tpl[3],
                'instructions' => $tpl[3]."\n\nDeliverables:\n- Your submission file or link\n- A short note on assumptions you made\n- Any questions you want answered in class",
                'due_at' => $due,
                'allow_late' => (bool) random_int(0, 1),
                'published_at' => now()->subDays(random_int(5, 30)),
                'status' => $i === 5 ? 'draft' : 'published',
            ]);
        });

        $assignments->where('status', 'published')->each(function (Assignment $assignment) use ($course, $roster) {
            $submitters = $roster->shuffle()->take((int) ceil($roster->count() * (random_int(55, 95) / 100)));

            foreach ($submitters as $student) {
                $submittedAt = $assignment->due_at
                    ? $assignment->due_at->copy()->addHours(random_int(-96, 72))
                    : now()->subDays(random_int(1, 10));

                $graded = $submittedAt->isPast() && random_int(1, 100) <= 62;

                $submission = AssignmentSubmission::create([
                    'assignment_id' => $assignment->id,
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'body' => collect([
                        'Attached my working notes. I tried two approaches — the second felt cleaner.',
                        'Here is my submission. I was unsure about the last part so I documented my reasoning.',
                        'Completed. I included an extra example because it helped me check my logic.',
                        'Submitting what I have — happy to revise if the framing is off.',
                    ])->random(),
                    'link_url' => random_int(0, 1) ? 'https://example.org/'.Str::random(8) : null,
                    'file_name' => random_int(0, 1) ? 'submission-'.Str::lower(Str::random(6)).'.pdf' : null,
                    'status' => 'submitted',
                    'submitted_at' => $submittedAt,
                ]);

                if ($submittedAt->gt($assignment->due_at ?? now()->addYear())) {
                    $submission->update(['status' => 'late']);
                }

                if (! $graded) {
                    continue;
                }

                $ratio = random_int(55, 100) / 100;
                $score = round($assignment->max_points * $ratio);

                $submission->update([
                    'score' => $score,
                    'feedback' => $ratio > 0.9
                        ? 'Excellent work — your reasoning is precise and well structured.'
                        : ($ratio > 0.75
                            ? 'Solid submission. Tighten the middle section and you are on track.'
                            : 'Good start. Revisit the core method — see me in office hours if it still feels unclear.'),
                    'status' => 'graded',
                    'graded_by' => $course->teacher_id,
                    'graded_at' => $submittedAt->copy()->addDays(random_int(1, 8)),
                ]);

                Grade::create([
                    'course_id' => $course->id,
                    'user_id' => $student->id,
                    'item_type' => 'assignment',
                    'item_id' => $assignment->id,
                    'item_title' => $assignment->title,
                    'score' => $score,
                    'max_score' => $assignment->max_points,
                    'weight' => 1,
                    'feedback' => 'Auto-imported from submission.',
                    'graded_by' => $course->teacher_id,
                    'graded_at' => $submittedAt->copy()->addDays(random_int(1, 8)),
                ]);
            }
        });

        $materialTemplates = [
            ['Course syllabus', 'link', 'Every deadline, weighting, and policy in one place.', 'Getting started'],
            ['Lecture slides — week 1', 'file', 'Slides used in the opening session.', 'Week 1'],
            ['Reading pack', 'link', 'Curated chapters and supplementary articles.', 'Resources'],
            ['Starter repository', 'link', 'Template to bootstrap your project work.', 'Tooling'],
            ['Reference cheat sheet', 'note', 'A one-page condensation of the core formulas and rules.', 'Resources'],
            ['Office hours sign-up', 'link', 'Reserve a 15 minute slot with me.', 'Support'],
        ];

        collect($materialTemplates)->shuffle()->take(random_int(3, 5))->each(function ($m) use ($course) {
            Material::create([
                'course_id' => $course->id,
                'user_id' => $course->teacher_id,
                'title' => $m[0],
                'type' => $m[1],
                'description' => $m[2],
                'topic' => $m[3],
                'url' => $m[1] === 'note' ? null : 'https://example.org/'.Str::slug($m[0]),
            ]);
        });
    }

    private function seedMessages($teachers, $students): void
    {
        $threads = [
            ['Could you clarify the weighting on the final project?', 'Of course — the milestone is 20% and the final submission is 35%. I will pin a breakdown to the class stream.'],
            ['I will miss Thursday due to a medical appointment. Is the recording available?', 'Yes, the session is recorded and posted the same evening. Focus on getting better.'],
            ['Is it possible to switch project groups?', 'Absolutely. Send me your preferred group before Friday and I will reshuffle.'],
            ['Thank you for the feedback on my last submission — it made the next step obvious.', 'Glad it helped. Keep that structure and you will do well on the final.'],
        ];

        foreach ($teachers as $teacher) {
            $partners = $students->shuffle()->take(3);

            foreach ($partners as $student) {
                $pairs = collect($threads)->shuffle()->take(1)->first();

                Message::create([
                    'sender_id' => $student->id,
                    'recipient_id' => $teacher->id,
                    'body' => $pairs[0],
                    'read_at' => random_int(0, 1) ? now()->subHours(random_int(1, 40)) : null,
                    'created_at' => now()->subDays(random_int(1, 20)),
                ]);

                Message::create([
                    'sender_id' => $teacher->id,
                    'recipient_id' => $student->id,
                    'body' => $pairs[1],
                    'read_at' => now()->subHours(random_int(1, 40)),
                    'created_at' => now()->subDays(random_int(1, 20))->addHours(2),
                ]);
            }
        }
    }

    private function seedAdminActivity(User $admin): void
    {
        $actions = ['auth.login', 'user.created', 'course.created', 'course.updated', 'user.updated', 'announcement.published', 'submission.graded'];

        foreach (range(1, 24) as $i) {
            ActivityLog::create([
                'user_id' => $admin->id,
                'action' => $actions[array_rand($actions)],
                'subject' => 'System event #'.$i,
                'properties' => ['source' => 'seed'],
                'created_at' => now()->subHours(random_int(1, 700)),
            ]);
        }
    }

    private function courseDescription(string $title): string
    {
        return "{$title} blends structured instruction with hands-on studio time. Expect weekly deliverables, "
            .'collaborative critique, and a final body of work that demonstrates depth rather than coverage. '
            .'Everything you need lives in this classroom — check the stream and the resources tab first.';
    }
}
