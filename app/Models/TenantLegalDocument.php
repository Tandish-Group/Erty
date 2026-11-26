<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantLegalDocument extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Records of tenancies at the member's assigned properties. */
    protected string $tenancyColumn = 'tenant_management_id';

    use HasFactory;
    protected $table = 'tenant_legal_documents';
    protected $fillable = ['tenant_management_id', 'legal_document_id'];


    /**
     * Relationship to tenant management
     */
    public function tenancy()
    {
        return $this->belongsTo(TenantManagement::class, 'tenant_management_id');
    }


}
