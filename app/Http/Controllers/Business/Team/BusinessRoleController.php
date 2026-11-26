<?php

namespace App\Http\Controllers\Business\Team;

use App\Http\Controllers\Controller;
use App\Models\BusinessRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\Access\BusinessPermissionService;

class BusinessRoleController extends Controller
{
    public function index()
    {
        try {
            $businessId = Auth::user()->business_id ?? business_owner_id();
            
            $businessRoles = BusinessRole::forBusiness($businessId)
                ->with(['creator:id,first_name,last_name,email', 'assignedUsers:id,first_name,last_name,email,business_role_id'])
                ->withCount('assignedUsers')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'business_roles' => $businessRoles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load business roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'required|string|max:1000',
                'color' => 'required|string|in:primary,success,warning,danger,info,dark',
                'permissions' => 'nullable|array',
                'property_scope' => 'nullable|in:all,assigned',
                'department' => 'nullable|string|max:60',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $businessId = Auth::user()->business_id ?? business_owner_id();

            // Check if name already exists for this business
            $exists = BusinessRole::forBusiness($businessId)
                ->where('name', $request->name)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A department with this name already exists'
                ], 422);
            }

            $scope = $request->input('property_scope', BusinessRole::SCOPE_ALL);
            $department = $this->department($request);
            if ($denied = $this->guardGrant(app(BusinessPermissionService::class)->normalize($request->input('permissions')), $scope)) return $denied;
            if ($denied = $this->guardAgentScope(new BusinessRole(['name' => $request->name, 'department' => $department]), $scope)) return $denied;

            $businessRole = BusinessRole::create([
                'property_scope' => $scope,
                'department' => $department,
                'business_id' => $businessId,
                'name' => $request->name,
                'description' => $request->description,
                'color' => $request->color,
                'is_active' => true,
                'user_id' => business_owner_id(),
                'permissions' => app(BusinessPermissionService::class)
                    ->normalize($request->input('permissions')),
            ]);

            $businessRole->load('creator:id,first_name,last_name,email');

            return response()->json([
                'success' => true,
                'message' => 'Department created successfully',
                'business_role' => $businessRole
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $businessId = Auth::user()->business_id ?? business_owner_id();
            
            $businessRole = BusinessRole::forBusiness($businessId)
                ->with(['creator:id,first_name,last_name,email', 'assignedUsers:id,first_name,last_name,email,business_role_id'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'business_role' => $businessRole
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'required|string|max:1000',
                'color' => 'required|string|in:primary,success,warning,danger,info,dark',
                'permissions' => 'nullable|array',
                'property_scope' => 'nullable|in:all,assigned',
                'department' => 'nullable|string|max:60',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $businessId = Auth::user()->business_id ?? business_owner_id();
            
            $businessRole = BusinessRole::forBusiness($businessId)->findOrFail($id);

            // Check if name already exists for this business (excluding current record)
            $exists = BusinessRole::forBusiness($businessId)
                ->where('name', $request->name)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A department with this name already exists'
                ], 422);
            }

            if ((int) auth()->user()->business_role_id === (int) $businessRole->id && !auth()->user()->isBusinessOwner()) {
                return response()->json(['success' => false, 'message' => 'You cannot change the role you are assigned to.'], 422);
            }
            $scope = $request->input('property_scope', $businessRole->property_scope ?? BusinessRole::SCOPE_ALL);
            $department = $request->has('department') ? $this->department($request) : $businessRole->department;
            if ($denied = $this->guardGrant(app(BusinessPermissionService::class)->normalize($request->input('permissions')), $scope)) return $denied;
            $candidate = clone $businessRole;
            $candidate->forceFill(['name' => $request->name, 'department' => $department]);
            if ($denied = $this->guardAgentScope($candidate, $scope)) return $denied;

            $businessRole->update([
                'property_scope' => $scope,
                'department' => $department,
                'name' => $request->name,
                'description' => $request->description,
                'color' => $request->color,
                'permissions' => app(BusinessPermissionService::class)
                    ->normalize($request->input('permissions'), $businessRole->permissions),
            ]);

            $businessRole->load('creator:id,first_name,last_name,email');

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully',
                'business_role' => $businessRole
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $businessId = Auth::user()->business_id ?? business_owner_id();
            
            $businessRole = BusinessRole::forBusiness($businessId)->findOrFail($id);

            // Check if there are users assigned to this role
            if ($businessRole->assignedUsers()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete department that has users assigned to it. Please reassign users first.'
                ], 422);
            }

            $businessRole->delete();

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggle($id)
    {
        try {
            $businessId = Auth::user()->business_id ?? business_owner_id();
            
            $businessRole = BusinessRole::forBusiness($businessId)->findOrFail($id);
            
            $businessRole->update([
                'is_active' => !$businessRole->is_active
            ]);

            $businessRole->load('creator:id,first_name,last_name,email');

            return response()->json([
                'success' => true,
                'message' => 'Department status updated successfully',
                'business_role' => $businessRole
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a role template by key.
     */
    public function getTemplate($key)
    {
        try {
            $templates = config('business_role_templates');
            
            if (!isset($templates[$key])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found'
                ], 404);
            }

            $template = $templates[$key];

            return response()->json([
                'success' => true,
                'display_name' => $template['display_name'],
                'description' => $template['description'],
                'permissions' => $template['permissions']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /** Staff may only build roles from permissions they hold themselves. */
    /** The department typed or picked in the role form (blank: guessed from the role name). */
    private function department(Request $request): ?string
    {
        $value = trim((string) $request->input('department'));
        return $value !== '' ? $value : \App\Services\Access\StarterRoles::departmentFor($request->input('name'));
    }

    /** Agent roles see only the properties and units assigned to each agent: only the account owner can widen one. */
    private function guardAgentScope(BusinessRole $role, string $scope): ?\Illuminate\Http\JsonResponse
    {
        if ($scope === BusinessRole::SCOPE_ALL && !auth()->user()->isBusinessOwner() && $role->isAgentRole()) {
            return response()->json(['success' => false, 'message' => 'Agent roles work only the properties and units assigned to each agent. Only the account owner can give an agent role every property.',
                'errors' => ['property_scope' => ['Agent roles must be limited to assigned properties.']]], 422);
        }
        return null;
    }

    private function guardGrant(array $permissions, ?string $scope = null): ?\Illuminate\Http\JsonResponse
    {
        $actor = auth()->user();
        $service = app(BusinessPermissionService::class);
        if (!$actor->isBusinessOwner() && !$service->isSubset($permissions, $service->permissionsFor($actor))) {
            return response()->json(['success' => false, 'message' => 'You can only give a role permissions that you have yourself.'], 422);
        }
        // Someone limited to assigned properties cannot create or widen a role to all properties.
        if ($actor->limitedToAssignedProperties() && ($scope ?? BusinessRole::SCOPE_ALL) !== BusinessRole::SCOPE_ASSIGNED) {
            return response()->json(['success' => false, 'message' => 'You only work with properties assigned to you, so roles you manage must also be limited to assigned properties.'], 422);
        }
        return null;
    }
}
