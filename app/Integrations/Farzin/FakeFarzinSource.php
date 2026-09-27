<?php

namespace App\Integrations\Farzin;

/**
 * Deterministic Farzin source (same role as the fake gateways of the
 * other integrations): exercises the real staging/projection pipeline
 * without SQL Server. Rows carry the external vocabulary verbatim,
 * including the CDATA-wrapped text the real source uses.
 */
final class FakeFarzinSource implements FarzinGatewayContract
{
    /** @var list<array<string, mixed>> */
    private array $letters;

    /** @var list<array<string, mixed>> */
    private array $sessions;

    /** @param list<array<string, mixed>> $letters @param list<array<string, mixed>> $sessions */
    public function __construct(
        array $letters = self::SAMPLE_LETTERS,
        array $sessions = self::SAMPLE_SESSIONS,
    ) {
        $this->letters = $letters;
        $this->sessions = $sessions;
    }

    public function letters(?string $afterLastEdit): array
    {
        return array_values(array_filter(
            $this->letters,
            fn (array $row) => $afterLastEdit === null
                || ($row['LastEditDate'] ?? null) > $afterLastEdit,
        ));
    }

    public function sessions(?string $afterLastEdit): array
    {
        return array_values(array_filter(
            $this->sessions,
            fn (array $row) => $afterLastEdit === null
                || ($row['LastEditDate'] ?? null) > $afterLastEdit,
        ));
    }

    /**
     * Sample rows: one invitation letter with a Jalali meeting date, one
     * non-meeting letter, and one session form for the same date. CDATA
     * + Arabic letters included to exercise the normalizer for real.
     */
    public const SAMPLE_LETTERS = [
        [
            'EntityCode' => 101,
            'subject' => '<![CDATA[<p>دعوت به جلسه هیئت مدیره مورخ ۱۴۰۴/۰۶/۱۵</p>]]>',
            'text' => '<![CDATA[<p>با سلام\nاحتراماً به استحضار می‌رساند دستور جلسه:\n1- تصویب صورت‌های مالی\nبا تشکر</p>]]>',
            'date' => '2025-09-06',
            'EntityNumber' => 451,
            'ExportEntityNumber' => 451,
            'r_title' => 'مدیرعامل',
            'r_name' => 'يوسف احمدي',
            'company_name' => 'شرکت نمونه',
            'CreatorRoleID' => 2880,
            'CreationDate' => '2025-09-06 08:30:00.000',
            'LastEditDate' => '2025-09-06 08:30:00.000',
        ],
        [
            'EntityCode' => 102,
            'subject' => 'نامه اداری بدون جلسه',
            'text' => 'متن نامه',
            'date' => '2025-09-07',
            'EntityNumber' => 452,
            'ExportEntityNumber' => 452,
            'r_title' => 'مدیرعامل',
            'r_name' => 'مریم کریمی',
            'company_name' => 'شرکت نمونه',
            'CreatorRoleID' => 2880,
            'CreationDate' => '2025-09-07 09:00:00.000',
            'LastEditDate' => '2025-09-07 09:00:00.000',
        ],
    ];

    public const SAMPLE_SESSIONS = [
        [
            'EntityCode' => 201,
            'dateSession' => '2025-09-06',
            'sharh' => '<![CDATA[جلسه هیئت مدیره ساعت ۱۰ در محل شرکت تشکیل شد]]>',
            'timeStart' => '10:00',
            'timeEnd' => '12:30',
            'location' => 'محل شرکت',
            'dastor' => 'دستور جلسه',
            'hazerin' => 'اعضای هیئت مدیره',
            'dabirName' => 'دبیر هیئت',
            'CreationDate' => '2025-09-06 14:00:00.000',
            'LastEditDate' => '2025-09-06 14:00:00.000',
        ],
    ];
}
