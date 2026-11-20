<?php

namespace App\Modules\Academy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentCommunicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'enrollment_id' => ['nullable', 'integer', 'exists:enrollments,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'activity_type' => ['required', 'in:phone_call,message,follow_up,email,meeting,other'],
            'channel' => ['required', 'in:phone,whatsapp,sms,email,in_person,other'],
            'direction' => ['nullable', 'in:incoming,outgoing'],
            'subject' => ['nullable', 'string', 'max:255'],
            'remarks' => ['required', 'string', 'max:10000'],
            'outcome' => ['nullable', 'string', 'max:2000'],
            'contacted_at' => ['required', 'date'],
            'follow_up_at' => ['nullable', 'date', 'after_or_equal:contacted_at'],
        ];
    }
}
