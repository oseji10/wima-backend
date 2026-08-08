<?php

namespace Database\Seeders;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\RequestType;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RequisitionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Audit', 'Finance', 'CEO'] as $roleName) {
            Role::firstOrCreate(['roleName' => $roleName]);
        }

        if (RequestType::where('code', 'general_requisition')->exists()) {
            return; // already seeded
        }

        $workflow = ApprovalWorkflow::create(['name' => 'General Requisition — Standard']);

        $steps = [
            ['step_order' => 1, 'role_id' => Role::where('roleName', 'Audit')->value('roleId'), 'label' => 'Audit Review'],
            ['step_order' => 2, 'role_id' => Role::where('roleName', 'Finance')->value('roleId'), 'label' => 'Finance Review'],
            ['step_order' => 3, 'role_id' => Role::where('roleName', 'CEO')->value('roleId'), 'label' => 'CEO Approval'],
        ];
        foreach ($steps as $s) {
            ApprovalStep::create(array_merge($s, ['workflow_id' => $workflow->id]));
        }

        RequestType::create([
            'name' => 'General Requisition',
            'code' => 'general_requisition',
            'workflow_id' => $workflow->id,
        ]);
    }
}