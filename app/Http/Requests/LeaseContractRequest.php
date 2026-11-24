<?php
namespace App\Http\Requests;

use App\Services\LeaseContractService;
use Illuminate\Foundation\Http\FormRequest;

class LeaseContractRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user()->active && $this->user()->can("contracts.create");
    }

    public function rules()
    {
        return LeaseContractService::draftRules();
    }
}
