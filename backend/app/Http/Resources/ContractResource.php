<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a Contract model into a JSON-safe API response.
 *
 * @mixin \App\Models\Contract
 */
class ContractResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'reference'     => $this->reference,
            'type'          => $this->type,
            'status'        => $this->status,
            'start_date'    => $this->start_date?->toDateString(),
            'end_date'      => $this->end_date?->toDateString(),
            'salary'        => $this->salary,
            'currency'      => $this->currency,
            'position'      => $this->position,
            'terms'         => $this->terms,
            'pdf_path'      => $this->pdf_path,
            'is_expired'    => $this->is_expired,
            'approved_at'   => $this->approved_at?->toDateTimeString(),
            'created_at'    => $this->created_at?->toDateTimeString(),
            'employee'      => $this->whenLoaded('employee', fn () => [
                'id'        => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'code'      => $this->employee->employee_code,
                'avatar_url'=> $this->employee->avatar_url ?? null,
            ]),
            'department'    => $this->whenLoaded('department', fn () => [
                'id'   => $this->department?->id,
                'name' => $this->department?->name,
            ]),
            'created_by'    => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ]),
            'approved_by'   => $this->whenLoaded('approvedBy', fn () => [
                'id'   => $this->approvedBy?->id,
                'name' => $this->approvedBy?->name,
            ]),
        ];
    }
}
