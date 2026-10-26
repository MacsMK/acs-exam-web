<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de grupo (RW-32 a RW-36). Solo el docente dueño lo crea.
 */
class GroupStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'teacher';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:120'],
        ];
    }
}
