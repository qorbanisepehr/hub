<?php

use App\Domains\Questionnaire\Models\Questionnaire;
use Illuminate\Support\Str;

function createQuestionnaire(string $status = 'submitted'): Questionnaire
{
    $suffix = substr((string) Str::uuid(), 0, 8);

    return Questionnaire::create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => "test{$suffix}@example.com",
        'mobile' => '0912'.substr($suffix, 0, 7),
        'status' => $status,
        'mobile_verified_at' => now(),
        'email_verified_at' => now(),
    ]);
}

describe('questionnaire management authorization', function () {
    it('blocks unauthenticated access to the management list and detail', function () {
        $questionnaire = createQuestionnaire();

        $this->getJson('/api/questionnaires')->assertStatus(401);
        $this->getJson("/api/questionnaires/{$questionnaire->id}")->assertStatus(401);
        $this->postJson("/api/questionnaire/{$questionnaire->uuid}/review")->assertStatus(401);
        $this->postJson("/api/questionnaire/{$questionnaire->uuid}/reject")->assertStatus(401);
    });

    it('denies the management list without the questionnaire.view permission', function () {
        $user = createUserWithPermissions();
        createQuestionnaire();

        $this->actingAs($user)
            ->getJson('/api/questionnaires')
            ->assertStatus(403);
    });

    it('denies the management detail without the questionnaire.view permission', function () {
        $user = createUserWithPermissions();
        $questionnaire = createQuestionnaire();

        $this->actingAs($user)
            ->getJson("/api/questionnaires/{$questionnaire->id}")
            ->assertStatus(403);
    });

    it('lists submitted questionnaires with the questionnaire.view permission', function () {
        $user = createUserWithPermissions(['questionnaire.view']);
        createQuestionnaire('submitted');
        createQuestionnaire('draft');

        $this->actingAs($user)
            ->getJson('/api/questionnaires')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'submitted');
    });

    it('shows a questionnaire with the questionnaire.view permission', function () {
        $user = createUserWithPermissions(['questionnaire.view']);
        $questionnaire = createQuestionnaire('submitted');

        $this->actingAs($user)
            ->getJson("/api/questionnaires/{$questionnaire->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');
    });

    it('denies review without the questionnaire.review permission', function () {
        $user = createUserWithPermissions(['questionnaire.view']);
        $questionnaire = createQuestionnaire('submitted');

        $this->actingAs($user)
            ->postJson("/api/questionnaire/{$questionnaire->uuid}/review")
            ->assertStatus(403);
    });

    it('denies reject without the questionnaire.reject permission', function () {
        $user = createUserWithPermissions(['questionnaire.view']);
        $questionnaire = createQuestionnaire('submitted');

        $this->actingAs($user)
            ->postJson("/api/questionnaire/{$questionnaire->uuid}/reject")
            ->assertStatus(403);
    });
});
