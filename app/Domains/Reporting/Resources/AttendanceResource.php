<?php

namespace App\Domains\Reporting\Resources;

use App\Domains\Reporting\Reports\Attendance\AttendanceRecord;
use App\Domains\Reporting\Reports\Attendance\AttendanceResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * HTTP contract for the attendance report (V2 §17): the resource owns the
 * JSON shape; the report only produces the typed result.
 *
 * @property AttendanceResult $resource
 */
class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => array_map(
                fn (AttendanceRecord $record): array => $this->record($record),
                $this->resource->records,
            ),
            'report' => $this->resource->report,
            'generated_at' => $this->resource->generatedAt->toIso8601String(),
            'freshness' => $this->resource->freshness,
            'metadata' => (object) $this->resource->metadata,
            'warnings' => $this->resource->warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function record(AttendanceRecord $record): array
    {
        return [
            'employee_identifier' => $record->employeeIdentifier,
            'date' => $record->date->toDateString(),
            'entry_at' => $record->entryAt?->toIso8601String(),
            'exit_at' => $record->exitAt?->toIso8601String(),
            'worked_minutes' => $record->workedMinutes,
        ];
    }
}
