<?php

namespace Tests\Unit\Support\Models;

use App\Domains\Cv\Models\Cv;
use App\Domains\Questionnaire\Models\Questionnaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedCandidateModelTraitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cv_and_questionnaire_generate_uuid_on_create(): void
    {
        $cv = Cv::create(['first_name' => 'Test', 'last_name' => 'User']);
        $questionnaire = Questionnaire::create(['first_name' => 'Test', 'last_name' => 'User']);

        $this->assertNotEmpty($cv->uuid);
        $this->assertNotEmpty($questionnaire->uuid);
        $this->assertNotSame($cv->uuid, $questionnaire->uuid);
    }

    public function test_otp_identifier_prefixes_differ_per_domain(): void
    {
        $cv = Cv::create(['first_name' => 'Test', 'last_name' => 'User']);
        $questionnaire = Questionnaire::create(['first_name' => 'Test', 'last_name' => 'User']);

        $this->assertSame("cv:{$cv->uuid}", $cv->getOtpIdentifier());
        $this->assertSame("questionnaire:{$questionnaire->uuid}", $questionnaire->getOtpIdentifier());
    }

    public function test_otp_verification_flags_round_trip(): void
    {
        $cv = Cv::create(['first_name' => 'Test', 'last_name' => 'User']);

        $this->assertFalse($cv->isMobileVerified());
        $this->assertFalse($cv->isEmailVerified());

        $cv->markOtpVerified('mobile');
        $cv->markOtpVerified('email');

        $cv->refresh();
        $this->assertTrue($cv->isMobileVerified());
        $this->assertTrue($cv->isEmailVerified());
    }

    public function test_dirty_contact_clears_verification_stamps(): void
    {
        $cv = Cv::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'first@example.com',
            'mobile' => '09120000000',
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
        ]);

        $cv->update(['email' => 'second@example.com']);

        $cv->refresh();
        $this->assertNull($cv->email_verified_at);
        $this->assertNotNull($cv->mobile_verified_at);
    }

    public function test_section_accessors_round_trip(): void
    {
        $cv = Cv::create(['first_name' => 'Test', 'last_name' => 'User']);

        $this->assertNull($cv->getSection('personal'));
        $cv->setSection('personal', ['gender' => 'male']);
        $this->assertSame(['gender' => 'male'], $cv->getSection('personal'));
    }

    public function test_version_tracking(): void
    {
        $cv = Cv::create(['first_name' => 'Test', 'last_name' => 'User']);
        $cv->refresh();

        $this->assertTrue($cv->matchesVersion(1));
        $cv->incrementVersion();
        $this->assertSame(2, $cv->version);
        $this->assertTrue($cv->matchesVersion(2));
        $this->assertFalse($cv->matchesVersion(1));
    }

    public function test_cv_prunes_orphaned_military_status_from_personal_section(): void
    {
        $cv = Cv::create(['first_name' => 'Test', 'last_name' => 'User']);
        $cv->setSection('personal', [
            'gender' => 'male',
            'military_status' => ['status' => 'completed'],
        ]);
        $cv->save();

        $cv->setSection('personal', [
            'gender' => 'female',
            'military_status' => ['status' => 'completed'],
        ]);
        $cv->save();

        $cv->refresh();
        $this->assertArrayNotHasKey('military_status', $cv->getSection('personal'));
    }

    public function test_questionnaire_prunes_orphaned_military_status_from_personal_section(): void
    {
        $questionnaire = Questionnaire::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'gender' => 'male',
        ]);
        $questionnaire->setSection('personal', [
            'gender' => 'male',
            'military_status' => ['status' => 'completed'],
        ]);
        $questionnaire->save();

        $questionnaire->gender = 'female';
        $questionnaire->save();

        $questionnaire->refresh();
        $this->assertArrayNotHasKey('military_status', $questionnaire->getSection('personal'));
    }

    public function test_cv_keeps_military_status_when_gender_stays_male(): void
    {
        $cv = Cv::create(['first_name' => 'Test', 'last_name' => 'User']);
        $cv->setSection('personal', [
            'gender' => 'male',
            'military_status' => ['status' => 'completed'],
        ]);
        $cv->save();

        $cv->refresh();
        $this->assertArrayHasKey('military_status', $cv->getSection('personal'));
    }
}
