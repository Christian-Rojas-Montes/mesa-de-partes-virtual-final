<?php

namespace App\Http\Requests\Title;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationWorkProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createApplicationWork', $this->route('titleProcess')) === true;
    }

    public function rules(): array
    {
        $max = config('title-process.application_work.max_members');

        return ['title' => ['required', 'string', 'max:250'], 'problem' => ['required', 'string', 'max:5000'], 'objective' => ['required', 'string', 'max:5000'], 'study_program' => ['required', 'string', 'max:200'], 'proposed_advisor' => ['required', 'string', 'max:200'], 'project_document_id' => ['required', 'integer', 'exists:request_documents,id'], 'proposal_date' => ['required', 'date'], 'members' => ['required', 'array', 'min:1', 'max:'.$max], 'members.*.name' => ['required', 'string', 'max:200'], 'members.*.study_program' => ['required', 'string', 'max:200']];
    }
}
