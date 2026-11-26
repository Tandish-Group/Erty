<?php

namespace App\Http\Controllers\Business\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentDepartment;
use App\Models\AgentRole;
use App\Models\AgentWorkLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Agent settings: departments, roles, work locations and the other lists used on agent profiles. */
class AgentSettingsController extends Controller
{
    /**
     * Display agent settings page
     */
    public function agentSettings()
    {
        $agentRoles = AgentRole::where('created_by', business_owner_id())
            ->active()
            ->get();
        $agentDepartments = AgentDepartment::where('created_by', business_owner_id())
            ->active()
            ->get();
        $agentWorkLocations = AgentWorkLocation::where('created_by', business_owner_id())
            ->active()
            ->get();

        return view('pages.business.crm.agent_management.settings', compact('agentRoles', 'agentDepartments', 'agentWorkLocations'));
    }
    /**
     * Handle agent settings CRUD operations
     */
    public function handleSettings(Request $request)
    {
        $type = $request->input('type') ?? $request->query('type');

        if (!$type) {
            return response()->json([
                'status' => 400,
                'message' => 'Setting type is required'
            ], 400);
        }

        if ($request->isMethod('get')) {
            return $this->getSettings($type);
        }

        if ($request->isMethod('post')) {
            return $this->storeSettings($request, $type);
        }

        if ($request->isMethod('put') || $request->isMethod('patch')) {
            $id = $request->route('id');
            return $this->updateSettings($request, $type, $id);
        }

        if ($request->isMethod('delete')) {
            $id = $request->route('id');
            return $this->deleteSettings($request, $type, $id);
        }

        return response()->json(['message' => 'Method not allowed'], 405);
    }
    private function getSettings($type)
    {
        try {
            $data = [];
    
            switch ($type) {
                case 'department':
                    $data = AgentDepartment::with('agents')
                        ->where('created_by', business_owner_id())
                        ->active()
                        ->get()
                        ->map(function($dept) {
                            return [
                                'id' => $dept->id,
                                'name' => $dept->name,
                                'description' => $dept->description,
                                'department_head' => $dept->department_head
                            ];
                        });
                    break;
    
                case 'role':
                    $data = AgentRole::with('department')
                        ->where('created_by', business_owner_id())
                        ->active()
                        ->get()
                        ->map(function($role) {
                            return [
                                'id' => $role->id,
                                'name' => $role->name,
                                'description' => $role->description,
                                'level' => ucfirst($role->level),
                                'department' => $role->department ? $role->department->name : null,
                                'department_id' => $role->department_id
                            ];
                        });
                    break;

                case 'location':
                    $data = AgentWorkLocation::where('created_by', business_owner_id())
                        ->active()
                        ->get()
                        ->map(function($location) {
                            return [
                                'id' => $location->id,
                                'name' => $location->name,
                                'address' => $location->address,
                                'city' => $location->city,
                                'country' => $location->country,
                                'type' => ucfirst($location->type)
                            ];
                        });
                    break;
    
                
    
                default:
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid setting type'
                    ], 400);
            }
    
            return response()->json([
                'status' => 200,
                'data' => $data
            ]);
    
        } catch (\Exception $e) {
            Log::error('Error fetching settings', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while fetching data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    private function storeSettings(Request $request, $type)
    {
        // Get validation rules for the type
        $validationRules = $this->getValidationRules($type);
        
        // Validate the request
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $created = null;

            switch ($type) {
                case 'department':
                    Log::info('Creating Department with data: ', $request->toArray());
                    // TODO: Replace with actual model
                    $created = AgentDepartment::create([
                        'name' => $request->name,
                        'description' => $request->description,
                        'department_head' => $request->department_head ?? null,
                        'created_by' => business_owner_id()
                    ]);

                    Log::info('Created Department: ', $created->toArray());
                    
                    // Simulated response
                    $created = [
                        'id' => rand(100, 999),
                        'name' => $request->name,
                        'description' => $request->description,
                        'head_id' => $request->head_id
                    ];
                    break;

                case 'role':
                    // TODO: Replace with actual model
                    $created = AgentRole::create([
                        'name' => $request->name,
                        'description' => $request->description,
                        'level' => $request->level,
                        'department_id' => $request->department_id,
                        'created_by' => business_owner_id()
                    ]);
                    
                    $created = [
                        'id' => rand(100, 999),
                        'name' => $request->name,
                        'description' => $request->description,
                        'level' => $request->level,
                        'department_id' => $request->department_id
                    ];
                    break;


                case 'location':
                    // TODO: Replace with actual model
                    $created = AgentWorkLocation::create([
                        'name' => $request->name,
                        'address' => $request->address,
                        'city' => $request->city,
                        'country' => $request->country,
                        'type' => $request->location_type,
                        'created_by' => business_owner_id()
                    ]);
                    
                    $created = [
                        'id' => rand(100, 999),
                        'name' => $request->name,
                        'address' => $request->address,
                        'city' => $request->city,
                        'country' => $request->country,
                        'type' => $request->location_type
                    ];
                    break;


                default:
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid setting type'
                    ], 400);
            }

            return response()->json([
                'status' => 200,
                'message' => ucfirst(str_replace('_', ' ', $type)) . ' created successfully',
                'data' => $created
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while saving',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    private function updateSettings(Request $request, $type, $id)
    {
        if (!$id) {
            return response()->json([
                'status' => 400,
                'message' => 'ID is required for update'
            ], 400);
        }

        // Get validation rules for the type
        $validationRules = $this->getValidationRules($type, $id);
        
        // Validate the request
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updated = null;

            switch ($type) {
                case 'department':
                    // TODO: Replace with actual model
                    // $department = Department::findOrFail($id);
                    // $department->update([
                    //     'name' => $request->name,
                    //     'description' => $request->description,
                    //     'head_id' => $request->head_id,
                    //     'updated_by' => auth()->id()
                    // ]);
                    // $updated = $department;
                    
                    $updated = ['id' => $id, 'name' => $request->name];
                    break;

                case 'role':
                    // TODO: Replace with actual model
                    $updated = ['id' => $id, 'name' => $request->name];
                    break;

                case 'employment_type':
                    // TODO: Replace with actual model
                    $updated = ['id' => $id, 'name' => $request->name];
                    break;

                case 'location':
                    // TODO: Replace with actual model
                    $updated = ['id' => $id, 'name' => $request->name];
                    break;

                case 'specialization':
                    // TODO: Replace with actual model
                    $updated = ['id' => $id, 'name' => $request->name];
                    break;

                default:
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid setting type'
                    ], 400);
            }

            return response()->json([
                'status' => 200,
                'message' => ucfirst(str_replace('_', ' ', $type)) . ' updated successfully',
                'data' => $updated
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while updating',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    private function deleteSettings(Request $request, $type, $id)
    {
        if (!$id) {
            return response()->json([
                'status' => 400,
                'message' => 'ID is required for deletion'
            ], 400);
        }

        try {
            switch ($type) {
                case 'department':
                    // TODO: Check if department has agents
                    // $department = Department::findOrFail($id);
                    // if ($department->agents()->count() > 0) {
                    //     return response()->json([
                    //         'status' => 422,
                    //         'message' => 'Cannot delete department with active agents'
                    //     ], 422);
                    // }
                    // $department->delete();
                    break;

                case 'role':
                    // TODO: Check if role is assigned to agents
                    // $role = Role::findOrFail($id);
                    // if ($role->agents()->count() > 0) {
                    //     return response()->json([
                    //         'status' => 422,
                    //         'message' => 'Cannot delete role assigned to agents'
                    //     ], 422);
                    // }
                    // $role->delete();
                    break;

                case 'employment_type':
                    // TODO: Check if employment type is in use
                    // $employmentType = EmploymentType::findOrFail($id);
                    // if ($employmentType->agents()->count() > 0) {
                    //     return response()->json([
                    //         'status' => 422,
                    //         'message' => 'Cannot delete employment type in use'
                    //     ], 422);
                    // }
                    // $employmentType->delete();
                    break;

                case 'location':
                    // TODO: Check if location has agents
                    // $location = WorkLocation::findOrFail($id);
                    // if ($location->agents()->count() > 0) {
                    //     return response()->json([
                    //         'status' => 422,
                    //         'message' => 'Cannot delete location with assigned agents'
                    //     ], 422);
                    // }
                    // $location->delete();
                    break;

                case 'specialization':
                    // TODO: Check if specialization is assigned
                    // $specialization = Specialization::findOrFail($id);
                    // if ($specialization->agents()->count() > 0) {
                    //     return response()->json([
                    //         'status' => 422,
                    //         'message' => 'Cannot delete specialization assigned to agents'
                    //     ], 422);
                    // }
                    // $specialization->delete();
                    break;

                default:
                    return response()->json([
                        'status' => 400,
                        'message' => 'Invalid setting type'
                    ], 400);
            }

            return response()->json([
                'status' => 200,
                'message' => ucfirst(str_replace('_', ' ', $type)) . ' deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while deleting',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    private function getValidationRules($type, $id = null)
    {
        $rules = [];

        switch ($type) {
            case 'department':
                $rules = [
                    'name' => [
                        'required',
                        'string',
                        'min:2',
                        'max:100',
                        Rule::unique('agent_departments', 'name')->ignore($id)
                    ],
                    'description' => 'nullable|string|max:500',
                    'head_id' => 'nullable|integer|exists:users,id'
                ];
                break;

            case 'role':
                $rules = [
                    'name' => [
                        'required',
                        'string',
                        'min:2',
                        'max:100',
                        Rule::unique('agent_roles', 'name')->ignore($id)
                    ],
                    'description' => 'nullable|string|max:500',
                    'level' => [
                        'required',
                        'string',
                        Rule::in(['entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive'])
                    ],
                    'department_id' => 'nullable|integer|exists:agent_departments,id'
                ];
                break;

            case 'location':
                $rules = [
                    'name' => [
                        'required',
                        'string',
                        'min:2',
                        'max:100'
                    ],
                    'address' => 'nullable|string|max:500',
                    'city' => 'nullable|string|max:100',
                    'country' => 'nullable|string|max:100',
                    'location_type' => [
                        'nullable',
                        'string',
                        Rule::in(['office', 'branch', 'remote', 'hybrid'])
                    ]
                ];
                break;

        }

        return $rules;
    }
}
