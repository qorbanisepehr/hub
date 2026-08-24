<?php

namespace Tests\Unit\Domains\Audit\Architecture;

use Tests\TestCase;

class AuditDomainTest extends TestCase
{
    /**
     * Audit Domain must NOT import Employee models/repositories.
     */
    public function test_audit_domain_does_not_import_employee_models(): void
    {
        $this->assertFilesDoNotContain(
            glob(base_path('app/Domains/Audit/**/*.php')),
            'App\\Domains\\Employee\\Models',
            'Audit Domain must not depend on Employee models',
        );
    }

    /**
     * Audit Domain must NOT import Document models/repositories.
     */
    public function test_audit_domain_does_not_import_document_models(): void
    {
        $this->assertFilesDoNotContain(
            glob(base_path('app/Domains/Audit/**/*.php')),
            'App\\Domains\\Document\\Models',
            'Audit Domain must not depend on Document models',
        );
    }

    /**
     * Audit Domain must NOT import Questionnaire models/repositories.
     */
    public function test_audit_domain_does_not_import_questionnaire_models(): void
    {
        $this->assertFilesDoNotContain(
            glob(base_path('app/Domains/Audit/**/*.php')),
            'App\\Domains\\Questionnaire\\Models',
            'Audit Domain must not depend on Questionnaire models',
        );
    }

    /**
     * Audit Domain uses shared contracts from App\Contracts.
     */
    public function test_audit_domain_uses_shared_contracts(): void
    {
        $files = glob(base_path('app/Domains/Audit/**/*.php'));
        $found = false;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'App\\Contracts\\AuditEvent')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            'Expected Audit Domain to use App\Contracts\AuditEvent',
        );
    }

    private function assertFilesDoNotContain(array $files, string $forbidden, string $message): void
    {
        $violations = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, $forbidden)) {
                $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            }
        }

        $this->assertEmpty(
            $violations,
            $message.'. Found in: '.implode(', ', $violations),
        );
    }
}
